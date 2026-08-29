<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\PhpTypes\Option;

/**
 * A way of working, written down and kept in git — the half that does not die with the session, so a
 * second project starts from what the first learned. It is prose because all of it is prose, and it holds
 * no branch, port or lane: those are one build rather than a way of working, and naming them would make
 * it unreusable.
 */
final readonly class Profile
{
    /**
     * How a role's document names the agent type it spawns as.
     */
    private const string TYPE = 'type:';

    /**
     * The documents a profile is made of, each answering one question a brief would otherwise re-state.
     */
    public const array DOCUMENTS = [
        'profile' => 'what this way of working is FOR, and which role writes the branch',
        'behaviour' => 'how the orchestrator works — the judgement no refusal can enforce',
        'restrictions' => 'what it may never do, including what no tool can catch',
        'traps' => 'failures already paid for, each with what it cost',
    ];

    public function __construct(
        public string $name,
        public string $path,
    ) {}

    public function exists(): bool
    {
        return is_dir($this->path);
    }

    /**
     * One of the profile's documents, absent when it was never written — which is a fact about the
     * profile rather than an error, since a project may have nothing to say about restrictions yet.
     *
     * @return Option<string>
     */
    public function document(string $name): Option
    {
        return Option::fromTruthy($this->read($this->path . '/' . $name . '.md'));
    }

    /**
     * Every role this profile declares, by name.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        $roles = [];

        foreach (glob($this->path . '/roles/*.md') ?: [] as $file) {
            $roles[] = basename($file, '.md');
        }

        return $roles;
    }

    /**
     * What a role IS — its brief, its prohibitions, and what it has caught. Read by the orchestrator when
     * it dispatches, so a brief is loaded rather than copied out by hand.
     *
     * @return Option<string>
     */
    public function role(string $name): Option
    {
        return Option::fromTruthy($this->read($this->path . '/roles/' . $name . '.md'));
    }

    /**
     * The agent type a role spawns as, read from its document's `type:` line. This is what lets a refusal
     * survive a restart: `writtenBy` names a role, the profile says which type that role IS, and neither
     * is session state — where a per-session binding to an agent id dies with the agent.
     *
     * @return Option<string>
     */
    public function typeOf(string $role): Option
    {
        foreach ($this->role($role) as $document) {
            foreach (explode("\n", $document) as $line) {
                if (str_starts_with(trim($line), self::TYPE)) {
                    return Option::fromTruthy(trim(substr(trim($line), strlen(self::TYPE))));
                }
            }
        }

        return Option::none();
    }

    private function read(string $path): string
    {
        return is_file($path) ? trim((string) file_get_contents($path)) : '';
    }
}
