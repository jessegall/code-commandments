<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use PhpParser\Comment;
use PhpParser\Node;

/**
 * Computes the new content of files it amends. Pure: returns data, never writes to disk. Applies
 * to the whole tree for cross-file correctness; {@see Scope} restricts which files are actually
 * edited. {@see Catalog} is the roll of Scribes; applying is a separate {@see RewriteApplier}.
 */
abstract class Scribe
{
    use NamedByClass;

    /**
     * The new content of each file this Scribe emends.
     *
     * @return array<string, string>  path => new file content (changed files only)
     */
    abstract public function rewrites(Codebase $codebase, Scope $scope): array;

    /**
     * Apply byte-range edits to a source string, from the end backwards so earlier
     * offsets stay valid.
     *
     * @param  list<Edit>  $edits
     */
    protected function applyEdits(string $source, array $edits): string
    {
        usort($edits, Edit::lastFirst(...));

        foreach ($edits as $edit) {
            $source = $edit->appliedTo($source);
        }

        return $source;
    }

    /**
     * An edit that replaces the source a node — or a docblock, which carries the same span and is
     * not a node — occupies. php-parser reports an INCLUSIVE end and an {@see Edit}'s is half-open,
     * so this is the ONE place that conversion is written.
     */
    protected function replaceNode(Node|Comment $node, string $text): Edit
    {
        return Edit::overNode($node, $text);
    }
}
