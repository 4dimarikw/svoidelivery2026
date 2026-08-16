<?php

namespace Tests\Feature\Vk;

use DefStudio\Telegraph\Facades\Telegraph;
use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Domain\Vk\Models\VkPostDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Jobs\BroadcastVkPostJob;
use Tests\TestCase;

class BroadcastVkPostTest extends TestCase
{
    use RefreshDatabase;

    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = TelegramBot::create(['token' => 'test-bot-token', 'name' => 'Test Bot']);
    }

    private function userWithTelegram(int $chatId, ?bool $isBotActive = null): User
    {
        $user = User::factory()->telegram()->create();

        TelegramChat::create(['chat_id' => (string) $chatId, 'telegraph_bot_id' => $this->bot->id, 'user_id' => $user->id]);

        if ($isBotActive !== null) {
            $user->profile()->create(['is_bot_active' => $isBotActive]);
        }

        return $user;
    }

    private function makePost(array $attributes = []): VkPost
    {
        return VkPost::query()->create([
            'owner_id' => -1,
            'vk_post_id' => 1,
            'post_type' => 'post',
            'posted_at' => now(),
            'text' => 'Новость',
            'message_text' => 'Текст для рассылки',
            'images' => [],
            'raw' => [],
            'status' => VkPostStatus::READY,
            ...$attributes,
        ]);
    }

    private function runJob(VkPost $post): void
    {
        app()->call([new BroadcastVkPostJob($post->id), 'handle']);
    }

    public function test_sends_to_linked_users_and_skips_explicitly_inactive_bot(): void
    {
        Telegraph::fake();

        $active = $this->userWithTelegram(1, isBotActive: true);
        $unchecked = $this->userWithTelegram(2, isBotActive: null);
        $inactive = $this->userWithTelegram(3, isBotActive: false);

        $post = $this->makePost();

        $this->runJob($post);

        $this->assertSame('sent', VkPostDelivery::query()->where('user_id', $active->id)->value('status'));
        $this->assertSame('sent', VkPostDelivery::query()->where('user_id', $unchecked->id)->value('status'));
        $this->assertNull(VkPostDelivery::query()->where('user_id', $inactive->id)->first());

        $post->refresh();
        $this->assertSame(VkPostStatus::SENT, $post->status);
        $this->assertSame(2, $post->broadcast_stats['sent']);
    }

    public function test_rerun_does_not_duplicate_deliveries(): void
    {
        Telegraph::fake();

        $user = $this->userWithTelegram(1, isBotActive: true);
        $post = $this->makePost();

        $this->runJob($post);
        $this->runJob($post);

        $this->assertSame(1, VkPostDelivery::query()->where('user_id', $user->id)->count());
    }

    public function test_post_without_message_text_is_not_broadcast(): void
    {
        Telegraph::fake();

        $user = $this->userWithTelegram(1, isBotActive: true);
        $post = $this->makePost(['message_text' => null]);

        $this->runJob($post);

        $this->assertSame(0, VkPostDelivery::query()->where('user_id', $user->id)->count());

        $post->refresh();
        $this->assertSame(VkPostStatus::READY, $post->status);
        $this->assertNull($post->broadcast_at);
    }
}
