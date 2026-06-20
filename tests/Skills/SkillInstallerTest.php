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

        // The umbrella directory holds one folder per subject, each with a SKILL.md.
        $this->assertFileExists($this->dir . '/option/SKILL.md');
        $this->assertFileExists($this->dir . '/invariants/SKILL.md');
        $this->assertFileExists($this->dir . '/enums/SKILL.md');

        // The reference/ deep-dive tree is copied recursively.
        $this->assertFileExists($this->dir . '/option/reference/api.md');
    }

    public function test_rewrites_the_namespace_placeholder_to_the_scaffold_namespace(): void
    {
        SkillInstaller::packaged()->install('Acme\\Support', $this->dir);

        // A reference deep-dive that uses {{ namespace }}\Option must land with
        // the consumer's namespace, so the examples match the scaffolded code.
        $api = (string) file_get_contents($this->dir . '/option/reference/api.md');
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

        file_put_contents($this->dir . '/option/SKILL.md', "hand-edited\n");

        $results = $installer->install('Acme\\Support', $this->dir, force: true);

        $option = null;
        foreach ($results as $r) {
            if ($r['slug'] === 'option') {
                $option = $r;
            }
        }

        $this->assertNotNull($option);
        $this->assertSame(SkillInstaller::STATUS_REWRITTEN, $option['status']);
        $this->assertStringContainsString('name: commandments-option', (string) file_get_contents($this->dir . '/option/SKILL.md'));
    }

    public function test_except_skips_named_skills(): void
    {
        SkillInstaller::packaged()->install('Acme\\Support', $this->dir, except: ['option']);

        $this->assertDirectoryDoesNotExist($this->dir . '/option');
        $this->assertFileExists($this->dir . '/invariants/SKILL.md');
    }

    public function test_auto_refresh_stamps_a_do_not_edit_banner(): void
    {
        SkillInstaller::packaged()->install('Acme\\Support', $this->dir, force: true, except: [], autoRefresh: true);

        $skill = (string) file_get_contents($this->dir . '/option/SKILL.md');

        $this->assertStringContainsString('AUTO-GENERATED — DO NOT EDIT', $skill);
        $this->assertStringContainsString('skills.auto_refresh is ON', $skill);
    }

    public function test_default_install_carries_no_banner(): void
    {
        SkillInstaller::packaged()->install('Acme\\Support', $this->dir);

        $skill = (string) file_get_contents($this->dir . '/option/SKILL.md');

        $this->assertStringNotContainsString('DO NOT EDIT', $skill);
    }
}
