<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The language a worked example is written in — what fences its code block, and what labels it when
 * one skill teaches a discipline that both engines have.
 *
 * A fact about the FIXTURE the example came from, never a guess from the sin that points at it: a
 * frontend rule's example is a template when it was marked in a `.vue` file and a module when it was
 * marked in a `.ts` one, and only the file knows which.
 */
enum Language: string
{
    case Php = 'php';

    case Vue = 'vue';

    case TypeScript = 'ts';

    /**
     * The language of the file at $path — the one place an extension is read as a language.
     */
    public static function ofFile(string $path): self
    {
        return match (true) {
            str_ends_with($path, '.vue') => self::Vue,
            str_ends_with($path, '.ts') => self::TypeScript,
            default => self::Php,
        };
    }

    /**
     * Is the file at $path written in a language judge reads? Each case's value IS its extension, so
     * a language added here is judged everywhere a path is filtered — never behind a list of its own.
     */
    public static function judges(string $path): bool
    {
        return self::tryFrom(pathinfo($path, PATHINFO_EXTENSION)) !== null;
    }

    /**
     * $text as a comment of this language on a line of its own — how a stamp is written into a file.
     */
    public function comment(string $text): string
    {
        return match ($this) {
            self::Php, self::TypeScript => "// {$text}",
            self::Vue => "<!-- {$text} -->",
        };
    }

    /**
     * Does $line carry a comment of this language — a line a declaration in a comment can be read
     * from? Read off the delimiter the line opens with, never its words.
     */
    public function isCommentLine(string $line): bool
    {
        $opened = ltrim($line);

        return match ($this) {
            self::Php, self::TypeScript => str_starts_with($opened, '//') || str_starts_with($opened, '/*') || str_starts_with($opened, '*'),
            self::Vue => str_starts_with($opened, '<!--'),
        };
    }

    /**
     * How a reader is told which language an example is in, when a skill shows more than one.
     */
    public function label(): string
    {
        return match ($this) {
            self::Php => 'PHP',
            self::Vue => 'Vue',
            self::TypeScript => 'TypeScript',
        };
    }

    /**
     * The language $text names by its {@see label} — how a rendered example heading says which one
     * it is in. Null when it names none, which is every other line of a document.
     */
    public static function namedIn(string $text): ?self
    {
        foreach (self::cases() as $language) {
            if (str_ends_with(rtrim($text), '— in ' . $language->label())) {
                return $language;
            }
        }

        return null;
    }
}
