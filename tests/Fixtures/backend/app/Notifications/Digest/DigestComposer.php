<?php

namespace Shop\Notifications\Digest;

use JesseGall\CodeCommandments\Sins\Backend\ReassignedParameter;
use JesseGall\CodeCommandments\Sins\Backend\UnreachableStatement;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Writes the subject and body of the daily order digest mailed to a shop owner.
 */
final class DigestComposer
{
    #[Sinful(ReassignedParameter::class)]
    public function subject(string $shopName, int $orders): string
    {
        $shopName .= ' daily digest';

        return sprintf('%s: %d orders', $shopName, $orders);
    }

    #[Sinful(UnreachableStatement::class)]
    public function body(int $orders): string
    {
        if ($orders === 0) {
            return 'No orders today.';
            error_log('empty digest');
        }

        return str_repeat('*', min($orders, 20));
    }

    #[Fixed(UnreachableStatement::class)]
    public function summary(int $orders): string
    {
        if ($orders === 0) {
            return 'No orders today.';
        }

        return $orders === 1 ? 'one order' : "{$orders} orders";
    }

    #[Righteous(UnreachableStatement::class)]
    public function greeting(string $owner): string
    {
        if ($owner === '') {
            return 'Hello,';
        }

        return "Hello {$owner},";
    }
}
