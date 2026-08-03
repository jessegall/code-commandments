<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Sins\Backend\TypeSwitch;
use JesseGall\CodeCommandments\Testing\Sinful;

interface Recipient {}

final class EmailRecipient implements Recipient
{
    public function address(): string
    {
        return 'someone@example.test';
    }
}

final class SmsRecipient implements Recipient
{
    public function number(): string
    {
        return '+3160000000';
    }
}

/**
 * Delivers a notice. Written as a run of sequential `if`s that each return — the same switch as
 * a ladder, only spelled with early exits, and every new channel means finding this method again.
 */
final class ChannelDispatch
{
    #[Sinful(TypeSwitch::class)]
    public function deliver(Recipient $recipient, string $notice): string
    {
        if ($recipient instanceof EmailRecipient) {
            return "mail:{$recipient->address()}:{$notice}";
        }

        if ($recipient instanceof SmsRecipient) {
            return "sms:{$recipient->number()}:{$notice}";
        }

        return "dropped:{$notice}";
    }
}
