<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Documentation;

final class ArchaeologyComment extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'archaeology-comment',
            skill: Documentation::class,
            description: "History/archaeology comments (\"previously / used to / refactored / changed from\", task refs)",
            rule: "Comment what the code IS now, never its history — no \"previously/used to/changed from\" or task-ref archaeology."
        );
    }
}
