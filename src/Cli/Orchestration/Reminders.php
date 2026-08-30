<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * Where a hook's words come from — the profile in force where it has a file for them, this package
 * otherwise. The shipped file is the ONLY copy either way: no class keeps a string of its own "as a
 * fallback", since a second copy is one that drifts and the one that drifts is the one nobody reads.
 */
final readonly class Reminders
{
    public function __construct(
        private Workspace $workspace,
        private Templates $shipped,
    ) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self($workspace, Templates::shipped());
    }

    /**
     * What $name says with $holes filled, absent where the profile in force has EMPTIED it — which is how
     * a project switches a nudge off.
     *
     * An absent file is not that. A session that is not orchestrating never had an opinion about these
     * words, and a profile written before a reminder existed cannot have deleted it, so reading silence
     * into either gap mutes a nudge nobody switched off — and every profile is older than the next
     * reminder we ship. Both fall through to the package.
     *
     * @return Option<string>
     */
    public function say(string $name, Holes $holes): Option
    {
        foreach (Profiles::inForce($this->workspace) as $profile) {
            foreach ($profile->reminder($name, $holes) as $said) {
                return Option::fromTruthy($said);
            }
        }

        return $this->shipped->reminder($name, $holes);
    }

    /**
     * The same words, for a caller that cannot do without them — a dispatch BRIEF, which is not advice to
     * an agent but the whole of its instructions.
     *
     * Emptying a nudge's file means "do not say this", and that reads correctly right up to the file that
     * is somebody's entire prompt: there it would not stop the dispatch, it would send an agent out with
     * nothing to do. So a brief always resolves — the profile's words when it has them, the shipped ones
     * when it has not — and the off switch for a dispatch is the one that actually stops it:
     * `orchestrate off <trigger>`.
     */
    public function insist(string $name, Holes $holes): string
    {
        return $this->say($name, $holes)->unwrapOrElse(fn (): string => $this->shipped->reminder($name, $holes)->unwrapOr(''));
    }
}
