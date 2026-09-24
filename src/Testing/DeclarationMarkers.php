<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\ModuleCodebase;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Element;

/**
 * Frontend analog of `<!-- @sin -->` comments and `#[Sinful]` attributes. A `// @sin Name` comment
 * immediately above an `interface`/`type` marks that declaration from raw source (text-read, no parser).
 * Location comes from parsed {@see TypeDeclaration}, giving the exact `file:line` a finding reports.
 */
final class DeclarationMarkers
{
    /**
     * A marker as it reads inside any comment syntax: `@tag Name`.
     */
    private const string MARKER = '/@(\w+)\s+(\w+)/';

    /**
     * The `file:line` of every declaration marked `@{$tag} Name`, grouped by Name.
     *
     * @return array<string, list<string>>
     */
    public static function in(Codebase $codebase, string $tag): array
    {
        $marked = [];
        $lines = [];

        foreach ($codebase->typeDeclarations() as $declaration) {
            $lines[$declaration->file] ??= self::lines($declaration->file);

            foreach (self::markersAbove($lines[$declaration->file], $declaration->line, $tag) as $name) {
                $marked[$name][] = $declaration->file . ':' . $declaration->line;
            }
        }

        // Every OTHER node of a module too — a class, a method, a field, a statement. A marker binds
        // to whatever declaration follows it, and a rule about a field inside a class was unmarkable
        // while only top-level `interface`/`type` could carry one.
        foreach ($codebase->modules() as $module) {
            self::markLines($module->file, array_map(static fn ($node) => $module->lineAt($node->start), $module->nodes()), $tag, $marked);
        }

        foreach ($codebase->components() as $component) {
            self::inTemplate($component->template, $component->path, $tag, $marked);
        }

        return $marked;
    }

    /**
     * The `file:line` of every node marked `@{$tag} Name` in a codebase read as modules, in each module's own comment syntax, grouped by Name.
     *
     * @return array<string, list<string>>
     */
    public static function inModules(ModuleCodebase $codebase, string $tag): array
    {
        $marked = [];

        foreach ($codebase->modules() as $module) {
            self::markLines($module->file, array_map(static fn (array $span): int => $module->lineAt($span[0]), $module->nodeSpans()), $tag, $marked);
        }

        return $marked;
    }

    /**
     * Mark every line of $file in $nodeLines that a run of `@{$tag} Name` comments sits above — the one
     * walk a module of either language is read by, whatever its comments are spelled with.
     *
     * @param  list<int>  $nodeLines
     * @param  array<string, list<string>>  $marked
     */
    private static function markLines(string $file, array $nodeLines, string $tag, array &$marked): void
    {
        $lines = self::lines($file);

        foreach (array_unique($nodeLines) as $line) {
            foreach (self::markersAbove($lines, $line, $tag) as $name) {
                $marked[$name][] = $file . ':' . $line;
            }
        }
    }

    /**
     * Every `<!-- @{$tag} Name -->` in a template, bound to the element that follows it.
     *
     * A marker in markup and a marker in a module are the same claim about the same codebase, so
     * they are read in one place: a caller asking what is marked gets the whole answer, and neither
     * side has to keep its own walk of the other's half.
     *
     * @param  array<string, list<string>>  $marked
     */
    private static function inTemplate(Element $node, string $file, string $tag, array &$marked): void
    {
        $pending = [];

        foreach ($node->children as $child) {
            if ($child->isComment()) {
                $pending = [...$pending, ...self::markersAbove([$child->text], 2, $tag)];

                continue;
            }

            if ($child->isElement()) {
                foreach ($pending as $name) {
                    $marked[$name][] = $file . ':' . $child->line;
                }

                $pending = [];
            }

            self::inTemplate($child, $file, $tag, $marked);
        }
    }

    /**
     * Is $line a fixture marker — a comment in $language holding `@tag Name` and nothing the reader of
     * an example needs?
     */
    public static function isMarkerLine(string $line, Language $language): bool
    {
        return $language->isCommentLine($line) && preg_match(self::MARKER, trim($line)) === 1;
    }

    /**
     * Is $text, a comment's words, one fixture marker and nothing else — `@sin Name`, `@fixed Name`,
     * `@righteous Name` or `@example Name bad|good`? A marker is the fixture's metadata, never prose about
     * the code beneath it.
     */
    public static function isMarkerComment(string $text): bool
    {
        return preg_match('/^@(?:(?:sin|fixed|righteous)\s+\w+|example\s+\w+\s+(?:bad|good))$/', trim($text)) === 1;
    }

    /**
     * The names in a run of `@{$tag} Name` comments immediately above line $at (1-based)
     * — walking up over consecutive comment lines, stopping at the first line that is
     * neither blank nor a comment, exactly as a template marker binds to the next element.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function markersAbove(array $lines, int $at, string $tag): array
    {
        $names = [];

        // Start from the last line that EXISTS: a declaration reported past the end of the slice
        // has nothing above it to read, rather than a run of empty strings to skip.
        for ($n = min($at - 1, count($lines)); $n >= 1; $n--) {
            $text = trim($lines[$n - 1]);

            if ($text === '') {
                continue;
            }

            // A declaration wears every marker that applies to it, and they cannot all be the line
            // nearest it. So a marker for ANOTHER tag is stepped over rather than ending the run —
            // reading `@righteous` here must not hide the `@fixed` above it. Anything that is not
            // marker-shaped still ends it, so prose above a declaration keeps a marker from binding
            // across it.
            if (preg_match(self::MARKER, $text, $match) !== 1) {
                break;
            }

            if ($match[1] === $tag) {
                $names[] = $match[2];
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $file): array
    {
        return explode("\n", (string) @file_get_contents($file));
    }
}
