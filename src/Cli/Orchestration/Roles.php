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
 * ASSIGNED to a live agent by its id: the explicit stamp, declared by somebody rather than inferred.
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
                . 'whose type was fixed before the role existed.',
            [],
            list: 'one `agent-id<TAB>role` per line.',
            safe: 'the assignments are forgotten and every agent is judged by its type alone',
        );
    }

    /**
     * Give $role to the agent $id, replacing any role it held.
     */
    public function assign(string $id, string $role): void
    {
        $state = $this->file->read();
        $kept = array_values(array_filter(
            $state->items(),
            fn (string $line) => ! str_starts_with($line, $id . "\t"),
        ));

        $this->file->write($state->withItems([...$kept, $id . "\t" . $role]));
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

        foreach ($this->file->read()->items() as $line) {
            $fields = explode("\t", $line, 2);

            if (count($fields) === 2 && $fields[0] === $id) {
                return Option::fromTruthy($fields[1]);
            }
        }

        return Option::none();
    }

    /**
     * Every assignment, as `agent-id => role`.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $roles = [];

        foreach ($this->file->read()->items() as $line) {
            $fields = explode("\t", $line, 2);

            if (count($fields) === 2) {
                $roles[$fields[0]] = $fields[1];
            }
        }

        return $roles;
    }
}
