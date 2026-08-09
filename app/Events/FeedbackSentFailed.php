<?php

namespace App\Events;

final readonly class FeedbackSentFailed implements LoggableEvent
{
    public function __construct(
        public string $feedbackType,
        public string $error,
    )
    {
    }

    public function eventType(): string
    {
        return 'ProfileController.feedbackSent';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return $this->error;
    }

    public function context(): array
    {
        return [
            'feedback_type' => $this->feedbackType,
            'error' => $this->error,
        ];
    }
}
