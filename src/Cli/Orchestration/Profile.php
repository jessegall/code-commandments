<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Events\Trigger;
use JesseGall\CodeCommandments\Support\File;
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
     * The script a profile runs to stand a lane up, given its path and name.
     */
    public const string SETUP = 'lane.sh';

    /**
     * Where roles sit inside a profile. Stated HERE and nowhere else: a caller that spells `/roles/`
     * itself is a second declaration of the same fact, and two declarations drift the moment one moves.
     */
    private const string ROLE_FOLDER = 'roles';

    /**
     * What this profile turns ON, and how. JSON because it is configuration a TOOL reads, where every
     * other file in a profile is prose an AGENT reads — and the two want opposite things from a format.
     * It lives with the profile rather than in the project config so that a way of working carries its
     * own switches: taking a profile takes what it turns on with it.
     */
    private const string SETTINGS = 'settings.json';

    /**
     * The documents a profile is made of, each answering one question a brief would otherwise re-state.
     */
    public const array DOCUMENTS = [
        'profile' => 'what this way of working is FOR, and which role writes the branch',
        'behaviour' => 'how the orchestrator works — the judgement no refusal can enforce',
        'restrictions' => 'what it may never do, including what no tool can catch',
        'traps' => 'failures already paid for, each with what it cost',
        'routine' => 'what you do EVERY time you come to a stop — the standing habits, nudged not enforced',
    ];

    /**
     * The roles this package ships, and what each one IS. A project writes its own beside them; these
     * three are the ones every orchestrated build turns out to need — somebody who writes to the branch,
     * somebody who reads without touching, and somebody who files what the others found.
     */
    public const array ROLES = [
        'integrator' => 'the sole writer to the shared branch — it merges a committed sha, runs the gates on the branch itself, and answers for what landed',
        'auditor' => 'read-only, on request only — reports violations most-severe first, and a ruling ignored outranks a new finding',
        'secretary' => 'files what workers report into the plan, so the orchestrator only decides — it quotes rather than summarises, and never closes anything',
    ];

    /**
     * The headings a line added to a role's record can file under. Naming the set is what keeps an
     * entry under a heading the file actually has.
     */
    public const array SECTIONS = ['caught', 'behaviour', 'restrictions', 'brief'];

    public function __construct(
        public string $name,
        public string $path,
    ) {}

    /**
     * Add one entry to a document, stamped with when and against what. APPEND is the verb that matters:
     * a role's record grows through a build — what it caught, a correction it made, a rule learned the
     * hard way — and "write it down when it happens, not at the end" only works if adding a line costs
     * one command. A helper that rewrites is used once; one that appends is used twenty times.
     */
    public function append(string $document, string $entry, string $stamp): bool
    {
        $path = $this->pathTo($document);
        $existing = is_file($path) ? rtrim((string) file_get_contents($path)) : '# ' . basename($document);

        @mkdir(dirname($path), 0777, true);

        return File::write($path, $existing . "\n\n- {$stamp} {$entry}\n");
    }

    /**
     * Replace a document outright — the rare case, where the whole thing is being rewritten rather than
     * grown.
     */
    public function set(string $document, string $body): bool
    {
        @mkdir(dirname($this->pathTo($document)), 0777, true);

        return File::write($this->pathTo($document), rtrim($body) . "\n");
    }

    /**
     * Where a DOCUMENT of this profile lives — `traps` at the top, a role beneath. Public because
     * everything that writes into a profile — scaffolding a new one, taking a template — must ask for
     * the layout rather than spell it again beside the write.
     */
    public function pathTo(string $document): string
    {
        return $this->path . '/' . $document . '.md';
    }

    /**
     * Where a ROLE of this profile lives.
     */
    public function pathToRole(string $role): string
    {
        return $this->path . '/' . self::ROLE_FOLDER . '/' . $role . '.md';
    }

    /**
     * The folder roles sit in — what a scaffold makes before it writes any of them.
     */
    public function roleFolder(): string
    {
        return $this->path . '/' . self::ROLE_FOLDER;
    }

    /**
     * Where this profile's switches live.
     */
    public function settingsFile(): string
    {
        return $this->path . '/' . self::SETTINGS;
    }

    /**
     * What this profile says about $feature, absent when it says nothing — which is what an unconfigured
     * feature must be: OFF, and silently, rather than on by a default nobody chose.
     *
     * @return Option<array<string, mixed>>
     */
    public function settings(string $feature): Option
    {
        $file = $this->settingsFile();

        if (! is_file($file)) {
            return Option::none();
        }

        $declared = json_decode((string) file_get_contents($file), true);

        if (! is_array($declared) || ! isset($declared[$feature]) || ! is_array($declared[$feature])) {
            return Option::none();
        }

        return Option::some($declared[$feature]);
    }

    /**
     * Everything this profile turns on, by feature — what `orchestrate settings` prints.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allSettings(): array
    {
        $file = $this->settingsFile();
        $declared = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($declared) ? $declared : [];
    }

    /**
     * Turn $feature on with $with, or off. OFF REMOVES it rather than writing `false`: a feature this
     * profile says nothing about is already off, so two spellings of the same state would be one more
     * thing a reader has to know.
     *
     * @param  array<string, mixed>  $with
     */
    public function turn(string $feature, bool $on, array $with = []): bool
    {
        $declared = $this->allSettings();

        if ($on) {
            $declared[$feature] = $with;
        } else {
            unset($declared[$feature]);
        }

        // An empty MAP, never an empty list: PHP encodes `[]` for both, and a settings file that reads
        // as an array is a different shape from the one every reader of it expects.
        $body = $declared === [] ? '{}' : json_encode($declared, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return File::write($this->settingsFile(), $body . "\n");
    }

    /**
     * The script that stands a lane up — `lane.sh` in the profile, run with the lane's path and name.
     * It is a FILE the project edits rather than steps the package guesses: what a worktree needs to be
     * usable is per-project (a vendor copy, node_modules, a database, a port block), and a package
     * inventing them would be guessing constants from one build.
     *
     * @return Option<string>
     */
    public function setupScript(): Option
    {
        $path = $this->path . '/' . self::SETUP;

        return is_file($path) ? Option::some($path) : Option::none();
    }

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

        foreach (glob($this->roleFolder() . '/*.md') ?: [] as $file) {
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
        return Option::fromTruthy($this->read($this->pathToRole($name)));
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

    /**
     * The triggers THIS way of working arms — loaded only while this profile is in force.
     *
     * A {@see \JesseGall\CodeCommandments\Hooks\Hook} is a fact about the PROJECT and applies to
     * every session; a trigger is a fact about this build. "When a walker reports, dispatch the
     * secretary" means nothing where there is no walker and no board, and a rule firing in a context
     * that cannot satisfy it is how most false positives are born. So the scope IS the concept, and the
     * profile is what carries it.
     *
     * @return list<\JesseGall\CodeCommandments\Cli\Orchestration\Events\Trigger>
     */
    public function triggers(): array
    {
        $armed = [];

        foreach (glob($this->path . '/triggers/*.php') ?: [] as $file) {
            foreach ($this->triggerIn($file) as $trigger) {
                $armed[] = $trigger;
            }
        }

        usort($armed, static fn (object $a, object $b): int => $a::class <=> $b::class);

        return $armed;
    }

    /**
     * The trigger $file declares, if it declares one. Loaded BY FILE the way a project's own detectors
     * are — a profile is not PSR-4 mapped, so no autoloader knows these names.
     *
     * @return \JesseGall\PhpTypes\Option<\JesseGall\CodeCommandments\Cli\Orchestration\Events\Trigger>
     */
    private function triggerIn(string $file): Option
    {
        $before = get_declared_classes();

        require_once $file;

        foreach (array_diff(get_declared_classes(), $before) as $class) {
            if (is_subclass_of($class, Trigger::class) && new \ReflectionClass($class)->isInstantiable()) {
                return Option::some(new $class);
            }
        }

        return Option::none();
    }
}
