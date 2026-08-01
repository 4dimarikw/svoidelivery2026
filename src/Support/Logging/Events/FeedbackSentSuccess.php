<?php

namespace Support\Logging\Events;

final readonly class FeedbackSentSuccess implements LoggableEvent
{
    public function __construct(
        public string $feedbackType,
    )
    {
    }

    public function eventType(): string
    {
        return 'ProfileController.feedbackSent';
    }

    public function level(): string
    {
        return 'info';
    }

    public function message(): string
    {
        return "Feedback sent successfully.";
    }

    public function context(): array
    {
        return [
            'feedback_type' => $this->feedbackType,
        ];
    }
}
