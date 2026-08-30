<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * One command a shell would actually run, and the DIRECTORY it would run in. The pair is the point: a
 * gate judging what a command does to a checkout has to ask the checkout the command REACHES, and an
 * agent whose session is pinned to the repository root reaches its lane with a leading `cd` inside the
 * command string. Reading the hook's own directory instead made a lane pulling the shared branch INTO
 * itself look like a merge into the shared branch — four reports of one refusal that was
 * correct-looking, which is worse than one that reads as broken.
 */
final readonly class Invocation
{
    public function __construct(
        public string $line,
        public string $in,
    ) {}

    /**
     * Does this RUN $command — the words at its head, never the words anywhere in it. A command that
     * merely names another is a different act, and the gate that could not tell them apart refused its
     * own commit message.
     */
    public function runs(string $command): bool
    {
        return str_starts_with($this->line, $command);
    }

    /**
     * Is this `git <verb>`, however the invocation is spelled? The verb is looked for among the words
     * rather than at a fixed position, since `git -C <dir> merge` and `git merge` are one act.
     */
    public function isGit(string $verb): bool
    {
        $words = $this->words();

        return $words->opens('git') && $words->has($verb);
    }

    public function words(): Words
    {
        return Words::of($this->line);
    }
}
