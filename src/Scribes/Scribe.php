<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use PhpParser\Node;

/**
 * Computes the new content of files it amends. Pure: returns data, never writes to disk. Applies
 * to the whole tree for cross-file correctness; {@see Scope} restricts which files are actually
 * edited. {@see Catalog} is the roll of Scribes; applying is a separate {@see RewriteApplier}.
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
