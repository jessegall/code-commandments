<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Help;

use JesseGall\CodeCommandments\Cli\Command;

/**
 * Renders the help — the ONLY place help text is laid out. Both screens are projected from the
 * commands themselves: the overview from every registered {@see Command}'s {@see Help} summary, and a
 * command page from its forms, options and notes. Nothing here knows a verb by name, so a command
 * that declares a new subcommand or flag is documented the moment it is registered.
 */
final class HelpScreen
{
    /**
     * Where the description column starts on a two-column line.
     */
    private const int GUTTER = 2;

    /**
     * The width lines wrap at — the classic terminal width, so the screen reads the same everywhere.
     */
    private const int WIDTH = 100;

    /**
     * The widest the label column may grow — past this a full invocation form would push every
     * description off the screen, so it takes its own line instead.
     */
    private const int COLUMN = 34;

    /**
     * The flags {@see \JesseGall\CodeCommandments\Cli\Kernel} and `bin/commandments` answer to,
     * whatever the verb — every command inherits them, so they are stated once, here.
     */
    private const array GLOBAL_OPTIONS = [
        '--help, -h' => 'show this help — or, after a verb, that command\'s own usage',
        '--memory=LIMIT' => 'memory ceiling for the run (default: 2G; -1 for no limit)',
    ];

    /**
     * @param  list<Command>  $commands  in registration order — the order the overview lists them
     */
    public function __construct(private readonly array $commands) {}

    /**
     * The top-level screen: every command's verb + summary, grouped by the section it declares.
     */
    public function overview(): string
    {
        $out = "Code Commandments — a compiler for architecture.\n\n"
            . "Usage:\n  commandments <command> [options]\n";

        foreach ($this->sections() as $section => $commands) {
            $out .= "\n{$section}:\n" . $this->rows(array_map(
                static fn (Command $command) => [$command->names()[0], $command->help()->summary],
                $commands,
            ));
        }

        return $out
            . "\nGlobal options:\n" . $this->rows(array_map(
                static fn (string $spec, string $does) => [$spec, $does],
                array_keys(self::GLOBAL_OPTIONS),
                array_values(self::GLOBAL_OPTIONS),
            ))
            . "\nRun `commandments <command> --help` for a command's forms, options and notes.\n";
    }

    /**
     * One command's page — its forms, its options, its notes.
     */
    public function page(Command $command): string
    {
        $help = $command->help();
        $out = "commandments {$command->names()[0]} — {$help->summary}\n";

        if (($aliases = array_slice($command->names(), 1)) !== []) {
            $out .= 'Also answers to: ' . implode(', ', $aliases) . "\n";
        }

        $out .= "\nUsage:\n" . $this->rows(array_map(
            static fn (Form $form) => ['commandments ' . $form->syntax, $form->does],
            $help->forms,
        ));

        if ($help->options !== []) {
            $out .= "\nOptions:\n" . $this->rows(array_map(
                static fn (Option $option) => [$option->spec, $option->does],
                $help->options,
            ));
        }

        foreach ($help->notes as $note) {
            $out .= "\n" . $this->paragraph($note) . "\n";
        }

        return $out;
    }

    /**
     * The usage error — a command was called wrong, so print WHY and then the same page `--help`
     * gives, on STDERR, with the conventional exit code. One call replaces the hand-written
     * `Usage: …` one-liners that used to drift from the real grammar.
     */
    public static function usage(Command $command, string $message = ''): int
    {
        fwrite(STDERR, ($message === '' ? '' : "✗ {$message}\n\n") . new self([$command])->page($command));

        return 2;
    }

    /**
     * The commands grouped under their declared section, sections in first-seen order.
     *
     * @return array<string, list<Command>>
     */
    private function sections(): array
    {
        $sections = [];

        foreach ($this->commands as $command) {
            $sections[$command->help()->section][] = $command;
        }

        return $sections;
    }

    /**
     * A block of `label   description` lines: the labels share one column, and a label too long for
     * that column (a full invocation form) keeps the column clean by taking the next line for its
     * description. Descriptions wrap under their own hanging indent.
     *
     * @param  list<array{string, string}>  $rows
     */
    private function rows(array $rows): string
    {
        // Widths are counted in CHARACTERS, not bytes — an option spec carrying an ellipsis or an em
        // dash would otherwise pad short and skew the whole column.
        $labels = array_map(static fn (array $row): string => $row[0], $rows);
        $column = min(self::COLUMN, max([0, ...array_map(mb_strlen(...), $labels)]) + self::GUTTER);
        $indent = str_repeat(' ', self::GUTTER + $column);
        $out = '';

        foreach ($rows as [$label, $description]) {
            $wrapped = $description === '' ? '' : $this->paragraph($description, self::WIDTH - mb_strlen($indent), $indent);
            $fits = mb_strlen($label) < $column;

            $out .= rtrim('  ' . ($fits ? mb_str_pad($label, $column) . ltrim($wrapped) : $label)) . "\n";
            $out .= $fits || $wrapped === '' ? '' : rtrim($wrapped) . "\n";
        }

        return $out;
    }

    /**
     * A paragraph wrapped to the screen width, every line carrying $indent.
     */
    private function paragraph(string $text, int $width = self::WIDTH - self::GUTTER, string $indent = '  '): string
    {
        return $indent . wordwrap(trim($text), max(20, $width), "\n" . $indent);
    }
}
