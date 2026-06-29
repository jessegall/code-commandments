<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use PHPUnit\Framework\TestCase;

/**
 * Locks the Scope semantics (git-free) before the commands are wired onto it — in
 * particular the realpath canonicalization that lets a scanned path match the
 * git change-set on a symlinked temp dir (macOS `/var` → `/private/var`).
 */
final class ScopeTest extends TestCase
{
    public function test_an_unscoped_scope_includes_everything(): void
    {
        $scope = Scope::everything();

        $this->assertFalse($scope->isScoped());
        $this->assertFalse($scope->isEmpty());
        $this->assertTrue($scope->includes('/anything/at/all.php'));
        $this->assertTrue($scope->includes(__FILE__));
        $this->assertNull($scope->files());
    }

    public function test_a_restricted_scope_includes_only_its_members(): void
    {
        $scope = Scope::restrictedTo([__FILE__]);

        $this->assertTrue($scope->isScoped());
        $this->assertFalse($scope->isEmpty());
        $this->assertTrue($scope->includes(__FILE__));
        $this->assertFalse($scope->includes(__DIR__ . '/HintsTest.php'));
    }

    public function test_includes_canonicalizes_both_sides_with_realpath(): void
    {
        // A path with a `/../` segment must resolve to the same member.
        $unnormalized = __DIR__ . '/../Cli/ScopeTest.php';
        $this->assertFileExists($unnormalized);

        $scope = Scope::restrictedTo([$unnormalized]);

        $this->assertTrue($scope->includes(__FILE__));
        $this->assertTrue($scope->includes($unnormalized));
    }

    public function test_an_empty_restriction_is_scoped_but_empty(): void
    {
        $scope = Scope::restrictedTo([]);

        $this->assertTrue($scope->isScoped());
        $this->assertTrue($scope->isEmpty());
        $this->assertFalse($scope->includes(__FILE__));
        $this->assertSame([], $scope->files());
    }
}
