<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\PhpTypes\Option;

/**
 * The profile documents and roles this package ships as a starting point — a scaffold asks a question and
 * leaves a blank, where a template shows an answer somebody already found worth keeping. Discovered from
 * the folder rather than listed, so shipping one is adding a file.
 */
final readonly class Templates
{
    private const string FOLDER = '/templates';

    public function __construct(private string $root) {}

    public static function shipped(): self
    {
        return new self(dirname(__DIR__, 3));
    }

    /**
     * Every template, as `roles/secretary` or `documents/routine` — the path a caller names, and the one
     * that says where it belongs once installed.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $found = [];

        foreach (['roles', 'documents'] as $kind) {
            foreach (glob($this->root . self::FOLDER . '/' . $kind . '/*.md') ?: [] as $file) {
                $found[] = $kind . '/' . basename($file, '.md');
            }
        }

        sort($found);

        return $found;
    }

    /**
     * What $name says, or nothing when this package ships no such template.
     *
     * @return Option<string>
     */
    public function read(string $name): Option
    {
        $file = $this->fileFor($name);

        return is_file($file) ? Option::some((string) file_get_contents($file)) : Option::none();
    }

    /**
     * The first line of a template's prose — what a listing shows so a reader can choose between them
     * without printing each one in full.
     */
    public function about(string $name): string
    {
        foreach ($this->read($name) as $body) {
            foreach (explode("\n", $body) as $line) {
                $line = trim($line);

                if ($line !== '' && ! str_starts_with($line, '#') && ! str_starts_with($line, 'type:')) {
                    return $line;
                }
            }
        }

        return '';
    }

    /**
     * Where $name lands inside a profile. The template's own folder decides WHICH of the two it is; the
     * PROFILE decides where that is, so the layout is asked for rather than spelled again here.
     */
    public function homeIn(Profile $profile, string $name): string
    {
        [$kind, $leaf] = array_pad(explode('/', $name, 2), 2, '');

        return $kind === 'roles' ? $profile->pathToRole($leaf) : $profile->pathTo($leaf);
    }

    private function fileFor(string $name): string
    {
        [$kind, $leaf] = array_pad(explode('/', $name, 2), 2, '');

        if ($kind === '' || $leaf === '' || ! in_array($kind, ['roles', 'documents'], true)) {
            return '';
        }

        return $this->root . self::FOLDER . '/' . $kind . '/' . basename($leaf) . '.md';
    }
}
