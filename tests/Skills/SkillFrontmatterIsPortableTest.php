<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Skills\Skill;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every published `SKILL.md` is read by MORE THAN ONE agent, and they do not agree on how forgiving
 * to be: one shrugs at frontmatter that isn't quite YAML, another skips the skill in silence. So the
 * frontmatter is held to the strict form — the two spec fields, and a `description` written as a
 * DOUBLE-QUOTED scalar.
 *
 * That quoting is the whole point. A plain YAML scalar may not carry `": "` and treats ` #` as the
 * start of a comment, and a trigger is arbitrary prose — eight of them carried a colon-space and
 * were not YAML at all. A double-quoted scalar is a superset of a JSON string, so the check below
 * decodes it with an INDEPENDENT parser (`json_decode`) and asserts it round-trips: if that holds,
 * the line is a well-formed scalar whatever reads it.
 *
 * @see \JesseGall\CodeCommandments\Skills\SkillRenderer::frontmatter() the one producer
 */
final class SkillFrontmatterIsPortableTest extends TestCase
{
    /**
     * The agent-skills spec caps a description at 1024 characters; past it a skill fails validation.
     */
    private const int MAX_DESCRIPTION = 1024;

    /**
     * The fields the spec defines. An agent-specific extra (`allowed-tools`, `context`, …) in a file
     * every agent reads is a portability bug, so the set is closed.
     *
     * @var list<string>
     */
    private const array FIELDS = ['name', 'description'];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function skills(): iterable
    {
        $root = dirname(__DIR__, 2) . '/skills';

        /**
         * @var \SplFileInfo $file
         */
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        ) as $file) {
            if ($file->getFilename() === 'SKILL.md') {
                yield substr($file->getPathname(), strlen($root) + 1) => [
                    $file->getPathname(),
                    (string) file_get_contents($file->getPathname()),
                ];
            }
        }
    }

    #[DataProvider('skills')]
    public function test_the_frontmatter_is_the_strict_portable_form(string $path, string $source): void
    {
        $this->assertStringStartsNotWith("\xEF\xBB\xBF", $source, 'a BOM makes the frontmatter unreadable to some agents');

        $lines = explode("\n", $source);

        $this->assertSame('---', $lines[0] ?? null, 'frontmatter must open on the first line');
        $this->assertSame('---', $lines[3] ?? null, 'frontmatter must be exactly the two spec fields');

        foreach (self::FIELDS as $index => $field) {
            $this->assertStringStartsWith("{$field}: ", $lines[$index + 1] ?? '', "field {$index} must be `{$field}`");
        }

        $name = substr($lines[1], strlen('name: '));
        $description = substr($lines[2], strlen('description: '));

        // The source tree is nested (`commandments/backend/absence`) while the PUBLISHED one is flat,
        // so "name == its directory" is a publish-time invariant, asserted where the publishing is.
        // Here we only hold the name to the shape the flatten produces.
        $this->assertSame(Skill::idFor(substr($name, strlen('commandments-'))), $name, 'the `name` must be a flat skill id');

        $decoded = json_decode($description);

        $this->assertIsString($decoded, "the description is not a well-formed quoted scalar: {$description}");
        $this->assertSame($description, json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'the description must round-trip');
        $this->assertLessThanOrEqual(self::MAX_DESCRIPTION, strlen($decoded), 'the description is over the spec cap of ' . self::MAX_DESCRIPTION);
    }

    /**
     * A leading `!` + backtick is a SHELL INJECTION in one agent's skill format and inert prose in
     * another's — so the same file would do two different things. Nothing we publish may carry it.
     */
    #[DataProvider('skills')]
    public function test_no_skill_body_carries_a_shell_injection(string $path, string $source): void
    {
        foreach (explode("\n", $source) as $number => $line) {
            $this->assertStringStartsNotWith('!`', ltrim($line), "{$path}:" . ($number + 1) . ' carries a shell injection');
        }
    }
}
