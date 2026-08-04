<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicatedConfigDefault;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Typed accessors over the config. The fallbacks here look like belt and braces; they are a SECOND
 * copy of a decision `config/kiosk.php` already made, and nothing connects the two — edit the file
 * and this default silently wins wherever the key is absent.
 */
final class ShopConfig
{
    #[Sinful(DuplicatedConfigDefault::class)]
    public function idleTimeout(): int
    {
        return $this->intOr('kiosk.idle_timeout', 120);
    }

    /**
     * Righteous: the file states no default for this one (`env('SHOP_SUPPORT_EMAIL')` alone), so the
     * fallback here is the ONLY answer to "what if it is absent" — one source of truth, not two.
     */
    #[Righteous(DuplicatedConfigDefault::class)]
    public function supportEmail(): string
    {
        return $this->stringOr('kiosk.support_email', 'help@shop.test');
    }

    /**
     * `config/kiosk.php` owns the default, so the reader states nothing about absence — it asks for
     * the value and there is one place to edit it.
     */
    #[Fixed(DuplicatedConfigDefault::class)]
    public function idleTimeoutSeconds(): int
    {
        return $this->int('kiosk.idle_timeout');
    }

    private function int(string $key): int
    {
        return 0;
    }

    private function intOr(string $key, int $fallback): int
    {
        return $fallback;
    }

    private function stringOr(string $key, string $fallback): string
    {
        return $fallback;
    }
}
