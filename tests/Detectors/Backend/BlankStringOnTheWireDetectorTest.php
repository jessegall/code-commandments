<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Bridge\Bridge;
use JesseGall\CodeCommandments\Detectors\Backend\BlankStringOnTheWireDetector;
use JesseGall\CodeCommandments\Vue\Codebase as FrontendCodebase;
use PHPUnit\Framework\TestCase;

final class BlankStringOnTheWireDetectorTest extends TestCase
{
    private const string SHAPE = <<<'PHP'
    <?php
    namespace App\Client;

    final readonly class EventSource
    {
        public function __construct(
            public string $channel,
            public string $socket,
            public int $busyMs,
        ) {}
    }
    PHP;

    private const string READER = <<<'TS'
    function dial(source: EventSource): Connection | null {
        return source.socket === '' ? polled(source) : socketed(source.socket);
    }
    TS;

    public function test_it_flags_a_field_its_typed_reader_asks_about(): void
    {
        // #510: nothing in PHP compares `$socket` to '' — the reader is a TypeScript module holding
        // an `EventSource`, and the blank it decodes as "no socket configured" is the same sin the
        // PHP-side rule names.
        $this->assertSame(['socket'], $this->judge(self::SHAPE, self::READER));
    }

    public function test_a_field_no_one_asks_about_is_left_alone(): void
    {
        // The blank is only absence when something READS it as absence — rendering proves nothing.
        $this->assertSame([], $this->judge(self::SHAPE, <<<'TS'
        function label(source: EventSource): string {
            return source.channel + source.socket;
        }
        TS));
    }

    public function test_a_field_of_another_type_that_shares_the_name_is_left_alone(): void
    {
        // The name alone is a coincidence; the pairing rests on the type the reader declared.
        $this->assertSame([], $this->judge(self::SHAPE, <<<'TS'
        function dial(config: DevtoolsConfig): boolean {
            return config.socket === '';
        }
        TS));
    }

    public function test_a_nullable_field_says_absence_in_its_type(): void
    {
        // The fix: the type carries the absence, so there is no blank to decode.
        $this->assertSame([], $this->judge(<<<'PHP'
        <?php
        namespace App\Client;

        final readonly class EventSource
        {
            public function __construct(
                public string $channel,
                public ?string $socket = null,
                public int $busyMs = 0,
            ) {}
        }
        PHP, self::READER));
    }

    public function test_a_field_the_shape_keeps_to_itself_is_left_alone(): void
    {
        // A private field never reaches the wire, whatever the far side calls its own values.
        $this->assertSame([], $this->judge(<<<'PHP'
        <?php
        namespace App\Client;

        final class EventSource
        {
            private string $socket = '';

            public function socket(): string
            {
                return $this->socket;
            }
        }
        PHP, self::READER));
    }

    /**
     * The field names flagged when $php is judged with $typeScript on the other side of the wire.
     *
     * @return list<string>
     */
    private function judge(string $php, string $typeScript): array
    {
        $backend = Codebase::fromString($php);
        $detector = new BlankStringOnTheWireDetector();

        Bridge::publish(Bridge::gather($backend, FrontendCodebase::fromTypeScript($typeScript)), [$detector]);

        return array_map(
            static fn ($match): string => (string) $match->asField()->unwrap()->name,
            $detector->find($backend),
        );
    }
}
