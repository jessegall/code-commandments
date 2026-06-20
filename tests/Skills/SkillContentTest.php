<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use PHPUnit\Framework\TestCase;

/**
 * Content guards for the shipped skill stubs (#127): every reference a SKILL.md
 * points at must exist, and no skill code may call an Option method that is not
 * in the scaffolded Option — both are mechanical mistakes that otherwise ship as
 * broken links / runtime-fatal "good" examples.
 */
class SkillContentTest extends TestCase
{
    private const SKILLS_DIR = __DIR__ . '/../../stubs/skills';

    private const OPTION_STUB = __DIR__ . '/../../stubs/scaffold/Option.stub';

    /**
     * @return list<string>
     */
    private function markdownFiles(): array
    {
        $files = [];

        foreach (glob(self::SKILLS_DIR . '/*', GLOB_ONLYDIR) ?: [] as $skillDir) {
            foreach (glob($skillDir . '/SKILL.md') ?: [] as $f) {
                $files[] = $f;
            }
            foreach (glob($skillDir . '/reference/*.md') ?: [] as $f) {
                $files[] = $f;
            }
        }

        return $files;
    }

    /** The skill root dir (…/stubs/skills/<slug>) for any file beneath it. */
    private function skillRoot(string $file): string
    {
        return basename(dirname($file)) === 'reference' ? dirname(dirname($file)) : dirname($file);
    }

    public function test_there_are_skills_to_check(): void
    {
        $this->assertNotEmpty($this->markdownFiles(), 'No skill markdown found — wrong path?');
    }

    public function test_every_referenced_reference_file_exists(): void
    {
        foreach ($this->markdownFiles() as $file) {
            $content = (string) file_get_contents($file);
            preg_match_all('#reference/([\w-]+\.md)#', $content, $m);

            foreach (array_unique($m[1]) as $ref) {
                $path = $this->skillRoot($file) . '/reference/' . $ref;
                $this->assertFileExists(
                    $path,
                    sprintf('%s links to reference/%s which does not exist (broken deep-dive link).', $this->rel($file), $ref),
                );
            }
        }
    }

    public function test_no_skill_calls_a_nonexistent_option_method(): void
    {
        $allowed = $this->optionMethods();

        foreach ($this->markdownFiles() as $file) {
            $content = (string) file_get_contents($file);

            // Static factory calls: Option::<m>( must be a real method.
            preg_match_all('/Option::(\w+)\s*\(/', $content, $statics);
            foreach (array_unique($statics[1]) as $method) {
                $this->assertContains(
                    $method,
                    $allowed,
                    sprintf('%s calls Option::%s() — absent from Option.stub.', $this->rel($file), $method),
                );
            }

            // The canonical confusion: the scaffolded Option has transform(), NOT map().
            $this->assertDoesNotMatchRegularExpression(
                '/->\s*map\s*\(|Option::map\s*\(|`map\(\)`/',
                $content,
                sprintf('%s uses map() — the scaffolded Option has transform(), not map().', $this->rel($file)),
            );
        }
    }

    /**
     * Public method + static-factory names declared on the scaffolded Option.
     *
     * @return list<string>
     */
    private function optionMethods(): array
    {
        $stub = (string) file_get_contents(self::OPTION_STUB);
        preg_match_all('/public\s+(?:static\s+)?function\s+(\w+)\s*\(/', $stub, $m);

        return array_values(array_unique($m[1]));
    }

    private function rel(string $file): string
    {
        return 'stubs/skills/' . ltrim(str_replace(realpath(self::SKILLS_DIR) ?: self::SKILLS_DIR, '', realpath($file) ?: $file), '/');
    }
}
