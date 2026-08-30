<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\PhpTypes\Option;

/**
 * The command a `Bash` tool call is about to run, read as a shell would read it: what it actually runs,
 * in order, and WHERE each piece runs. One reader, because the two gates that each answered this
 * question for themselves got it wrong in the same shape twice — a separator inside a quoted string is
 * not a separator, and a directory named in the command is where the command runs, whatever directory
 * the hook's own process happens to be standing in.
 */
final readonly class ShellCommand
{
    /**
     * Where a shell starts a new command. A BACKTICK is not among them, though a shell would treat it as
     * one: backticks are how prose quotes a command, so counting them refused this package's own commit
     * message for naming what it forbids. `$(` stays, being substitution and nothing else.
     */
    private const array SEPARATORS = ['&&', '||', ';', '|', '$('];

    private const string CHANGE_DIRECTORY = 'cd';

    /**
     * How git is told to act on a checkout other than the one it is standing in.
     */
    private const string GIT_DIRECTORY = '-C';

    private const string HEREDOC = '<<';

    private function __construct(private string $command) {}

    public static function of(string $command): self
    {
        return new self($command);
    }

    /**
     * Every command the shell would run, each paired with the directory it would run in. `cd` moves that
     * directory for everything after it — which is how a compound command reaches a lane — and `git -C`
     * names one for a single invocation without moving anything.
     *
     * $cwd is where the shell starts, which is the session's own directory. Anything the command SAYS
     * about a directory wins over it, because the command is the thing about to run.
     *
     * @return list<Invocation>
     */
    public function invocations(string $cwd = ''): array
    {
        $found = [];
        $at = $cwd;

        foreach ($this->segments() as $segment) {
            $words = Words::of($segment);

            if ($words->opens(self::CHANGE_DIRECTORY)) {
                // A bare `cd` goes home, and nothing here knows which home — so it moves nothing rather
                // than resolving a directory nobody named.
                foreach ($words->after(self::CHANGE_DIRECTORY) as $where) {
                    $at = $this->resolve($where, $at);
                }

                continue;
            }

            $found[] = new Invocation($segment, $this->statedBy($words, $at));
        }

        return $found;
    }

    /**
     * The command split at the points a shell would start a new one, each trimmed of the whitespace and
     * grouping that carry no meaning for what is about to run.
     *
     * @return list<string>
     */
    public function segments(): array
    {
        $segments = [];
        $spoken = str_replace(self::SEPARATORS, "\n", $this->quotesBlanked($this->heredocsBlanked($this->command)));

        foreach (explode("\n", $spoken) as $segment) {
            $segments[] = ltrim(trim($segment), '({ ');
        }

        return $segments;
    }

    /**
     * The directory THIS invocation names for itself — `git -C <dir>` — else the one it inherits.
     */
    private function statedBy(Words $words, string $at): string
    {
        foreach ($words->after(self::GIT_DIRECTORY) as $stated) {
            return $this->resolve($stated, $at);
        }

        return $at;
    }

    /**
     * $where as an absolute path, resolved against the directory it was said in.
     */
    private function resolve(string $where, string $at): string
    {
        if (str_starts_with($where, '/')) {
            return $where;
        }

        return $at === '' ? $where : rtrim($at, '/') . '/' . $where;
    }

    /**
     * The command with the CONTENTS of every quoted string blanked out, the quotes themselves kept so
     * the shape around them survives. A separator inside quotes is not a separator — a shell would never
     * start a command there — and two earlier versions of one gate got that wrong in different costumes:
     * first by matching anywhere, then by treating any `;` or `&&` as a boundary wherever it appeared.
     * Both fired on PROSE ABOUT THE RULE, three times between two sessions, including on the commit
     * messages describing the feature.
     *
     * That is worse than an ordinary false positive. A refusal that fires on writing ABOUT a command
     * teaches people to rephrase until it stops, and an agent that has learned to rephrase past one
     * refusal has learned it about all of them.
     */
    private function quotesBlanked(string $command): string
    {
        $out = '';
        $quote = '';

        foreach (str_split($command) as $char) {
            if ($quote === '' && ($char === '"' || $char === "'")) {
                $quote = $char;
                $out .= $char;

                continue;
            }

            if ($quote !== '' && $char === $quote) {
                $quote = '';
                $out .= $char;

                continue;
            }

            $out .= $quote === '' ? $char : ' ';
        }

        return $out;
    }

    /**
     * The same for a HEREDOC body, which is the other way a command carries prose: a commit message
     * written as `<<'EOF' … EOF` is text ABOUT the work, and the work is often the very rule the gate
     * enforces. The body's lines survive as blanks, so the delimiters and the line count stay put.
     */
    private function heredocsBlanked(string $command): string
    {
        $out = [];
        $until = Option::none();

        foreach (explode("\n", $command) as $line) {
            if ($until->isNone()) {
                $out[] = $line;
                $until = $this->opensHeredoc($line);

                continue;
            }

            $ends = $until->isSomeAnd(static fn (string $delimiter): bool => $delimiter === trim($line));
            $out[] = $ends ? $line : '';
            $until = $ends ? Option::none() : $until;
        }

        return implode("\n", $out);
    }

    /**
     * The delimiter a line opens a heredoc with — absent when it opens none. The quotes and the `-` a
     * heredoc may be spelled with are not part of the word that ends it.
     *
     * @return Option<string>
     */
    private function opensHeredoc(string $line): Option
    {
        $at = strpos($line, self::HEREDOC);

        if ($at === false) {
            return Option::none();
        }

        return Words::of(ltrim(substr($line, $at + strlen(self::HEREDOC)), '-'))
            ->first()
            ->map(static fn (string $word): string => trim($word, '"\''))
            ->filter(static fn (string $word): bool => $word !== '');
    }
}
