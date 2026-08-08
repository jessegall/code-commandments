<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * A published `SKILL.md` with the worked examples of languages the project does not write removed.
 * A shipped skill is COPIED into a project rather than re-rendered — its examples come from the
 * package's own fixtures, which a consumer does not have — so the filtering happens here, on the
 * rendered document, and only on the example sections that name their language.
 */
final class LanguageSections
{
    private const string HEADING = '### ';

    private const string SECTION = '## ';

    /**
     * $skill with every `### … — in <Language>` example dropped for a language $languages excludes.
     */
    public static function keep(string $skill, Languages $languages): string
    {
        if ($languages->disabled() === []) {
            return $skill;
        }

        $kept = [];
        $dropping = false;

        foreach (explode("\n", $skill) as $line) {
            if (str_starts_with($line, self::HEADING)) {
                $dropping = self::namesExcludedLanguage($line, $languages);
            } elseif (str_starts_with($line, self::SECTION)) {
                $dropping = false; // a new top-level section always resumes
            }

            if (! $dropping) {
                $kept[] = $line;
            }
        }

        return implode("\n", $kept);
    }

    /**
     * Does this example heading name a language the project does not write?
     */
    private static function namesExcludedLanguage(string $heading, Languages $languages): bool
    {
        $language = Language::namedIn($heading);

        return $language !== null && ! $languages->writes($language);
    }
}
