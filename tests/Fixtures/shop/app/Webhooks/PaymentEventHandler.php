<?php

namespace Shop\Webhooks;

use JesseGall\CodeCommandments\Detectors\Backend\ArchaeologyCommentDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayBagDetector;
use JesseGall\CodeCommandments\Detectors\Backend\GenericExceptionDetector;
use JesseGall\CodeCommandments\Detectors\Backend\InlineThrowDetector;
use JesseGall\CodeCommandments\Detectors\Backend\MessageAtThrowDetector;
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
    #[Sinful(ArchaeologyCommentDetector::class)]
    #[Sinful(ArrayBagDetector::class)]
    #[Sinful(InlineThrowDetector::class)]
    #[Sinful(GenericExceptionDetector::class)]
    #[Sinful(MessageAtThrowDetector::class)]
    public function handle(array $event): void
    {
        // previously this lived inline in the StripeController
        $type = $event['type'];

        $this->record($type, $event['id'] ?? throw new \InvalidArgumentException('event id is required'));
    }

    private function record(string $type, string $id): void {}
}
