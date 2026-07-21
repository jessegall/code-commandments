<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\PlaceholderFilledDataDetector;
use PHPUnit\Framework\TestCase;

final class PlaceholderFilledDataDetectorTest extends TestCase
{
    private const DATA = <<<'PHP'
    namespace Spatie\LaravelData { class Data {} }

    namespace App {
        use Spatie\LaravelData\Data;

        final class WorkflowRowData extends Data
        {
            public function __construct(
                public readonly string $slug,
                public readonly string $name,
                public readonly ?string $trigger,
                public readonly bool $active,
                public readonly string $updatedAt,
                public readonly ?TriggerData $meta = null,
            ) {}
        }

        final class TriggerData extends Data
        {
            public function __construct(public readonly string $event) {}
        }

        final class ChartDataset extends Data
        {
            public function __construct(
                public readonly string $label,
                public readonly bool $fill,
                public readonly int $rate,
                public readonly TriggerData $trigger,
            ) {}
        }
    PHP;

    /** @return list<string> */
    private function scopes(string $body): array
    {
        $php = "<?php\n" . self::DATA . "\n" . $body . "\n}\n";

        return array_map(
            static fn ($m): string => $m->scope(),
            (new PlaceholderFilledDataDetector)->find(Codebase::fromString($php)),
        );
    }

    public function test_flags_an_empty_string_in_a_required_string_slot(): void
    {
        $body = <<<'PHP'
        final class Authoring
        {
            public function activate(string $slug): WorkflowRowData
            {
                return new WorkflowRowData(slug: $slug, name: $slug, trigger: null, active: true, updatedAt: '');
            }
        }
        PHP;

        $this->assertSame(['App\\Authoring::activate'], $this->scopes($body));
    }

    public function test_zero_and_false_are_ordinary_values_not_placeholders(): void
    {
        // The first version of this rule treated every "empty literal" alike and flagged 155 sites in a
        // real app — `fill: false` and `rate: 0` are the values those slots are FOR.
        $body = <<<'PHP'
        final class Charts
        {
            public function dataset(TriggerData $trigger): ChartDataset
            {
                return new ChartDataset(label: 'Previous period', fill: false, rate: 0, trigger: $trigger);
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($body));
    }

    public function test_one_value_feeding_several_slots_is_not_the_sin(): void
    {
        // `id: $key, name: $key, key: $key` reads like a placeholder but is usually correct — an
        // attribute whose name genuinely IS its key. Tried as a signal, dropped as noise.
        $body = <<<'PHP'
        final class Options
        {
            public function option(string $value): WorkflowRowData
            {
                return new WorkflowRowData(slug: $value, name: $value, trigger: null, active: true, updatedAt: $value);
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($body));
    }

    public function test_a_nullable_slot_already_admits_absence(): void
    {
        // `?string` says "may be missing", so passing nothing is honest — the lie only exists when the
        // type promises a value that is always there.
        $body = <<<'PHP'
        final class Authoring
        {
            public function draft(string $slug, string $name, string $stamp): WorkflowRowData
            {
                return new WorkflowRowData(slug: $slug, name: $name, trigger: null, active: false, updatedAt: $stamp);
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($body));
    }

    public function test_resolves_the_slot_positionally_too(): void
    {
        $body = <<<'PHP'
        final class Authoring
        {
            public function activate(string $slug): WorkflowRowData
            {
                return new WorkflowRowData($slug, $slug, null, true, '');
            }
        }
        PHP;

        $this->assertSame(['App\\Authoring::activate'], $this->scopes($body));
    }
}
