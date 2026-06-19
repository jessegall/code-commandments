<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Prophets\Backend\NoNullCoalesceToNullProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * The repent auto-fix guard: an auto-fixable symptom (`NoNullCoalesceToNull`'s
 * `?? null`) is NOT rewritten while its root cause (`ThrowOnUnhandledCase`'s
 * `default => null`) sits unresolved in the same region — otherwise repent would
 * launder the invariant violation with no human in the loop.
 */
class RepentGuardTest extends TestCase
{
    private const FIXTURE = <<<'PHP'
<?php

namespace RcGuard;

enum Status: string
{
    case Open = 'open';
    case Paid = 'paid';

    public function priority(): ?int
    {
        return match ($this) {
            self::Open => 1,
            self::Paid => 2,
            default => null,
        };
    }

    public function compute(): int
    {
        return 5;
    }

    public function laundered(): ?int
    {
        return $this->compute() ?? null;
    }
}
PHP;

    private string $dir = '';

    private string $file = '';

    protected function defineEnvironment($app): void
    {
        $this->dir = sys_get_temp_dir() . '/rc-guard-' . uniqid();
        @mkdir($this->dir, 0777, true);
        $this->file = $this->dir . '/Status.php';
        file_put_contents($this->file, self::FIXTURE);

        $app['config']->set('commandments.scrolls', [
            'backend' => [
                'path' => $this->dir,
                'extensions' => ['php'],
                'prophets' => [
                    NoNullCoalesceToNullProphet::class,
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_repent_skips_the_auto_fix_while_the_root_cause_is_unresolved(): void
    {
        $before = file_get_contents($this->file);

        $this->artisan('commandments:repent')
            ->expectsOutputToContain('SKIPPED')
            ->assertSuccessful();

        $after = file_get_contents($this->file);

        // The `?? null` was NOT stripped — the laundering path stayed closed.
        $this->assertSame($before, $after);
        $this->assertStringContainsString('$this->compute() ?? null', $after);
    }
}
