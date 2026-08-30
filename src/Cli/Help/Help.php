<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Help;

use JesseGall\CodeCommandments\Cli\Command;

/**
 * What a {@see Command} says about itself — its one-line summary, every {@see Form} it answers to,
 * the {@see Option}s it reads, and any longer notes. Declared ON the command, next to the code that
 * parses those flags, and rendered by {@see HelpScreen}: the top-level `commandments --help` screen
 * and every `commandments <verb> --help` page are PROJECTED from these, never hand-maintained. Adding
 * a subcommand means adding a `->form(...)` line beside it — there is no second list to update.
 */
final class Help
{
    /**
     * The overview headings a command can sit under — the plumbing verbs a human never types are
     * grouped away from the ones they do.
     */
    public const string COMMANDS = 'Commands';

    public const string HOOKS = 'Hooks (wired automatically — you rarely run these by hand)';

    /**
     * @param  list<Form>  $forms
     * @param  list<Option>  $options
     * @param  list<string>  $notes  free paragraphs printed under the options
     */
    private function __construct(
        public readonly string $summary,
        public readonly array $forms = [],
        public readonly array $options = [],
        public readonly array $notes = [],
        public readonly string $section = self::COMMANDS,
    ) {}

    public static function of(string $summary): self
    {
        return new self($summary);
    }

    /**
     * One invocation form — `$syntax` as typed after `commandments`, `$does` the half-line beside it.
     */
    public function form(string $syntax, string $does = ''): self
    {
        return $this->with(forms: [...$this->forms, new Form($syntax, $does)]);
    }

    /**
     * The NAME of every option this command declares, taken from its spec — `--last=N` is `last`. What an
     * unknown flag is measured against.
     *
     * @return list<string>
     */
    public function optionNames(): array
    {
        $names = [];

        foreach ($this->options as $option) {
            // Up to the first `=` OR `[`. An option whose value is optional is spelled `--branch[=BASE]`,
            // and reading only to the `=` named it `branch[` — a name no invocation can ever match, so
            // every such flag was documented in the help screen and refused by the parser that reads the
            // same declaration. Three of them shipped that way: `--branch`, `--repent`, `--dry-run`.
            $spec = ltrim(explode('[', explode('=', $option->spec, 2)[0], 2)[0], '-');

            if ($spec !== '') {
                $names[] = $spec;
            }
        }

        return $names;
    }

    public function option(string $spec, string $does): self
    {
        return $this->with(options: [...$this->options, new Option($spec, $does)]);
    }

    /**
     * Adopt options another collaborator owns — the scope flags belong to {@see \JesseGall\CodeCommandments\Cli\Scope\Scope},
     * which is the one place they are parsed, so every command that hands it `raw()` documents them from
     * that single declaration instead of restating them.
     *
     * @param  list<Option>  $options
     */
    public function adopt(array $options): self
    {
        return $this->with(options: [...$this->options, ...$options]);
    }

    public function note(string $paragraph): self
    {
        return $this->with(notes: [...$this->notes, $paragraph]);
    }

    public function section(string $section): self
    {
        return $this->with(section: $section);
    }

    /**
     * The first form's syntax — what the overview shows beside the verb.
     */
    public function synopsis(): string
    {
        return $this->forms === [] ? '' : $this->forms[0]->syntax;
    }

    private function with(?array $forms = null, ?array $options = null, ?array $notes = null, ?string $section = null): self
    {
        return new self(
            $this->summary,
            $forms ?? $this->forms,
            $options ?? $this->options,
            $notes ?? $this->notes,
            $section ?? $this->section,
        );
    }
}
