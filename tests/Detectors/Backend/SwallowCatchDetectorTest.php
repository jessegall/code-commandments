<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\SwallowCatchDetector;
use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Packages\Package;
use JesseGall\CodeCommandments\Packages\Tags\ControlSignal;
use PHPUnit\Framework\TestCase;

final class SwallowCatchDetectorTest extends TestCase
{
    public function test_flags_catches_that_swallow_into_absence(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function a() { try { x(); } catch (\Throwable $e) {} }
            public function b() { try { x(); } catch (\Throwable $e) { return null; } }
            public function c() { try { x(); } catch (\Throwable $e) { return []; } }
            public function d() { try { x(); } catch (\Throwable $e) { return false; } }
            public function ok1() { try { x(); } catch (\Throwable $e) { $this->log($e); throw $e; } }
            public function ok2() { try { x(); } catch (\Throwable $e) { report($e); return null; } }
        }
        PHP;

        $hits = (new SwallowCatchDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['S::a', 'S::b', 'S::c', 'S::d'], $scopes);
    }

    /** The reporter's engine (issues #367, #368, #371): signals that steer control flow, not failures. */
    private const ENGINE = <<<'PHP'
    <?php
    namespace Engine {
        abstract class Signal extends \Exception {}
        final class BreakSignal extends Signal {}
        final class StopSignal extends Signal {}

        final class ForEachLoop {
            public function run($branches) {
                try { foreach ([] as $x) { $branches->fire('body'); } }
                catch (BreakSignal) {}
                return 'done';
            }
        }
        final class Run {
            public function execute() {
                try { $this->wave([]); }
                catch (StopSignal) {}
                return 'completed';
            }
        }
        final class Loader {
            public function hydrate($wires) {
                foreach ($wires as $wire) { try { $wire->toWire(); } catch (\Throwable) {} }
            }
        }
        final class Mixed_ {
            public function run($b) { try { $b->fire(); } catch (BreakSignal | \RuntimeException) {} }
        }
    }
    PHP;

    public function test_a_control_signal_catch_is_exempt_once_the_signal_type_is_tagged(): void
    {
        $codebase = Codebase::fromString(self::ENGINE);

        // Untagged, the empty catches are indistinguishable from swallowing a failure.
        $this->assertSame(
            ['Engine\\ForEachLoop::run', 'Engine\\Run::execute', 'Engine\\Loader::hydrate', 'Engine\\Mixed_::run'],
            array_map(static fn ($m): string => $m->scope(), (new SwallowCatchDetector)->find($codebase)),
        );

        Exemptions::usePackages(SignalPackage::class);

        // Tagged ONCE at the base Signal (the clause matches through extends), both signal catches go
        // quiet. The `catch (Throwable)` hydration absorb and the mixed `BreakSignal | RuntimeException`
        // catch still sin — the first swallows real errors, the second still has a failing alternative.
        $this->assertSame(
            ['Engine\\Loader::hydrate', 'Engine\\Mixed_::run'],
            array_map(static fn ($m): string => $m->scope(), (new SwallowCatchDetector)->find($codebase)),
        );
    }

    protected function tearDown(): void
    {
        Exemptions::usePackages();
    }
}

/** A consumer package tagging its engine's signal base — the whole declaration, in one place. */
final class SignalPackage extends Package
{
    public function register(Exemptions $exemptions): void
    {
        $exemptions->exempt(ControlSignal::class)->classes('Engine\\Signal');
    }
}
