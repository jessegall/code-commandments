<?php

namespace Shop\Webhooks;

use JesseGall\CodeCommandments\Sins\Backend\ArchaeologyComment;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Sins\Backend\InlineThrow;
use JesseGall\CodeCommandments\Sins\Backend\MessageAtThrow;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Handles a raw payment event payload — digs fields out of the array and throws a
 * generic exception inline when a required one is missing.
 */
final class PaymentEventHandler
{
    /**
     * @param  array<string, mixed>  $event
     */
    #[Sinful(ArchaeologyComment::class)]
    #[Sinful(ArrayBag::class)]
    #[Sinful(InlineThrow::class)]
    #[Sinful(GenericException::class)]
    #[Sinful(MessageAtThrow::class)]
    public function handle(array $event): void
    {
        // previously this lived inline in the StripeController
        $type = $event['type'];

        $this->record($type, $event['id'] ?? throw new \InvalidArgumentException('event id is required'));
    }

    private function record(string $type, string $id): void {}
}
