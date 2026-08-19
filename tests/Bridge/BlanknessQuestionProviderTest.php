<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Bridge;

use JesseGall\CodeCommandments\Bridge\BlanknessQuestion;
use JesseGall\CodeCommandments\Bridge\Frontend\BlanknessQuestionProvider;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class BlanknessQuestionProviderTest extends TestCase
{
    public function test_it_publishes_the_field_a_typed_reader_asks_about(): void
    {
        $codebase = Codebase::fromTypeScript(<<<'TS'
        export interface EventSource {
            socket: string;
            poll: string;
        }

        function dial(source: EventSource): Connection | null {
            if (source.poll === '') {
                return null;
            }

            return source.socket !== '' ? socketed(source.socket) : polled(source.poll);
        }
        TS);

        $this->assertSame(['EventSource::poll', 'EventSource::socket'], $this->asked($codebase));
    }

    public function test_it_reads_a_typed_binding_as_well_as_a_parameter(): void
    {
        $codebase = Codebase::fromTypeScript(<<<'TS'
        let listening: EventSource | null = null;

        export function stale(): boolean {
            return listening.channel === '';
        }
        TS);

        $this->assertSame(['EventSource::channel'], $this->asked($codebase));
    }

    public function test_a_question_about_an_untyped_subject_is_not_published(): void
    {
        // A bare `x === ''` is almost always about a local, and a module that never says what it is
        // holding cannot prove anything about a field on the other side.
        $codebase = Codebase::fromTypeScript(<<<'TS'
        export function payload(isolation: string, signal: string) {
            return isolation === '' ? { signal } : { signal, isolation };
        }

        export function labelled(holder) {
            return holder.id !== '';
        }
        TS);

        $this->assertSame([], $this->asked($codebase));
    }

    public function test_a_name_the_module_types_two_ways_proves_nothing(): void
    {
        $codebase = Codebase::fromTypeScript(<<<'TS'
        function first(row: OrderRow): boolean {
            return row.note === '';
        }

        function second(row: CustomerRow): boolean {
            return row.note === '';
        }
        TS);

        $this->assertSame([], $this->asked($codebase), 'one name, two types — the module is not saying');
    }

    /**
     * The published questions as `Type::field`, sorted so the assertion reads as a set.
     *
     * @return list<string>
     */
    private function asked(Codebase $codebase): array
    {
        $asked = array_map(
            static fn (BlanknessQuestion $question): string => $question->type . '::' . $question->field,
            (new BlanknessQuestionProvider())->contracts($codebase),
        );

        sort($asked);

        return $asked;
    }
}
