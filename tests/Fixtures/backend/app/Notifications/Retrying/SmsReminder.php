<?php

namespace Shop\Notifications\Retrying;

use JesseGall\CodeCommandments\Sins\Backend\RefusedBequest;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A reminder sent to a customer, which can be sent again when the first attempt bounced.
 */
abstract class Reminder
{
    private int $attempts = 0;

    abstract protected function deliver(string $to): bool;

    public function send(string $to): bool
    {
        $this->attempts++;

        return $this->deliver($to);
    }

    public function retry(string $to): bool
    {
        return $this->attempts < 3 && $this->send($to);
    }
}

/**
 * A text-message reminder; the gateway charges per message, so it refuses the retry it inherited.
 */
final class SmsReminder extends Reminder
{
    protected function deliver(string $to): bool
    {
        return str_starts_with($to, '+');
    }

    #[Sinful(RefusedBequest::class)]
    public function retry(string $to): bool
    {
        throw SmsIsNeverRetried::to($to);
    }
}

/**
 * Retrying a paid text message.
 */
final class SmsIsNeverRetried extends \RuntimeException
{
    public static function to(string $number): self
    {
        return new self("A text to {$number} is sent once.");
    }
}
