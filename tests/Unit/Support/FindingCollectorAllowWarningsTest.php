<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Prophets\Backend\DuplicateCodeProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\FindingCollector;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;
use PHPUnit\Framework\TestCase;

class FindingCollectorAllowWarningsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-collector-' . uniqid();
        mkdir($this->dir, 0755, true);
        Environment::setBasePath($this->dir);
        file_put_contents($this->dir . '/Thing.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function results(): array
    {
        return [
            $this->dir . '/Thing.php' => [
                DuplicateCodeProphet::class => new Judgment(
                    sins: [new Sin('a sin', 1, null, 'sinsnip', null, 'sym-sin')],
                    warnings: [new Warning('a warning', 2, 'warnsnip', 'sym-warn')],
                ),
            ],
        ];
    }

    private function collector(): FindingCollector
    {
        return new FindingCollector(new JsonConfessionTracker($this->dir . '/.commandments/confessions.json', new Filesystem()));
    }

    public function test_allow_warnings_true_yields_both(): void
    {
        $kinds = array_map(fn ($f) => $f->kind, $this->collector()->collect($this->results(), null, false, true));

        $this->assertContains('sin', $kinds);
        $this->assertContains('warning', $kinds);
    }

    public function test_allow_warnings_false_drops_warnings(): void
    {
        $kinds = array_map(fn ($f) => $f->kind, $this->collector()->collect($this->results(), null, false, false));

        $this->assertContains('sin', $kinds);
        $this->assertNotContains('warning', $kinds, 'sins-only must drop warnings entirely');
    }
}
