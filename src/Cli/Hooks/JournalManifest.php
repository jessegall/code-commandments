<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Support\Summary;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Sins\Sin;
use ReflectionClass;

/**
 * The agent journal plugin's settings, read off the registry so they never drift: a switch per
 * language, and a switch per sin, grouped under the skill that teaches its fix. {@see JournalConfig}
 * writes the chosen switches into `.commandments/config.php`.
 */
final class JournalManifest
{
    public const LANGUAGES = 'Languages';

    public const FOLDERS = 'Folders';

    public const JUDGED = 'folders_judged';

    public const SKIPPED = 'folders_skipped';

    public static function sinKey(Sin $sin): string
    {
        return "sin_{$sin->name()}";
    }

    public static function languageKey(Language $language): string
    {
        return "language_{$language->value}";
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function settings(): array
    {
        $settings = [];

        $settings[self::JUDGED] = [
            'title' => 'Folders to check',
            'help' => 'One folder per line, from the project root. Empty, config.php keeps the folders it names.',
            'type' => 'list',
            'default' => '',
            'group' => self::FOLDERS,
        ];
        $settings[self::SKIPPED] = [
            'title' => 'Folders to leave out',
            'help' => 'One folder per line, never read or reported, such as generated code. This list is what config.php leaves out.',
            'type' => 'list',
            'default' => '',
            'group' => self::FOLDERS,
        ];

        foreach (Language::cases() as $language) {
            $settings[self::languageKey($language)] = [
                'title' => $language->label(),
                'help' => "Enables {$language->label()} sin detection",
                'type' => 'flag',
                'default' => 'true',
                'group' => self::LANGUAGES,
            ];
        }

        $detectors = Catalog::all();
        usort($detectors, static fn ($a, $b): int => [$a->sin()->slug(), $a->sin()->name()] <=> [$b->sin()->slug(), $b->sin()->name()]);

        foreach ($detectors as $detector) {
            $settings[self::sinKey($detector->sin())] = [
                'title' => (new ReflectionClass($detector->sin()))->getShortName(),
                'help' => Summary::of($detector),
                'type' => 'flag',
                'default' => 'true',
                'group' => $detector->sin()->slug(),
                'when' => array_map(
                    static fn (Language $language) => [self::languageKey($language) => true],
                    new ($detector->sin()->skillClass())()->languages(),
                ),
                'detail' => true,
            ];
        }

        return $settings;
    }
}
