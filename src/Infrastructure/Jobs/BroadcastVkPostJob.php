<?php

declare(strict_types=1);

namespace Infrastructure\Jobs;

use App\Events\VkPostBroadcast;
use App\Events\VkPostBroadcastPreparationFailed;
use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramChat;
use Domain\Vk\Actions\SendVkPostToChatAction;
use Domain\Vk\DTOs\PreparedVkPostImages;
use Domain\Vk\Enums\VkImageSendMode;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Domain\Vk\Models\VkPostDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Infrastructure\Settings\VKSyncSettings;
use RuntimeException;
use Throwable;

/**
 * Массовая рассылка одного VkPost всем пользователям с привязанным
 * Telegram (кроме тех, у кого профиль помечен is_bot_active = false —
 * см. CheckTelegramBotAvailabilityAction). Идемпотентна: пропускает тех,
 * кому уже есть vk_post_deliveries со статусом sent, поэтому повторный
 * dispatch (например, после сбоя) не дублирует сообщения.
 */
class BroadcastVkPostJob implements ShouldBeUnique, ShouldQueue
{
    public const string FILE_MODE_ERROR = 'Массовая рассылка файлами с сайта недоступна. Выберите отправку оригинальными ссылками VK или ссылками с сайта';

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 1, не 5 — Bot API уже даёт нам свой ретрай на 429 внутри handle();
    // повторный запуск всей job задвоил бы часть уже отправленных сообщений
    // тем, для кого не успела записаться строка delivery.
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        private readonly int $vkPostId,
    ) {}

    public function uniqueId(): string
    {
        return 'vk-post-broadcast-'.$this->vkPostId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(SendVkPostToChatAction $send): void
    {
        $post = VkPost::query()->findOrFail($this->vkPostId);

        if (app(VKSyncSettings::class)->image_send_mode === VkImageSendMode::SiteFile->value) {
            event(new VkPostBroadcastPreparationFailed($post->id, self::FILE_MODE_ERROR));
            $this->fail(new RuntimeException(self::FILE_MODE_ERROR));

            return;
        }

        if (trim($post->broadcastText) === '') {
            Log::warning('Рассылка поста VK пропущена: пустой текст для рассылки', ['vk_post_id' => $post->id]);

            return;
        }

        try {
            $prepared = $send->prepare($post);
        } catch (Throwable $e) {
            event(new VkPostBroadcastPreparationFailed($post->id, $e->getMessage()));
            $this->fail($e);

            return;
        }

        $alreadySent = VkPostDelivery::query()
            ->where('vk_post_id', $post->id)
            ->where('status', 'sent')
            ->pluck('user_id')
            ->all();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        User::query()
            ->whereHas('telegramChat')
            ->whereDoesntHave('profile', fn ($q) => $q->where('is_bot_active', false))
            ->with('telegramChat')
            ->chunkById(200, function ($users) use ($send, $post, $prepared, $alreadySent, &$sent, &$failed, &$skipped): void {
                foreach ($users as $user) {
                    if (in_array($user->id, $alreadySent, true)) {
                        $skipped++;

                        continue;
                    }

                    [$status, $error] = $this->deliverOnce($send, $post, $user->telegramChat, $prepared);

                    VkPostDelivery::query()->updateOrCreate(
                        ['vk_post_id' => $post->id, 'user_id' => $user->id],
                        ['status' => $status, 'error' => $error, 'sent_at' => $status === 'sent' ? now() : null],
                    );

                    $status === 'sent' ? $sent++ : $failed++;

                    // Bot API держит ~30 сообщений/сек — тот же приём, что в
                    // CheckTelegramBotActivityCommand.
                    usleep(50_000);
                }
            });

        $post->update([
            'broadcast_at' => now(),
            'status' => VkPostStatus::SENT,
            'broadcast_stats' => ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped],
        ]);

        event(new VkPostBroadcast($post->id, $sent, $failed, $skipped, $prepared->mode->value, $prepared->imageUrls));
    }

    /**
     * Одна отправка с единственной повторной попыткой при 429
     * (retry_after из ответа Telegram).
     *
     * @return array{0: string, 1: string|null}
     */
    private function deliverOnce(SendVkPostToChatAction $send, VkPost $post, TelegramChat $chat, PreparedVkPostImages $prepared): array
    {
        try {
            $response = $send($post, $chat, $prepared);

            if ($response->telegraphOk()) {
                return ['sent', null];
            }

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->json('parameters.retry_after') ?? 1);
                sleep(min($retryAfter, 30));

                $retry = $send($post, $chat, $prepared);

                return $retry->telegraphOk()
                    ? ['sent', null]
                    : ['failed', $retry->json('description') ?? ('HTTP '.$retry->status())];
            }

            return ['failed', $response->json('description') ?? ('HTTP '.$response->status())];
        } catch (Throwable $e) {
            return ['failed', $e->getMessage()];
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Рассылка поста VK не выполнена', [
            'vk_post_id' => $this->vkPostId,
            'error' => $e->getMessage(),
        ]);
    }
}
