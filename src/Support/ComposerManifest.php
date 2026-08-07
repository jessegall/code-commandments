<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\Option;

/**
 * A project's `composer.json`, as a thing you can ask questions of rather than a decoded array whose
 * keys every reader has to remember. It is the boundary type: the string keys are spelled HERE,
 * once, and nothing downstream indexes into the decoded shape.
 */
final readonly class ComposerManifest
{
    /**
     * @param  array<string, mixed>  $declared
     */
    private function __construct(private array $declared) {}

    /**
     * The nearest manifest at or above $path. None when no ancestor directory holds one, or the one
     * it holds cannot be read as JSON — a BOM, a stray comment, a half-written save.
     *
     * @return Option<self>
     */
    public static function nearest(string $path): Option
    {
        foreach (Path::selfAndAncestors(dirname($path)) as $dir) {
            if (! is_file($dir . '/composer.json')) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($dir . '/composer.json'), true);

            return is_array($decoded) ? Option::some(new self($decoded)) : Option::none();
        }

        return Option::none();
    }

    /**
     * The version constraint the project declares for PHP itself — `^8.5`, `>=8.4 <9.0`, `8.5.*`.
     * None when it requires no particular PHP.
     *
     * @return Option<string>
     */
    public function phpConstraint(): Option
    {
        $constraint = $this->declared['require']['php'] ?? null;

        return is_string($constraint) ? Option::some($constraint) : Option::none();
    }
}
