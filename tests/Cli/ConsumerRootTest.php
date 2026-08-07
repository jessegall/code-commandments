<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\ConsumerRoot;
use PHPUnit\Framework\TestCase;

/**
 * `sync` WRITES INTO and DELETES FROM the directory it decides is the project — skills, an ignore
 * file, an instructions file, a settings file. So the decision has to be a decision, not whatever
 * the shell happened to be pointing at: run from a home directory, a bare `getcwd()` would have it
 * publish into the user's GLOBAL agent configuration and sweep the skills they installed there
 * themselves.
 *
 * The anchor is the `composer.json` that declares us a dependency — the same directory composer
 * itself runs the post-install hook in — found by walking UP, so running the command from a
 * subdirectory lands in the project rather than scattering a half-integration under it.
 */
final class ConsumerRootTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }

        $this->dirs = [];
    }

    public function test_a_project_root_is_itself(): void
    {
        $root = $this->project();

        $this->assertSame($root, ConsumerRoot::from($root));
    }

    public function test_it_walks_up_from_a_subdirectory(): void
    {
        $root = $this->project();
        mkdir("{$root}/src/Deep/Nested", 0775, true);

        $this->assertSame($root, ConsumerRoot::from("{$root}/src/Deep/Nested"));
    }

    public function test_a_directory_with_no_project_above_it_is_refused(): void
    {
        $orphan = (string) realpath(sys_get_temp_dir()) . '/cc-orphan-' . uniqid('', true);
        mkdir($orphan, 0775, true);
        $this->dirs[] = $orphan;

        // The temp dir has no composer.json above it — and crucially the walk stops there rather
        // than climbing to `/`, where it might find someone else's project.
        $this->assertNull(ConsumerRoot::from($orphan));
    }

    public function test_the_home_directory_is_refused_even_with_a_composer_json(): void
    {
        $home = $this->project();
        putenv("HOME={$home}");

        try {
            $this->assertNull(ConsumerRoot::from($home), 'a home directory holds GLOBAL agent config, never a consumer');
        } finally {
            putenv('HOME=' . (getenv('HOME') ?: ''));
        }
    }

    public function test_the_filesystem_root_is_refused(): void
    {
        $this->assertNull(ConsumerRoot::from('/'));
    }

    private function project(): string
    {
        $dir = (string) realpath(sys_get_temp_dir()) . '/cc-root-' . uniqid('', true);
        mkdir($dir, 0775, true);
        file_put_contents("{$dir}/composer.json", '{}');
        $this->dirs[] = $dir;

        return $dir;
    }
}
