<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\IfElseLadder;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Maps an HTTP status to a label via a branch ladder — a closed lookup wearing
 * control flow.
 */
final class HttpStatusText
{
    #[Sinful(IfElseLadder::class)]
    public function describe(int $status): string
    {
        if ($status >= 500) {
            return 'server error';
        } elseif ($status >= 400) {
            return 'client error';
        } elseif ($status >= 300) {
            return 'redirect';
        } elseif ($status >= 200) {
            return 'ok';
        }

        return 'informational';
    }

    public function isError(int $status): bool
    {
        return $status >= 400;
    }

    public function emoji(int $status): string
    {
        return $this->isError($status) ? '⚠️' : '✅';
    }

    public function retryable(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }
}
