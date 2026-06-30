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
            description: "Masked invariant — a transient own nullable read through `?->… ?? <fake literal>`, the field set inside the operation so the default answers an impossible \"not set yet\""
        );
    }
}
