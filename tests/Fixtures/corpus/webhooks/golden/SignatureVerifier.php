<?php

namespace App\Webhooks;

/**
 * Verifies that an inbound webhook body was signed with the shared secret.
 */
final class SignatureVerifier
{
    public function __construct(
        private readonly string $secret,
    ) {}

    public function verify(string $body, string $signature): void
    {
        $expected = hash_hmac('sha256', $body, $this->secret);

        if (! hash_equals($expected, $signature)) {
            throw InvalidSignatureException::mismatch();
        }
    }
}
