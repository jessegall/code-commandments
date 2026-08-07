<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Agents;

use JesseGall\CodeCommandments\Agents\SkillLink;
use PHPUnit\Framework\TestCase;

/**
 * Pointing an agent's skill folder at the library. Every link here has both endpoints inside this
 * test's own temp directory, asserted in {@see setUp} — a link out of the tree, plus any delete that
 * followed one, is how a test eats the repository it is testing.
 */
final class SkillLinkTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = (string) realpath(sys_get_temp_dir()) . '/cc-link-' . uniqid('', true);
        mkdir("{$this->dir}/.agents/skills/commandments-absence", 0775, true);
        file_put_contents("{$this->dir}/.agents/skills/commandments-absence/SKILL.md", "the real thing\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_it_links_an_agents_folder_to_the_library(): void
    {
        $this->assertTrue(new SkillLink()->point($this->link(), $this->target()));

        $this->assertTrue(is_link($this->link()));
        $this->assertSame(realpath($this->target()), realpath($this->link()));
        $this->assertSame("the real thing\n", file_get_contents($this->link() . '/SKILL.md'), 'and the agent reads through it');
    }

    public function test_the_link_is_relative_so_the_project_can_move(): void
    {
        new SkillLink()->point($this->link(), $this->target());

        // Asserting what the link RESOLVES to would pass with an absolute target baked in — which
        // breaks the moment the project is cloned, mounted or moved somewhere else.
        $this->assertSame('../../.agents/skills/commandments-absence', readlink($this->link()));
    }

    public function test_it_creates_the_agents_folder(): void
    {
        // The folder used to exist only as a side effect of copying skills into it; `symlink` will
        // not create a parent, so without this a fresh project links nothing at all.
        $this->assertDirectoryDoesNotExist("{$this->dir}/.claude/skills");
        $this->assertTrue(new SkillLink()->point($this->link(), $this->target()));
    }

    public function test_pointing_again_leaves_the_existing_link_alone(): void
    {
        new SkillLink()->point($this->link(), $this->target());
        $before = lstat($this->link());

        $this->assertTrue(new SkillLink()->point($this->link(), $this->target()));
        $this->assertSame($before['ino'], lstat($this->link())['ino'], 'the same link, not a fresh one');
    }

    public function test_a_directory_left_by_an_older_version_becomes_a_link(): void
    {
        mkdir($this->link(), 0775, true);
        file_put_contents($this->link() . '/SKILL.md', "a stale copy from the version that copied\n");

        $this->assertTrue(new SkillLink()->point($this->link(), $this->target()));

        $this->assertTrue(is_link($this->link()));
        $this->assertSame("the real thing\n", file_get_contents($this->link() . '/SKILL.md'));
        $this->assertFileExists($this->target() . '/SKILL.md', 'and the library it now points at is intact');
    }

    public function test_a_dangling_link_is_repaired(): void
    {
        @mkdir(dirname($this->link()), 0775, true);
        symlink("{$this->dir}/.agents/skills/gone", $this->link());

        // Not `is_dir`, so a guard that leads with that skips it forever — and `symlink` then fails
        // because something already occupies the path.
        $this->assertTrue(new SkillLink()->point($this->link(), $this->target()));
        $this->assertSame(realpath($this->target()), realpath($this->link()));
    }

    public function test_a_plain_file_in_the_way_is_replaced(): void
    {
        @mkdir(dirname($this->link()), 0775, true);
        // What a Windows checkout with `core.symlinks=false` turns a committed link into.
        file_put_contents($this->link(), '../../.agents/skills/commandments-absence');

        $this->assertTrue(new SkillLink()->point($this->link(), $this->target()));
        $this->assertTrue(is_link($this->link()));
    }

    public function test_without_symlinks_it_copies(): void
    {
        $this->assertTrue(new SkillLink(symlinks: false)->point($this->link(), $this->target()));

        $this->assertFalse(is_link($this->link()));
        $this->assertSame("the real thing\n", file_get_contents($this->link() . '/SKILL.md'));
    }

    public function test_a_copy_is_left_alone_when_it_already_matches(): void
    {
        $link = new SkillLink(symlinks: false);
        $link->point($this->link(), $this->target());
        $before = stat($this->link() . '/SKILL.md');

        $link->point($this->link(), $this->target());

        // A copy can never resolve to the target, so without a contents comparison a machine with no
        // links would delete and re-copy every skill on every install, forever.
        $this->assertSame($before['ino'], stat($this->link() . '/SKILL.md')['ino']);
    }

    public function test_a_copy_that_has_drifted_is_refreshed(): void
    {
        $link = new SkillLink(symlinks: false);
        $link->point($this->link(), $this->target());
        file_put_contents($this->link() . '/SKILL.md', "an old release's wording\n");

        $link->point($this->link(), $this->target());

        $this->assertSame("the real thing\n", file_get_contents($this->link() . '/SKILL.md'));
    }

    private function link(): string
    {
        return "{$this->dir}/.claude/skills/commandments-absence";
    }

    private function target(): string
    {
        $target = "{$this->dir}/.agents/skills/commandments-absence";

        $this->assertStringStartsWith($this->dir . '/', (string) realpath($target), 'a test link must not escape its temp dir');

        return $target;
    }
}
