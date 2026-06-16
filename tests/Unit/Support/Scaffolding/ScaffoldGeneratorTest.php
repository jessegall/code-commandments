<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Scaffolding;

use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator;
use JesseGall\CodeCommandments\Tests\TestCase;

class ScaffoldGeneratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-scaffold-' . uniqid();
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    public function test_generates_classes_with_rewritten_namespace(): void
    {
        $results = ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $created = array_filter($results, fn ($r) => $r['status'] === ScaffoldGenerator::STATUS_CREATED);
        $this->assertNotEmpty($created);

        $trait = file_get_contents($this->dir . '/FromArrayOnly.php');
        $this->assertStringContainsString('namespace Acme\\Support;', $trait);
        $this->assertStringContainsString('trait FromArrayOnly', $trait);

        $this->assertFileExists($this->dir . '/Option.php');
        $this->assertFileExists($this->dir . '/NullCallable.php');
        $this->assertStringContainsString('namespace Acme\\Support;', file_get_contents($this->dir . '/Option.php'));
    }

    public function test_is_idempotent_and_skips_existing(): void
    {
        $gen = ScaffoldGenerator::packaged();
        $gen->generate('Acme\\Support', $this->dir);

        $second = $gen->generate('Acme\\Support', $this->dir);

        foreach ($second as $result) {
            $this->assertSame(ScaffoldGenerator::STATUS_SKIPPED, $result['status']);
        }
    }

    public function test_force_rewrites_existing(): void
    {
        $gen = ScaffoldGenerator::packaged();
        $gen->generate('Acme\\Support', $this->dir);

        file_put_contents($this->dir . '/Option.php', '<?php // hand-edited');

        $results = $gen->generate('Acme\\Support', $this->dir, force: true);

        $option = collect($results)->firstWhere('name', 'option');
        $this->assertSame(ScaffoldGenerator::STATUS_REWRITTEN, $option['status']);
        $this->assertStringContainsString('final readonly class Option', file_get_contents($this->dir . '/Option.php'));
    }

    public function test_except_skips_named_scaffolds(): void
    {
        $results = ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir, except: ['option']);

        $this->assertFileDoesNotExist($this->dir . '/Option.php');
        $this->assertFileExists($this->dir . '/FromArrayOnly.php');
    }
}
