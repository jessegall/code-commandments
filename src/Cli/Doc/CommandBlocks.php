<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

use FilesystemIterator;
use JesseGall\CodeCommandments\Support\GeneratedBlock;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Fills every `commands:<verbs>` block in a document from the live CLI — a skill (or any doc) that
 * teaches a command declares WHERE its reference goes, naming the verbs it covers
 * (`<!-- BEGIN: commands:plan,checks … -->`, or `commands:all` for the whole surface), and the
 * generator writes WHAT it says from each command's own {@see \JesseGall\CodeCommandments\Cli\Help\Help}.
 * So a skill can never drift from the CLI it teaches: adding `stop-condition pause` puts it in the skill.
 */
final class CommandBlocks
{
    private const string PREFIX = 'commands:';

    private const string OPEN = '<!-- BEGIN: ';

    /**
     * Every command block in the document, refreshed. Documents with none come back unchanged.
     */
    public static function refresh(string $document): string
    {
        foreach (self::names($document) as $name) {
            $document = GeneratedBlock::replace($document, $name, "\n" . self::render($name) . "\n") ?? $document;
        }

        return $document;
    }

    /**
     * Every Markdown document under $directory, keyed by path — what a generator sweeps for blocks.
     *
     * @return array<string, string>  absolute path => contents
     */
    public static function documentsIn(string $directory): array
    {
        $documents = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $documents[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        ksort($documents);

        return $documents;
    }

    /**
     * The block names the document declares, in order — read off the BEGIN marker lines.
     *
     * @return list<string>
     */
    private static function names(string $document): array
    {
        $names = [];

        foreach (explode("\n", $document) as $line) {
            $marker = trim($line);

            if (! str_starts_with($marker, self::OPEN . self::PREFIX)) {
                continue;
            }

            // `<!-- BEGIN: commands:plan,checks (auto-generated…) -->` → `commands:plan,checks`
            $names[] = explode(' ', substr($marker, strlen(self::OPEN)), 2)[0];
        }

        return $names;
    }

    /**
     * The table for one block name — the whole surface for `commands:all`, else the named verbs.
     */
    private static function render(string $name): string
    {
        $verbs = explode(',', substr($name, strlen(self::PREFIX)));

        return $verbs === ['all'] ? CommandTable::overview() : CommandTable::forVerbs(...$verbs);
    }
}
