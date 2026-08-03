<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\CoalescedLoopSubjectDetector;
use PHPUnit\Framework\TestCase;

final class CoalescedLoopSubjectDetectorTest extends TestCase
{
    public function test_flags_an_absence_check_buried_in_the_loop_header(): void
    {
        $code = <<<'PHP'
        <?php
        class T {
            public function coalesced(string $id, array $below): void {
                foreach ($below[$id] ?? [] as $child) {
                    $this->visit($child);
                }
            }
            public function shortTernary(array $options): void {
                foreach ($options['tags'] ?: [] as $tag) {
                    $this->visit($tag);
                }
            }
            public function guarded(string $id, array $below): void {
                if (! isset($below[$id])) {
                    return;
                }

                foreach ($below[$id] as $child) {
                    $this->visit($child);
                }
            }
            public function realDefault(array $options): void {
                foreach ($options['tags'] ?? $this->defaultTags() as $tag) {
                    $this->visit($tag);
                }
            }
            public function elsewhere(array $options): array {
                return $options['tags'] ?? [];
            }
            public function ownState(string $id): void {
                foreach ($this->listeners[$id] ?? [] as $called) {
                    $called();
                }
            }
            public function ownStatic(): void {
                foreach (self::$initializers[static::class] ?? [] as $registered) {
                    $registered($this);
                }
            }
            public function normalisedCall(string $dir): void {
                foreach (glob($dir . '/*.json') ?: [] as $file) {
                    $this->visit($file);
                }
            }
        }
        PHP;

        $hits = (new CoalescedLoopSubjectDetector)->find(Codebase::fromString($code));

        // Only the two headers coalescing a value the method was HANDED. Left alone: a real
        // fallback collection, a `?? []` that isn't a loop subject at all, the object's OWN
        // (sparse) state, and a stdlib call normalised where it is made.
        $this->assertSame(['T::coalesced', 'T::shortTernary'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
