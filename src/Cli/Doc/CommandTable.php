<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Form;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Kernel;

/**
 * The Markdown projection of the CLI — the same {@see Help} declarations the terminal help renders,
 * as a table a document can embed. Sibling of {@see PlanExecutionOptions} and {@see HookCatalog}: a
 * doc never restates a command's grammar, it embeds this, so a new subcommand shows up in the README
 * and in every skill that teaches the command the moment it is declared.
 */
final class CommandTable
{
    /**
     * Every form of the named verbs — the reference a skill embeds for the command it teaches.
     */
    public static function forVerbs(string ...$verbs): string
    {
        $rows = [];

        foreach (self::resolve($verbs) as $command) {
            foreach ($command->help()->forms as $form) {
                $rows[] = self::row('commandments ' . $form->syntax, $form->does === '' ? $command->help()->summary : $form->does);
            }
        }

        return self::table('Command', 'Does', $rows);
    }

    /**
     * One row per command — the whole verb surface, as the top-level help lists it.
     */
    public static function overview(): string
    {
        $rows = [];

        foreach (new Kernel()->commands() as $command) {
            $rows[] = self::row('commandments ' . self::synopsis($command), $command->help()->summary);
        }

        return self::table('Command', 'Purpose', $rows);
    }

    /**
     * The commands answering to these verbs, in the order asked for. An unknown verb is skipped —
     * a document naming a verb that no longer exists simply loses its rows, and the missing lines
     * say so louder than a stale table would.
     *
     * @param  list<string>  $verbs
     * @return list<Command>
     */
    private static function resolve(array $verbs): array
    {
        $registry = [];

        foreach (new Kernel()->commands() as $command) {
            foreach ($command->names() as $name) {
                $registry[$name] = $command;
            }
        }

        return array_values(array_filter(array_map(static fn (string $verb): ?Command => $registry[$verb] ?? null, $verbs)));
    }

    /**
     * The invocation shown beside a verb in the overview — its first {@see Form}, or the bare verb.
     */
    private static function synopsis(Command $command): string
    {
        return $command->help()->synopsis() ?: $command->names()[0];
    }

    /**
     * @param  list<string>  $rows
     */
    private static function table(string $left, string $right, array $rows): string
    {
        return implode("\n", ["| {$left} | {$right} |", '|---|---|', ...$rows]) . "\n";
    }

    private static function row(string $syntax, string $does): string
    {
        return '| `' . self::cell($syntax) . '` | ' . self::cell($does) . ' |';
    }

    /**
     * Table-cell safe — the column separator escaped so a form like `list|met` can't split the row.
     */
    private static function cell(string $text): string
    {
        return str_replace('|', '\|', trim($text));
    }
}
