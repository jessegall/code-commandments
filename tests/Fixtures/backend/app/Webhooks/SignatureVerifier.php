<?php

namespace Shop\Webhooks;

use JesseGall\CodeCommandments\Detectors\Backend\DeepNestingDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Verifies a webhook signature inside a three-deep nest of header checks.
 */
final class SignatureVerifier
{
    /**
     * @param  array<string, string>  $headers
     */
    #[Sinful(DeepNestingDetector::class)]
    public function verify(array $headers, string $secret): bool
    {
        foreach ($headers as $name => $value) {
            if ($name === 'X-Signature') {
                if ($value !== '') {
                    if (hash_equals($value, hash_hmac('sha256', $name, $secret))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
