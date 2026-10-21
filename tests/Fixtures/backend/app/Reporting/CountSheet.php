<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\RethrownUnchanged;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\Mailer;

/**
 * Mails the stocktake count sheet. Three clauses, one of which does nothing: the first hands
 * the failure back untouched, the second tells the stock desk before letting it travel, and
 * the third names a narrow failure so the broad clause under it never sees that one.
 */
final class CountSheet
{
    public function __construct(private readonly Mailer $mailer) {}

    #[Sinful(RethrownUnchanged::class)]
    public function post(string $desk, string $sheet): void
    {
        try {
            $this->mailer->send($desk, 'Count sheet', $sheet);
        } catch (\RuntimeException $e) {
            throw $e;
        }
    }

    #[Righteous(RethrownUnchanged::class)]
    public function postCounted(string $desk, string $sheet): void
    {
        try {
            $this->mailer->send($desk, 'Count sheet', $sheet);
        } catch (\RuntimeException $e) {
            $this->mailer->send($desk, 'Count sheet held', $sheet);

            throw $e;
        }
    }

    public function postOrHold(string $desk, string $sheet): void
    {
        try {
            $this->mailer->send($desk, 'Count sheet', $sheet);
        } catch (\JsonException $e) {
            throw $e;
        } catch (\RuntimeException $other) {
            $this->mailer->send($desk, 'Count sheet held', $sheet);
        }
    }
}
