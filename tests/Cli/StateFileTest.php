<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Cli\State\UnknownValue;
use PHPUnit\Framework\TestCase;

/**
 * The ONE format every session state file is written in: NAMED values, then the list of things a file
 * keeps, then the legend that says what all of it means — each divided by `-----`.
 */
final class StateFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-state-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function file(bool $list = true): StateFile
    {
        return new StateFile($this->dir . '/.thing', new Legend(
            'What this file is.',
            [
                'held_stops' => 'how many stops were held',
                'paused' => 'yes = set aside',
                'note' => 'something a human wrote',
            ],
            defaults: new State(held_stops: 0, paused: false, note: ''),
            list: $list ? 'one thing per line.' : null,
            safe: 'nothing is lost',
        ));
    }

    private function contents(): string
    {
        return (string) file_get_contents($this->dir . '/.thing');
    }

    public function test_values_are_written_by_name_and_read_back(): void
    {
        $this->file()->write(new State(held_stops: 3, paused: true, note: 'hello'));

        $state = $this->file()->read();

        $this->assertSame(3, $state->int('held_stops'));
        $this->assertTrue($state->flag('paused'));
        $this->assertSame('hello', $state->text('note'));
    }

    public function test_the_file_says_what_each_value_means(): void
    {
        $this->file()->write(new State(held_stops: 1, paused: false, note: ''));

        $contents = $this->contents();

        $this->assertStringContainsString("held-stops: 1\n", $contents, 'a name, not a positional line');
        $this->assertStringContainsString("paused: no\n", $contents, 'a flag says what it means');
        $this->assertStringContainsString('held-stops  how many stops were held', $contents, 'and carries its key');
        $this->assertStringContainsString('Safe to delete — nothing is lost.', $contents);
    }

    public function test_a_write_lays_the_WHOLE_state_out_in_the_legends_order(): void
    {
        // A file that only ever had one value touched would otherwise show only that, and a human
        // reading it could not tell an unset value from a missing feature.
        $this->file()->write(new State(note: 'only this was set'));

        $this->assertStringStartsWith("held-stops: 0\npaused: no\nnote: only this was set\n", $this->contents());
    }

    public function test_the_list_sits_between_its_own_dividers(): void
    {
        $this->file()->write(new State(held_stops: 0)->withItems(['first', 'second']));

        $this->assertSame(['first', 'second'], $this->file()->read()->items());
        $this->assertSame(3, substr_count($this->contents(), "\n-----\n") + 1, 'values | list | legend');
    }

    public function test_a_file_that_keeps_no_list_has_only_two_sections(): void
    {
        $this->file(list: false)->write(new State(note: 'solo')->withItems(['ignored']));

        $this->assertSame([], $this->file(list: false)->read()->items());
        $this->assertSame(2, substr_count($this->contents(), "\n-----\n") + 1);
    }

    public function test_writing_a_value_the_legend_does_not_declare_is_refused(): void
    {
        // The legend is the SCHEMA. A typo used to land in the file under a name nothing reads, and
        // every reader went on answering with its default — the state simply never moved.
        $this->expectException(UnknownValue::class);

        $this->file()->write(new State(held_stpos: 3));
    }

    public function test_reading_a_value_the_legend_does_not_declare_is_refused(): void
    {
        $this->file()->write(new State(held_stops: 3));

        $this->expectException(UnknownValue::class);

        $this->file()->read()->int('held_stpos');
    }

    public function test_a_value_from_an_older_shape_of_the_state_is_dropped_rather_than_carried(): void
    {
        file_put_contents($this->dir . '/.thing', "held-stops: 2\nlong-gone: 9\n-----\n-----\nlegend\n");

        $state = $this->file()->read();

        $this->assertSame(2, $state->int('held_stops'));
        $this->assertArrayNotHasKey('long-gone', $state->values());
    }

    public function test_a_value_arriving_with_newlines_stays_one_line(): void
    {
        $this->file()->write(new State(note: "first\n\nsecond"));

        $this->assertSame('first second', $this->file()->read()->text('note'));
    }

    public function test_an_absent_file_reads_as_the_empty_state(): void
    {
        $state = $this->file()->read();

        $this->assertSame(0, $state->int('held_stops'));
        $this->assertFalse($state->flag('paused'));
        $this->assertSame([], $state->items());
    }

    public function test_an_adjustment_keeps_every_other_value_and_the_list(): void
    {
        $file = $this->file();
        $file->write(new State(held_stops: 4, note: 'kept')->withItems(['a']));

        $file->write($file->read()->with(held_stops: 5));

        $this->assertSame(5, $file->read()->int('held_stops'));
        $this->assertSame('kept', $file->read()->text('note'));
        $this->assertSame(['a'], $file->read()->items());
    }
}
