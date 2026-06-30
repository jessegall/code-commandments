<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * A backend {@see RepentScribe} whose rewrite needs to consult the WHOLE codebase, not
 * just its findings — e.g. to resolve a target class's shape before deciding whether the
 * fix is safe. The {@see Backend\DetectorStep} injects the scanned codebase before calling
 * {@see RepentScribe::rewrite}, the backend mirror of the frontend extractor's component
 * library. A scribe with a purely-local rewrite ignores this and stays finding-only.
 */
interface NeedsCodebase
{
    public function withCodebase(Codebase $codebase): void;
}
