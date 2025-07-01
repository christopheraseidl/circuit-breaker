<?php

namespace christopheraseidl\CircuitBreaker\Laravel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $body;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->subject = $data['subject'] ?? 'Circuit Breaker Alert';
        $this->body = $data['body'] ?? '';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('circuit-breaker.notifiers.email.from_address')),
            subject: $this->subject
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'circuit-breaker::notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
