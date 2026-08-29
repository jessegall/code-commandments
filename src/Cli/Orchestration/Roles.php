<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * Which agent is which. An agent's TYPE answers it and is fixed at spawn, so an agent already alive
 * cannot become the reviewer — and the ones worth a role are exactly the ones you cannot afford to
 * respawn, since their value is the build history a fresh spawn discards. A role may therefore be
 * ASSIGNED to a live agent by its id, and it holds exactly ONE: an id dies with its session, so a long
 * build rebinds a role several times, and a second line for it would leave a corpse and the live agent
 * equally entitled to answer for the role ({@see Binding}).
 */
final class Roles
{
    public function __construct(private readonly StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new StateFile($workspace->path('.roles'), self::legend()));
    }

    public static function legend(): Legend
    {
        return new Legend(
            'Which agent holds which role (`commandments build assign <role> --to=<agent-id>`). An agent '
                . 'spawned under its own type needs no line here; this is for an agent already alive, '
                . 'whose type was fixed before the role existed. Nothing here says an agent is still '
                . 'reachable — it says who was named for the role, which is a different fact.',
            [],
            list: 'one `agent-id<TAB>role` per line, and ONE line per role — binding a role again '
                . 'replaces its line rather than adding one, since two lines for a role could not say '
                . 'which agent is current.',
            safe: 'the assignments are forgotten and every agent is judged by its type alone',
        );
    }

    /**
     * Give $role to the agent $id, replacing whoever held the role and any role $id itself held.
     */
    public function assign(string $id, string $role): void
    {
        $state = $this->file->read();
        $kept = [];

        foreach ($this->all() as $binding) {
            if ($binding->isFor($role) || $binding->isBy($id)) {
                continue;
            }

            $kept[] = $binding->toLine();
        }

        $this->file->write($state->withItems([...$kept, new Binding($id, $role)->toLine()]));
    }

    /**
     * Every current binding, one per role, in the order the roles were first written. A file left by the
     * version that accumulated may still carry several lines for one role, and the LAST of them is the
     * one that was meant — so the earlier ones read as what they are: superseded.
     *
     * @return list<Binding>
     */
    public function all(): array
    {
        $current = [];

        foreach ($this->file->read()->items() as $line) {
            foreach (Binding::fromLine($line) as $binding) {
                $current[$binding->role] = $binding;
            }
        }

        return array_values($current);
    }

    /**
     * The agent holding $role, absent when nobody has been named for it. It is ONE answer by
     * construction — which is the entire reason a binding replaces rather than accumulates.
     *
     * @return Option<string>
     */
    public function agentFor(string $role): Option
    {
        foreach ($this->all() as $binding) {
            if ($binding->isFor($role)) {
                return Option::some($binding->agent);
            }
        }

        return Option::none();
    }

    /**
     * The role assigned to $id, absent when nobody assigned one.
     *
     * @return Option<string>
     */
    public function of(string $id): Option
    {
        if ($id === '') {
            return Option::none();
        }

        foreach ($this->all() as $binding) {
            if ($binding->isBy($id)) {
                return Option::some($binding->role);
            }
        }

        return Option::none();
    }
}
