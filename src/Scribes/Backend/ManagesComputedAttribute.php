<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;

/**
 * Shared machinery for the scribes that stamp `#[Computed]` on a Spatie Data property hook — the import
 * and the indentation read. A get-only virtual property MUST carry `#[Computed]` or Spatie treats it as a
 * hydration input; both the lazify fix (which mints a hook) and the {@see HookMissingComputedScribe} (which
 * stamps an existing one) need the attribute imported and aligned.
 */
trait ManagesComputedAttribute
{
    /** The Spatie attribute that marks a get-only virtual property as computed (not a hydration input). */
    private const string COMPUTED = 'Spatie\\LaravelData\\Attributes\\Computed';

    /**
     * Add `use …\Computed;` when the file doesn't already import it — after the last existing `use`, else
     * after the `namespace …;`. A global-namespace file is left alone.
     */
    private function ensureComputedImport(Draft $draft, NodeMatch $match, ClassLike $class): void
    {
        $namespace = $class->getAttribute('parent');

        if (! $namespace instanceof Namespace_) {
            return;
        }

        $uses = array_values(array_filter($namespace->stmts, static fn (Node $stmt): bool => $stmt instanceof Use_));

        foreach ($uses as $use) {
            foreach ($use->uses as $used) {
                if (ltrim($used->name->toString(), '\\') === self::COMPUTED) {
                    return; // already imported
                }
            }
        }

        $source = $match->file->source;

        if ($uses !== []) {
            $offset = end($uses)->getEndFilePos() + 1;
            $insert = "\nuse " . self::COMPUTED . ';';
        } elseif ($namespace->name !== null) {
            $semicolon = strpos($source, ';', $namespace->name->getEndFilePos());
            $offset = $semicolon === false ? null : $semicolon + 1;
            $insert = "\n\nuse " . self::COMPUTED . ';';
        } else {
            return;
        }

        if ($offset !== null) {
            $draft->edit(new Span($match->file->path, $source, $offset, $offset), $insert);
        }
    }

    /** The leading whitespace of the line $pos sits on — the indentation to align an inserted line to. */
    private function indentAt(string $source, int $pos): string
    {
        $newline = strrpos(substr($source, 0, $pos), "\n");
        $lineStart = $newline === false ? 0 : $newline + 1;

        return substr($source, $lineStart, $pos - $lineStart);
    }
}
