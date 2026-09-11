<?php

namespace Tests\Unit\RemoteImages;

use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Services\RemoteImages\ImageDnsResolver;
use Services\RemoteImages\ImageDownloadFailed;
use Services\RemoteImages\ImageHttpTransport;
use Services\RemoteImages\RemoteImageDownloader;
use Services\RemoteImages\SpatieImageDownloader;
use Services\Vk\Support\VkImageUrlValidator;
use Tests\Support\FakeImageNetwork;
use Tests\TestCase;

class RemoteImageDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeImageNetwork::install();
    }

    public static function forbiddenUrls(): array
    {
        return array_map(fn ($url) => [$url], [
            'http://untappd.com/a.jpg', 'file:///tmp/a.jpg',
            'https://untappd.com.evil.test/a.jpg', 'https://cdn.untappd.com/a.jpg',
            'https://userapi.com.evil.test/a.jpg', 'https://127.0.0.1/a.jpg',
            'https://[::1]/a.jpg', 'https://2130706433/a.jpg',
            'https://user@untappd.com/a.jpg', 'https://untappd.com:444/a.jpg',
            'https://untappd.com/a.jpg#fragment', "https://untappd.com/a\n.jpg",
            'https://untappd.com\\@evil.test/a.jpg', 'https://untappd.com./a.jpg',
        ]);
    }

    #[DataProvider('forbiddenUrls')]
    public function test_rejects_url_before_dns_or_http(string $url): void
    {
        $dns = Mockery::mock(ImageDnsResolver::class);
        $dns->shouldNotReceive('resolve');
        app()->instance(ImageDnsResolver::class, $dns);
        $this->expectException(ImageDownloadFailed::class);
        app(RemoteImageDownloader::class)->download($url, 'untappd');
    }

    public static function forbiddenAddresses(): array
    {
        return array_map(fn ($ip) => [$ip], [
            '127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.0.1', '169.254.169.254',
            '100.64.0.1', '0.0.0.0', '224.0.0.1', '198.18.0.1', '192.0.2.1',
            '::1', '::', 'fc00::1', 'fe80::1', 'ff02::1', '::ffff:127.0.0.1',
            '2002:7f00:1::', '2001:db8::1', '64:ff9b::7f00:1',
        ]);
    }

    #[DataProvider('forbiddenAddresses')]
    public function test_rejects_mixed_dns_answers(string $ip): void
    {
        $dns = Mockery::mock(ImageDnsResolver::class);
        $dns->shouldReceive('resolve')->once()->andReturn(['93.184.216.34', $ip]);
        app()->instance(ImageDnsResolver::class, $dns);
        $transport = Mockery::mock(ImageHttpTransport::class);
        $transport->shouldNotReceive('get');
        app()->instance(ImageHttpTransport::class, $transport);
        $this->expectException(ImageDownloadFailed::class);
        app(RemoteImageDownloader::class)->download('https://untappd.com/a.jpg', 'untappd');
    }

    public function test_empty_dns_is_rejected(): void
    {
        $dns = Mockery::mock(ImageDnsResolver::class);
        $dns->shouldReceive('resolve')->andReturn([]);
        app()->instance(ImageDnsResolver::class, $dns);
        $this->expectException(ImageDownloadFailed::class);
        app(RemoteImageDownloader::class)->download('https://untappd.com/a.jpg', 'untappd');
    }

    public function test_each_download_resolves_again_and_pins_the_checked_address(): void
    {
        $dns = Mockery::mock(ImageDnsResolver::class);
        $dns->shouldReceive('resolve')->twice()->andReturn(['93.184.216.34'], ['127.0.0.1']);
        app()->instance(ImageDnsResolver::class, $dns);
        $transport = Mockery::mock(ImageHttpTransport::class);
        $transport->shouldReceive('get')->once()->with('https://untappd.com/a.jpg', 'untappd.com', '93.184.216.34', Mockery::type('callable'))
            ->andReturnUsing(fn ($url, $host, $ip, $write) => $write(FakeImageNetwork::jpeg()));
        app()->instance(ImageHttpTransport::class, $transport);
        $downloader = app(RemoteImageDownloader::class);
        $path = $downloader->download('https://untappd.com/a.jpg', 'untappd');
        $downloader->release($path);
        $this->assertFileDoesNotExist($path);
        $this->expectException(ImageDownloadFailed::class);
        $downloader->download('https://untappd.com/a.jpg', 'untappd');
    }

    public function test_adapter_and_vk_accept_valid_images_and_cleanup(): void
    {
        Http::fake(['*' => Http::response(FakeImageNetwork::jpeg())]);
        $downloader = app(RemoteImageDownloader::class);
        $path = $downloader->withCleanup(function () {
            $path = (new SpatieImageDownloader)->getTempFile('https://api.bcraftfest.ru/a.jpg');
            $this->assertFileExists($path);

            return $path;
        });
        $this->assertFileDoesNotExist($path);
        $validator = new VkImageUrlValidator;
        $this->assertTrue($validator('https://sun9-18.userapi.com/a.jpg'));
        $this->assertTrue($validator('https://sun9-18.userapi.com/a.jpg'));
        Http::assertSentCount(2);
        $validator->reset();
        $this->assertTrue($validator('https://sun9-18.userapi.com/a.jpg'));
        Http::assertSentCount(3);
    }

    public function test_redirect_is_not_followed(): void
    {
        Http::fake(['untappd.com/*' => Http::response('', 302, ['Location' => 'https://127.0.0.1/private'])]);
        try {
            app(RemoteImageDownloader::class)->download('https://untappd.com/a.jpg', 'untappd');
            $this->fail('Redirect must fail');
        } catch (ImageDownloadFailed) {
            Http::assertSentCount(1);
        }
    }

    public static function badFiles(): array
    {
        return [['mime'], ['corrupt'], ['bytes'], ['spatie_bytes'], ['dimensions'], ['pixels'], ['timeout'], ['certificate']];
    }

    #[DataProvider('badFiles')]
    public function test_failures_remove_temporary_files(string $failure): void
    {
        $before = glob(sys_get_temp_dir().'/remote-image-*');
        $payload = FakeImageNetwork::jpeg();
        if ($failure === 'mime') {
            $payload = '<html>not an image</html>';
        }
        if ($failure === 'corrupt') {
            $payload = substr($payload, 0, 30);
        }
        if ($failure === 'bytes') {
            config(['remote-images.max_bytes' => 128]);
        }
        if ($failure === 'spatie_bytes') {
            config(['media-library.max_file_size' => 128]);
        }
        if ($failure === 'dimensions') {
            config(['remote-images.max_dimension' => 1]);
        }
        if ($failure === 'pixels') {
            config(['remote-images.max_pixels' => 3]);
        }
        if (in_array($failure, ['timeout', 'certificate'])) {
            $transport = Mockery::mock(ImageHttpTransport::class);
            $transport->shouldReceive('get')->once()->andReturnUsing(function ($url, $host, $ip, $write) use ($failure) {
                $write('partial');
                throw new ImageDownloadFailed($failure);
            });
            app()->instance(ImageHttpTransport::class, $transport);
        } else {
            // Без Content-Length, заведомо ложный MIME в ответе.
            Http::fake(['*' => Http::response($payload, 200, ['Content-Type' => 'image/jpeg'])]);
        }
        try {
            app(RemoteImageDownloader::class)->download('https://untappd.com/a.jpg', 'untappd');
            $this->fail('Invalid download must fail');
        } catch (ImageDownloadFailed) {
            $this->assertSame($before, glob(sys_get_temp_dir().'/remote-image-*'));
        }
    }

    public function test_cleanup_after_consumer_exception(): void
    {
        Http::fake(['*' => Http::response(FakeImageNetwork::jpeg())]);
        $path = null;
        try {
            app(RemoteImageDownloader::class)->withCleanup(function () use (&$path) {
                $path = (new SpatieImageDownloader)->getTempFile('https://untappd.com/a.jpg');
                throw new \RuntimeException('Media write failed');
            });
        } catch (\RuntimeException) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_curl_security_options_and_redirect_header_abort(): void
    {
        $options = (new ImageHttpTransport)->options('https://untappd.com/a.jpg', 'untappd.com', '2606:4700::1111', fn ($chunk) => strlen($chunk));
        $this->assertSame(['untappd.com:443:[2606:4700::1111]'], $options[CURLOPT_RESOLVE]);
        $this->assertSame('', $options[CURLOPT_PROXY]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame(5, $options[CURLOPT_CONNECTTIMEOUT]);
        $this->assertSame(15, $options[CURLOPT_TIMEOUT]);
        $this->assertSame(CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        $this->assertSame(0, $options[CURLOPT_HEADERFUNCTION](null, "HTTP/1.1 302 Found\r\n"));
    }

    public function test_stream_stops_at_limit_without_consuming_remaining_chunks(): void
    {
        config(['remote-images.max_bytes' => 128]);
        $chunks = 0;
        $transport = Mockery::mock(ImageHttpTransport::class);
        $transport->shouldReceive('get')->once()->andReturnUsing(function ($url, $host, $ip, $write) use (&$chunks) {
            for ($i = 0; $i < 1000; $i++) {
                $chunks++;
                $write(str_repeat('x', 64));
            }
        });
        app()->instance(ImageHttpTransport::class, $transport);
        try {
            app(RemoteImageDownloader::class)->download('https://untappd.com/a.jpg', 'untappd');
            $this->fail('Stream must stop');
        } catch (ImageDownloadFailed) {
            $this->assertSame(3, $chunks);
        }
    }

    public static function imageFormats(): array
    {
        return [['jpeg'], ['png'], ['webp']];
    }

    #[DataProvider('imageFormats')]
    public function test_allowed_formats_are_identified_from_bytes(string $format): void
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        ('image'.$format)($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        Http::fake(['*' => Http::response($bytes, 200, ['Content-Type' => 'application/octet-stream'])]);
        $downloader = app(RemoteImageDownloader::class);
        $path = $downloader->download('https://untappd.com/a.'.$format, 'untappd');
        try {
            $this->assertSame('image/'.$format, mime_content_type($path));
        } finally {
            $downloader->release($path);
        }
    }
}
