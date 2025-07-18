<?php

namespace christopheraseidl\CircuitBreaker\Tests\Helpers;

use Carbon\Carbon;
use christopheraseidl\CircuitBreaker\Contracts\MailerContract;

/**
 * Test implementation of MailerContract for unit testing.
 */
class TestMailerAdapter implements MailerContract
{
    public array $sent = [];

    private ?\Throwable $exceptionToThrow = null;

    /**
     * Send email and track for testing.
     */
    public function send(array $to, string $subject, string $message): void
    {
        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }

        $this->sent[] = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'timestamp' => Carbon::now()->timestamp,
        ];
    }

    /**
     * Clear all sent emails.
     */
    public function clear(): void
    {
        $this->sent = [];
        $this->exceptionToThrow = null;
    }

    /**
     * Get the last email sent.
     */
    public function getLastEmail(): ?array
    {
        return end($this->sent) ?: null;
    }

    /**
     * Get all sent emails.
     */
    public function getSentEmails(): array
    {
        return $this->sent;
    }

    /**
     * Get count of sent emails.
     */
    public function count(): int
    {
        return count($this->sent);
    }

    /**
     * Set exception to throw on next send attempt.
     */
    public function shouldThrow(\Throwable $exception): void
    {
        $this->exceptionToThrow = $exception;
    }

    /**
     * Check if any email was sent to the given recipient.
     */
    public function wasSentTo(string $email): bool
    {
        foreach ($this->sent as $mail) {
            if (in_array($email, $mail['to'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if any email contains the given subject.
     */
    public function hasSubject(string $subject): bool
    {
        return ! empty(array_filter($this->sent, fn ($mail) => $mail['subject'] === $subject));
    }
}
