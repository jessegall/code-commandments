<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Support\Skills\SkillInstaller;
use JesseGall\CodeCommandments\Tests\TestCase;

class SkillInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-skills-' . uniqid();
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    public function test_installs_every_skill_with_skill_md_and_reference_tree(): void
    {
        $results = SkillInstaller::packaged()->install('Acme\\Support', $this->dir);

        $created = array_filter($results, fn ($r) => $r['status'] === SkillInstaller::STATUS_CREATED);
        $this->assertNotEmpty($created, 'No skills were installed.');

        // Flat layout: one `commandments-<subject>` dir directly under .claude/skills/.
        $this->assertFileExists($this->dir . '/commandments-option/SKILL.md');
        $this->assertFileExists($this->dir . '/commandments-invariants/SKILL.md');
        $this->assertFileExists($this->dir . '/commandments-enums/SKILL.md');

        // The reference/ deep-dive tree is copied recursively.
        $this->assertFileExists($this->dir . '/commandments-option/reference/api.md');
    }

    public function test_rewrites_the_namespace_placeholder_to_the_scaffold_namespace(): void
    {
        SkillInstaller::packaged()->install('Acme\\Support', $this->dir);

        // A reference deep-dive that uses {{ namespace }}\Option must land with
        // the consumer's namespace, so the examples match the scaffolded code.
        $api = (string) file_get_contents($this->dir . '/commandments-option/reference/api.md');
        $this->assertStringContainsString('Acme\\Support\\Option', $api);
        $this->assertStringNotContainsString('{{ namespace }}', $api);
    }

    public function test_no_installed_file_still_carries_the_placeholder(): void
    {
        SkillInstaller::packaged()->install('Acme\\Support', $this->dir);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $contents = (string) file_get_contents($file->getPathname());
                $this->assertStringNotContainsString('{{ namespace }}', $contents, "Placeholder left in {$file->getPathname()}");
            }
        }
    }

    public function test_is_idempotent_and_skips_existing(): void
    {
        $installer = SkillInstaller::packaged();
        $installer->install('Acme\\Support', $this->dir);

        $second = $installer->install('Acme\\Support', $this->dir);

        foreach ($second as $result) {
            $this->assertSame(SkillInstaller::STATUS_SKIPPED, $result['status'], "Skill {$result['slug']} was not skipped on re-install.");
        }
    }

    public function test_force_rewrites_existing(): void
    {
        $installer = SkillInstaller::packaged();
        $installer->install('Acme\\Support', $this->dir);

        file_put_contents($this->dir . '/commandments-option/SKILL.md', "hand-edited\n");

        $results = $installer->install('Acme\\Support', $this->dir, force: true);

        $option = null;
        foreach ($results as $r) {
            if ($r['slug'] === 'option') {
                $option = $r;
            }
        }

        $this->assertNotNull($option);
        $this->assertSame(SkillInstaller::STATUS_REWRITTEN, $option['status']);
        $this->assertStringContainsString('name: commandments-option', (string) file_get_contents($this->dir . '/commandments-option/SKILL.md'));
    }

    public function test_except_skips_named_skills(): void
    {
        SkillInstaller::packaged()->install('Acme\\Support', $this->dir, except: ['option']);

        $this->assertDirectoryDoesNotExist($this->dir . '/commandments-option');
        $this->assertFileExists($this->dir . '/commandments-invariants/SKILL.md');
    }

    public function test_auto_refresh_stamps_a_do_not_edit_banner(): void
    {
        SkillInstaller::packaged()->install('Acme\\Support', $this->dir, force: true, except: [], autoRefresh: true);

        $skill = (string) file_get_contents($this->dir . '/commandments-option/SKILL.md');

        $this->assertStringContainsString('AUTO-GENERATED — DO NOT EDIT', $skill);
        $this->assertStringContainsString('skills.auto_refresh is ON', $skill);
    }

    public function test_default_install_carries_no_banner(): void
    {
        SkillInstaller::packaged()->install('Acme\\Support', $this->dir);

        $skill = (string) file_get_contents($this->dir . '/commandments-option/SKILL.md');

        $this->assertStringNotContainsString('DO NOT EDIT', $skill);
    }

    public function test_migrates_a_legacy_nested_group_dir_to_flat(): void
    {
        // #132 — the pre-flat layout nested skills under <root>/commandments/<slug>/,
        // which Claude Code never discovered. A re-install must remove that dead
        // group dir and write each skill flat as commandments-<slug>/.
        @mkdir($this->dir . '/commandments/option', 0755, true);
        file_put_contents($this->dir . '/commandments/option/SKILL.md', "old nested\n");

        SkillInstaller::packaged()->install('Acme\\Support', $this->dir);

        $this->assertDirectoryDoesNotExist($this->dir . '/commandments', 'the dead nested group dir must be removed');
        $this->assertFileExists($this->dir . '/commandments-option/SKILL.md', 'the skill must be installed flat');
    }

    public function test_does_not_touch_an_unrelated_flat_skill_named_commandments(): void
    {
        // The cleanup must only remove the group dir when it nests our slugs — a
        // legitimate flat skill literally named "commandments" (with its own
        // SKILL.md) must survive.
        @mkdir($this->dir . '/commandments', 0755, true);
        file_put_contents($this->dir . '/commandments/SKILL.md', "name: commandments\n");

        SkillInstaller::packaged()->install('Acme\\Support', $this->dir);

        $this->assertFileExists($this->dir . '/commandments/SKILL.md', 'an unrelated flat skill must not be deleted');
    }
}
