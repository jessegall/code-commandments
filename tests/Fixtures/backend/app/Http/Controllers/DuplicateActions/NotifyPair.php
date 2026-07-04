<?php

namespace Shop\Http\Controllers\DuplicateActions;

use Illuminate\Foundation\Http\FormRequest;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRouteAction;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Email and SMS notification endpoints both hand off to the same Notifier::deliver — one operation
 * reached two ways.
 */
class NotifyRequest extends FormRequest {}

final class Notifier
{
    public function deliver(NotifyRequest $request): string
    {
        return 'sent';
    }
}

final class EmailNotifyController
{
    public function __construct(private readonly Notifier $notifier) {}

    #[Sinful(DuplicateRouteAction::class)]
    public function deliver(NotifyRequest $request): string
    {
        return $this->notifier->deliver($request);
    }

    public function subjectLine(string $name, string $topic): string
    {
        return sprintf('Hi %s — news about %s', $name, $topic);
    }

    public function throttleWindow(int $sentToday): int
    {
        if ($sentToday > 500) {
            return 3600;
        }

        return 60;
    }

    public function preferredLocale(string $header): string
    {
        return strtok($header, ',') ?: 'en';
    }

    public function recipientCount(array $users, array $optedOut): int
    {
        return count(array_diff($users, $optedOut));
    }
}

final class SmsNotifyController
{
    public function __construct(private readonly Notifier $notifier) {}

    #[Sinful(DuplicateRouteAction::class)]
    public function deliver(NotifyRequest $request): string
    {
        return $this->notifier->deliver($request);
    }

    public function segments(string $body): int
    {
        $length = strlen($body);

        return (int) ceil($length / 160);
    }
}
