<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\TypeHonesty;

final class ScratchStateRestore extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-scratch-state-restore',
            skill: TypeHonesty::class,
            description: '`previous = self.scope … self.scope = previous` — an attribute used as per-call scratch, saved and restored around the call',
            rule: 'Pass a per-call value as a parameter; don\'t save and restore one of your own attributes around the call.',
            suggestion: 'Hand the value down as an argument — or a small per-call object — and the attribute, its save and its restore disappear.',
        );
    }
}
