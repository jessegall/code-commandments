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
    // A registry-shaped class whose getById() both launders the miss
    // (`?? null`, the auto-fixable NoNullCoalesceToNull symptom) AND is the
    // RegistryReturnContract root cause — same method, so the guard must withhold
    // the auto-fix until the contract is fixed.
    private const FIXTURE = <<<'PHP'
<?php

namespace RcGuard;

final class UserDirectory
{
    /** @var array<int, User> */
    private array $byId = [];

    public function add(int $id, User $user): void
    {
        $this->byId[$id] = $user;
    }

    public function getById(int $id): ?User
    {
        return $this->byId[$id] ?? null;
    }
}
PHP;

    private string $dir = '';

    private string $file = '';

    protected function defineEnvironment($app): void
    {
        $this->dir = sys_get_temp_dir() . '/rc-guard-' . uniqid();
        @mkdir($this->dir, 0777, true);
        $this->file = $this->dir . '/UserDirectory.php';
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

        // --all sweeps the isolated fixture scroll; bare `repent` now defaults
        // to git scope (#196), which this temp-dir base path has no changes in.
        $this->artisan('commandments:repent', ['--all' => true])
            ->expectsOutputToContain('SKIPPED')
            ->assertSuccessful();

        $after = file_get_contents($this->file);

        // The `?? null` was NOT stripped — the laundering path stayed closed.
        $this->assertSame($before, $after);
        $this->assertStringContainsString('$this->byId[$id] ?? null', $after);
    }
}
