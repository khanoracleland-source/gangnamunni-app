<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SadminEnquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array
     */
    private $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $view, string $subject, array $data = [])
    {
        $this->view = $view;
        $this->subject = $subject;
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: $this->subject,
        );

        // from: 문의자 이름 표시 (SMTP 주소는 유지, 표시 이름만 변경)
        if (!empty($this->data['email'])) {
            $name = trim(($this->data['first_name'] ?? '') . ' ' . ($this->data['last_name'] ?? ''));
            $displayName = $name . ' (' . $this->data['email'] . ')';
            $fromEmail = config('mail.from.address', 'admin@seoulbeauty.app');
            $envelope->from = new \Illuminate\Mail\Mailables\Address($fromEmail, $displayName);
            $envelope->replyTo = [new \Illuminate\Mail\Mailables\Address($this->data['email'], $name)];
        }

        return $envelope;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->view,
            with: [
                'data' => $this->data,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
