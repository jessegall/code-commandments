<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TypeHonesty;

final class MaskedInvariant extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'masked-invariant',
            skill: TypeHonesty::class,
            description: 'Masked invariant — an own field read as `?->… ?? <fake literal>`, even though the very operation sets that field first, so the fallback only ever answers an impossible "not set yet".',
            rule: "Make an invariant certain (hold it non-nullable / assert it); don't mask it with `?->… ?? <fake>`."
        );
    }
}
