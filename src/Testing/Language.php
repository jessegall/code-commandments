<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

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
}
