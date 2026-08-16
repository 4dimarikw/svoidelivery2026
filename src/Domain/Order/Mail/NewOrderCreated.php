<?php

namespace Domain\Order\Mail;

use Domain\Order\Actions\OrderXmlFile;
use Domain\Order\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrderCreated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * $xml собирается заранее в HandleOrderCreated::notifyAdmin() (тем же
     * BuildOrderXml, что и UploadOrderToFTP) и передаётся сюда готовым —
     * пересобирать его в attachments() на воркере очереди не нужно.
     * Nullable — сбой генерации XML не должен отменять само письмо.
     */
    public function __construct(
        public Order $order,
        public ?OrderXmlFile $xml = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Создан новый заказ - '.$this->order->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-order',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->xml === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->xml->contents, $this->xml->filename)
                ->withMime('application/xml'),
        ];
    }
}
