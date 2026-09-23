<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Sins;

use JesseGall\CodeCommandments\Sins\Ecosystem;
use PHPUnit\Framework\TestCase;

/**
 * Which manifest answers "is this package installed" is the ecosystem's own knowledge — Composer's
 * installed set, a package.json, or a Python project's requirements and pyproject — and a project whose
 * manifest cannot be read is never over-filtered.
 */
final class EcosystemTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-ecosystem-' . uniqid();
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_pip_reads_requirements_files(): void
    {
        file_put_contents("{$this->root}/requirements.txt", "# pinned\nDjango>=4.2\nrequests[security]==2.31 ; python_version > '3.8'\n");
        file_put_contents("{$this->root}/requirements-dev.txt", "pytest\n");

        $this->assertTrue(Ecosystem::Pip->hasInstalled('django', $this->root));
        $this->assertTrue(Ecosystem::Pip->hasInstalled('requests', $this->root));
        $this->assertTrue(Ecosystem::Pip->hasInstalled('pytest', $this->root));
        $this->assertFalse(Ecosystem::Pip->hasInstalled('flask', $this->root));
    }

    public function test_pip_reads_a_pep_621_pyproject(): void
    {
        file_put_contents("{$this->root}/pyproject.toml", <<<'TOML'
            [project]
            name = "shop"
            dependencies = [
                "pydantic>=2",
                "Typing_Extensions",
            ]
            TOML);

        $this->assertTrue(Ecosystem::Pip->hasInstalled('pydantic', $this->root));
        $this->assertTrue(Ecosystem::Pip->hasInstalled('typing-extensions', $this->root));
        $this->assertFalse(Ecosystem::Pip->hasInstalled('shop', $this->root));
    }

    public function test_pip_reads_poetry_dependencies(): void
    {
        file_put_contents("{$this->root}/pyproject.toml", <<<'TOML'
            [tool.poetry.dependencies]
            python = "^3.12"
            fastapi = "^0.110"

            [tool.poetry.group.dev.dependencies]
            ruff = "*"
            TOML);

        $this->assertTrue(Ecosystem::Pip->hasInstalled('fastapi', $this->root));
        $this->assertTrue(Ecosystem::Pip->hasInstalled('ruff', $this->root));
        $this->assertFalse(Ecosystem::Pip->hasInstalled('django', $this->root));
    }

    public function test_without_a_manifest_every_package_counts_as_present(): void
    {
        $this->assertTrue(Ecosystem::Pip->hasInstalled('anything', $this->root));
        $this->assertTrue(Ecosystem::Npm->hasInstalled('anything', $this->root));
    }

    public function test_npm_reads_package_json(): void
    {
        file_put_contents("{$this->root}/package.json", json_encode(['devDependencies' => ['vue' => '^3']]));

        $this->assertTrue(Ecosystem::Npm->hasInstalled('vue', $this->root));
        $this->assertFalse(Ecosystem::Npm->hasInstalled('react', $this->root));
    }
}
