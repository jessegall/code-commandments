<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DivergentTwinDetector;
use PHPUnit\Framework\TestCase;

final class DivergentTwinDetectorTest extends TestCase
{
    public function test_flags_the_path_that_does_strictly_less_of_one_job(): void
    {
        $scopes = $this->scopesFlaggedIn(<<<'PHP'
        <?php
        namespace App;
        class DefinitionsCache {
            public function store(string $path, string $body): void {
                if (! \is_dir(\dirname($path))) { \mkdir(\dirname($path), 0755, true); }
                $partial = $path . \getmypid();
                \file_put_contents($partial, $body);
                \rename($partial, $path);
            }
        }
        class SkillInstaller {
            public function writeManaged(string $path, string $body): void {
                if (! \is_dir(\dirname($path))) { \mkdir(\dirname($path), 0755, true); }
                \file_put_contents($path, $body);
            }
        }
        PHP);

        // Both write a file into a directory they create; only one writes beside it and renames, so a
        // reader of the other can catch the file half-written. BOTH are reported: the duplication is
        // theirs jointly, and a reader shown one without the other is not told less than WHAT.
        $this->assertSame(
            ['App\DefinitionsCache::store', 'App\SkillInstaller::writeManaged'],
            $scopes,
        );
    }

    public function test_leaves_two_paths_a_third_method_chooses_between(): void
    {
        // `except` beside `only`, the cursor paginator beside the length-aware one: alternatives a
        // caller picks. The one doing less is answering for its own case.
        $this->assertSame([], $this->scopesFlaggedIn(<<<'PHP'
        <?php
        namespace App;
        class Normalizer {
            public function execute(array $items, bool $cursored): array {
                return $cursored ? $this->asCursor($items) : $this->asPages($items);
            }
            private function asPages(array $items): array {
                if (! \is_array($items)) { \trigger_error('no'); }
                return [\array_values($items), \array_keys($items), \count($items)];
            }
            private function asCursor(array $items): array {
                if (! \is_array($items)) { \trigger_error('no'); }
                return [\array_values($items), \array_keys($items)];
            }
        }
        PHP));
    }

    public function test_leaves_a_path_that_routes_through_the_other(): void
    {
        // The step it appears to lack is one call away — it IS the funnel's caller.
        $this->assertSame([], $this->scopesFlaggedIn(<<<'PHP'
        <?php
        namespace App;
        class Writer {
            public function write(string $path, string $body): void {
                if (! \is_dir(\dirname($path))) { \mkdir(\dirname($path), 0755, true); }
                $partial = $path . \getmypid();
                \file_put_contents($partial, $body);
                \rename($partial, $path);
            }
            public function writeOne(string $path, string $body): void {
                if (! \is_dir(\dirname($path))) { \mkdir(\dirname($path), 0755, true); }
                \file_put_contents($path, $body);
                $this->write($path, $body);
            }
        }
        PHP));
    }

    public function test_leaves_two_implementations_of_one_contract(): void
    {
        // Siblings under a contract are MEANT to differ; the one doing less answers a different case.
        $this->assertSame([], $this->scopesFlaggedIn(<<<'PHP'
        <?php
        namespace App;
        interface Store { public function put(string $path, string $body): void; }
        class Atomic implements Store {
            public function put(string $path, string $body): void {
                if (! \is_dir(\dirname($path))) { \mkdir(\dirname($path), 0755, true); }
                $partial = $path . \getmypid();
                \file_put_contents($partial, $body);
                \rename($partial, $path);
            }
        }
        class Plain implements Store {
            public function put(string $path, string $body): void {
                if (! \is_dir(\dirname($path))) { \mkdir(\dirname($path), 0755, true); }
                \file_put_contents($path, $body);
            }
        }
        PHP));
    }

    public function test_leaves_two_paths_that_cannot_be_producing_the_same_thing(): void
    {
        // One hands back a report, the other nothing at all — not one job however alike they read.
        $this->assertSame([], $this->scopesFlaggedIn(<<<'PHP'
        <?php
        namespace App;
        class Report {}
        class Auditor {
            public function audit(string $path): Report {
                if (! \is_dir(\dirname($path))) { \mkdir(\dirname($path), 0755, true); }
                \file_put_contents($path, \getmypid());
                \rename($path, $path);
                return new Report();
            }
        }
        class Cleaner {
            public function clean(string $path): void {
                if (! \is_dir(\dirname($path))) { \mkdir(\dirname($path), 0755, true); }
                \file_put_contents($path, '');
            }
        }
        PHP));
    }

    /**
     * @return list<string>
     */
    private function scopesFlaggedIn(string $code): array
    {
        $scopes = array_map(
            static fn ($match): string => $match->scope(),
            new DivergentTwinDetector()->find(Codebase::fromString($code)),
        );

        sort($scopes);

        return $scopes;
    }
}
