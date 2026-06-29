<?php

namespace Shop\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Righteous twin for the framework-seam exemptions: an Eloquent cast is `new`-ed by
 * the framework (no container, no constructor), and its signature is fixed — so the
 * `Crypt::` facade (FacadeCallDetector) and reading the framework's raw `$attributes`
 * array by key (ArrayBagDetector) are the ONLY options here. Neither may be flagged.
 */
final class EncryptedNote implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $owner = $attributes['owner'] ?? 'system';

        return Crypt::decryptString($value) . " — {$owner}";
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? Crypt::encryptString($value) : null;
    }
}
