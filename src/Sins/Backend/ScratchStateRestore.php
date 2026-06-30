<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TypeHonesty;

final class ScratchStateRestore extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'scratch-state-restore',
            skill: TypeHonesty::class,
            description: "Scratch state on `\$this` — a method that saves one of its own fields to a local and restores it (`\$prev = \$this->scope; … \$this->scope = \$prev`), the field really a per-call input"
        );
    }
}
