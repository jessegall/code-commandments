<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Rewriting;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use PhpParser\Node;

/**
 * A Scribe emends the source — it computes the new content of every file it changes.
 * Like a {@see \JesseGall\CodeCommandments\Detectors\Detector}, the computation is
 * PURE: it returns data, it does not write to disk. Applying the result
 * ({@see RewriteApplier}) and rendering it ({@see UnifiedDiff}) are separate
 * collaborators the caller owns; {@see Catalog} is the roll of Scribes.
 *
 * The whole tree is parsed for cross-file correctness; the {@see Scope} restricts
 * which files a Scribe actually edits.
 */
abstract class Scribe
{
    /**
     * The new content of each file this Scribe emends.
     *
     * @return array<string, string>  path => new file content (changed files only)
     */
    abstract public function rewrites(Codebase $codebase, Scope $scope): array;

    /**
     * The Scribe's short name (its class basename) — used to select it with `--only`.
     */
    public function name(): string
    {
        $parts = explode('\\', static::class);

        return end($parts);
    }

    /**
     * Apply byte-range edits to a source string, from the end backwards so earlier
     * offsets stay valid.
     *
     * @param  list<Edit>  $edits
     */
    protected function applyEdits(string $source, array $edits): string
    {
        usort($edits, static fn (Edit $a, Edit $b): int => $b->start <=> $a->start);

        foreach ($edits as $edit) {
            $source = substr($source, 0, $edit->start) . $edit->text . substr($source, $edit->end + 1);
        }

        return $source;
    }

    /**
     * An edit that replaces a node's source span with $text.
     */
    protected function replaceNode(Node $node, string $text): Edit
    {
        return new Edit($node->getStartFilePos(), $node->getEndFilePos(), $text);
    }
}
