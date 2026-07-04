<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * The parsed command line — built ONCE from `$argv` and handed to a {@see Command}. It is the
 * single place a flag is understood: a command never re-scans `$argv`, it asks the Input. Tokens
 * after the command verb are sorted into positional `arguments`, `--flag` switches, and
 * `--option=value` pairs; `raw()` keeps the untouched tail for the few collaborators that own their
 * own sub-grammar ({@see Scope\Scope::fromArgs} reads `--changes`/`--branch`/`--repent`).
 */
final class Input
{
    /**
     * @param  list<string>  $arguments  positionals after the command (a path, a subcommand, a sin id)
     * @param  array<string, string>  $options  `--k=v` pairs
     * @param  array<string, true>  $flags  bare `--k` switches
     * @param  list<string>  $raw  every token after the command, verbatim
     */
    private function __construct(
        private readonly string $command,
        private readonly array $arguments,
        private readonly array $options,
        private readonly array $flags,
        private readonly array $raw,
    ) {}

    /**
     * Parse a full `$argv` — `[script, command, ...rest]`. A missing or flag-first first token
     * means no command was named (the {@see Kernel} supplies the default).
     */
    public static function fromArgv(array $argv): self
    {
        $tokens = array_slice($argv, 1);
        $command = ($tokens[0] ?? '') !== '' && ! str_starts_with($tokens[0], '-') ? array_shift($tokens) : '';

        return self::of($command, array_values($tokens));
    }

    /**
     * Build for one command from its argument tail — the programmatic/test entry.
     *
     * @param  list<string>  $args
     */
    public static function of(string $command, array $args = []): self
    {
        $arguments = [];
        $options = [];
        $flags = [];

        foreach ($args as $token) {
            if (! str_starts_with($token, '--')) {
                $arguments[] = $token;

                continue;
            }

            $body = substr($token, 2);

            if (str_contains($body, '=')) {
                [$key, $value] = explode('=', $body, 2);
                $options[$key] = $value;
            } else {
                $flags[$body] = true;
            }
        }

        return new self($command, $arguments, $options, $flags, array_values($args));
    }

    public function command(): string
    {
        return $this->command;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function argument(int $index): ?string
    {
        return $this->arguments[$index] ?? null;
    }

    /**
     * The first positional, or $default — the common "path or subcommand" read.
     */
    public function firstArgument(?string $default = null): ?string
    {
        return $this->arguments[0] ?? $default;
    }

    /**
     * Is this switch present, as a bare `--flag` OR a `--flag=value`? (`--dry-run` and
     * `--dry-run=out.diff` both mean "dry run on".)
     */
    public function hasFlag(string $name): bool
    {
        return isset($this->flags[$name]) || isset($this->options[$name]);
    }

    /**
     * The value of a `--name=value` option, or null when absent (or given bare).
     */
    public function option(string $name, ?string $default = null): ?string
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * An OPTIONAL-valued flag — `true` for a bare `--name`, the string for `--name=value`, null
     * when absent. Models `--dry-run[=FILE]` / `--branch[=BASE]`.
     */
    public function optional(string $name): string|bool|null
    {
        return $this->options[$name] ?? (isset($this->flags[$name]) ? true : null);
    }

    /**
     * A comma-list option (`--exclude=a,b,c`) split and emptied of blanks.
     *
     * @return list<string>
     */
    public function list(string $name): array
    {
        $value = $this->options[$name] ?? null;

        return $value === null ? [] : array_values(array_filter(explode(',', $value)));
    }

    /**
     * Every token after the command, verbatim — for a collaborator that parses its own grammar.
     *
     * @return list<string>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
