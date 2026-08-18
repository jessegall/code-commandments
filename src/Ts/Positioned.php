<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

/**
 * The source range a parsed thing occupies, and the parser's one way to record it. Shared by the two
 * frontend ASTs — the TypeScript {@see Node\Node} and the expression {@see Expr\Expr} — because
 * "where did this come from" is one question, and each answering it separately is how two answers
 * begin to differ.
 */
trait Positioned
{
    /**
     * The `[start, end)` byte range this occupies IN THE MODULE SOURCE it was parsed from — 0/0 for
     * something built by hand rather than parsed. Module-relative on purpose: the parser is handed a
     * string and knows nothing of the file it came from, so turning this into a `file:line` is
     * {@see ModuleFile}'s job, which owns both the path and the offset the script block begins at.
     */
    public private(set) int $start = 0;

    public private(set) int $end = 0;

    /**
     * Stamp this with the source range it was parsed from, and return it — written here so no
     * subclass has to thread two more constructor arguments through. Write access belongs to this
     * trait alone ({@see $start}), so a node cannot be moved once the parser has placed it.
     */
    public function locatedAt(int $start, int $end): static
    {
        $this->start = $start;
        $this->end = $end;

        return $this;
    }
}
