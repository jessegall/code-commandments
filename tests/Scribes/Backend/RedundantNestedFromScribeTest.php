<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\RedundantNestedFromDetector;
use JesseGall\CodeCommandments\Scribes\Backend\RedundantNestedFromScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

/**
 * The redundant-nested-`from` fix unwraps `X::from([...])` to its plain array, letting the parent `::from`
 * auto-hydrate it. It fixes, does not overshoot an object-source sibling, and is idempotent.
 */
final class RedundantNestedFromScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new RedundantNestedFromDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new RedundantNestedFromScribe();
    }

    private const HEADER = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Sandbox extends Data { public function __construct(public readonly string $label) {} }
        PHP;

    public function test_unwraps_the_nested_from_to_a_plain_array(): void
    {
        $fixed = $this->fixStable(self::HEADER . "\n" . <<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Sandbox $sandbox) {}
            }
            final class Builder {
                public function make(): Payload {
                    return Payload::from(['sandbox' => Sandbox::from(['label' => 'x'])]);
                }
            }
            PHP);

        $this->assertStringContainsString("'sandbox' => ['label' => 'x']", $fixed);
        $this->assertStringNotContainsString('Sandbox::from(', $fixed);
    }

    public function test_does_not_overshoot_an_object_source(): void
    {
        $php = self::HEADER . "\n" . <<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Sandbox $sandbox) {}
            }
            final class Builder {
                public function make(object $model): Payload {
                    return Payload::from(['sandbox' => Sandbox::from($model)]);
                }
            }
            PHP;

        $this->assertFalse($this->rewrote($php), 'an object-source conversion must be left untouched');
    }
}
