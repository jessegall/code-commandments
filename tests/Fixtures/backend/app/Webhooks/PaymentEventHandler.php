<?php

namespace Shop\Webhooks;

use JesseGall\CodeCommandments\Sins\Backend\ArchaeologyComment;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Sins\Backend\InlineThrow;
use JesseGall\CodeCommandments\Sins\Backend\MessageAtThrow;

use JesseGall\CodeCommandments\Testing\Fixed;
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
        // formerly lived inline in the StripeController; was extracted here
        $type = $event['type'];

        $this->record($type, $event['id'] ?? throw new \InvalidArgumentException('event id is required'));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    #[Fixed(ArchaeologyComment::class)]
    public function handleRefund(array $event): void
    {
        // a refund carries no id of its own, so the charge reference identifies the row
        $this->record('refund', $this->reference($event));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function reference(array $event): string
    {
        return 'ref';
    }

    private function record(string $type, string $id): void {}
}
