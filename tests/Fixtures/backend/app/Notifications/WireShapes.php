<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Sins\Backend\TypeSwitch;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The wire form of an email address on the notification payload.
 */
final class MailTarget
{
    public static function for(EmailRecipient $recipient): self
    {
        return new self;
    }
}

/**
 * The wire form of a phone number on the notification payload.
 */
final class SmsTarget
{
    public static function for(SmsRecipient $recipient): self
    {
        return new self;
    }
}

/**
 * Turns each recipient into the shape the wire wants.
 */
final class WireShapes
{
    /**
     * A MAPPER, not a question: every arm hands the recipient to the wire type that is made from it,
     * and the default passes an already-wire value through. Moving each arm onto its recipient would
     * make a domain type name its own wire shape — the inversion the mapper exception exists to prevent.
     */
    #[Righteous(TypeSwitch::class)]
    public function travels(mixed $value): mixed
    {
        return match (true) {
            $value instanceof EmailRecipient => MailTarget::for($value),
            $value instanceof SmsRecipient => SmsTarget::for($value),
            default => $value,
        };
    }
}
