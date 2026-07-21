<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\PhpTarget;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\HandRolledWitherScribe;
use JesseGall\CodeCommandments\Sins\Backend\HandRolledWither;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A wither that rebuilds its object by re-spelling the WHOLE constructor field list
 * (`return new self($this->a, $this->b, $changed, $this->d);`) instead of saying only what changes.
 * Every field added later must then be threaded through every one of these. PHP 8.5's clone-with
 * says it in one line, so the rule is silent on a project whose composer.json does not yet require
 * 8.5 — the fix would not compile. Mechanically rewritable: `repent` does it. Points at value-objects.
 */
final class HandRolledWitherDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new HandRolledWither();
    }

    public function scribe(): string
    {
        return HandRolledWitherScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        if (! PhpTarget::forCodebase($codebase)->supportsCloneWith()) {
            return [];
        }

        return $codebase
            ->whereNew()
            ->where(static fn (AstNode $node): bool => $node->isWitherRebuild())
            ->get();
    }
}
