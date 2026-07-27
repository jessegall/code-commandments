<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

/**
 * The `<!-- BEGIN: name … -->` / `<!-- END: name -->` block a document reserves for generated
 * content — the one mechanism behind every auto-generated table (README, skills). A document DECLARES
 * where a table goes; the generator fills it, so the prose around it stays hand-written and the table
 * inside it is never hand-maintained.
 */
final class GeneratedBlock
{
    /**
     * Replace what stands between the markers, or return null when the document has no such block.
     */
    public static function replace(string $document, string $name, string $content): ?string
    {
        $beginAt = strpos($document, "<!-- BEGIN: {$name} ");
        $endAt = strpos($document, "<!-- END: {$name} -->");

        if ($beginAt === false || $endAt === false || $endAt < $beginAt) {
            return null;
        }

        $beginClose = strpos($document, '-->', $beginAt);

        return $beginClose === false
            ? null
            : substr($document, 0, $beginClose + 3) . $content . substr($document, $endAt);
    }

    /**
     * The BEGIN marker line for a block — so a document that wants one can be written (or checked)
     * against the exact form {@see replace} looks for.
     */
    public static function begin(string $name, string $command): string
    {
        return "<!-- BEGIN: {$name} (auto-generated, run `{$command}`) -->";
    }

    public static function end(string $name): string
    {
        return "<!-- END: {$name} -->";
    }
}
