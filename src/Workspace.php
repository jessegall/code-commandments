<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Cli\State\SessionNames;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;
use JesseGall\CodeCommandments\Support\Directory;
use JesseGall\CodeCommandments\Support\FileTree;

/**
 * The ONE home of the `.commandments/` layout — every artifact path in the package is built here.
 * Two tiers: the durable tier ({@see shared} — `config.php`, `repent.php`, `.gitignore`, the vue-tsc
 * cache) stays flat, and the session tier ({@see path} — counters, plan markers, the sins checklist)
 * is scoped into `.commandments/sessions/<key>/` so concurrent sessions never overwrite each other.
 * The key is a 5-char hash of the session id: an explicit id (a hook payload, via
 * {@see Hooks\HookEvent::workspace}) → the `CLAUDE_CODE_SESSION_ID` env var (exported to every shell
 * command, so the CLI lands in the SAME folder as the hooks) → `default` for a bare terminal/CI run.
 * {@see prune} sweeps stale session folders on a fresh session start.
 */
final class Workspace
{
    private const string DIR = '.commandments';

    /**
     * The folder holding one directory per session. Public because a session's own PLAN is tracked while
     * the rest of its folder is not, so the ignore rules have to name it.
     */
    public const string SESSIONS = 'sessions';

    /**
     * The durable-tier folder a PROJECT writes its own code into — its custom detectors, sins,
     * skills and packages ({@see custom}). The one folder under `.commandments/` that is neither
     * generated nor session-scoped, so it is the one (besides `config.php`) kept out of the
     * folder's `.gitignore`.
     */
    public const string CUSTOM = 'custom';

    /**
     * The session-folder subdirectory the judge checklists live in. They are generated OUTPUT —
     * large, dated, superseded, read once — and a session folder's other entries are its live
     * STATE, which one folder per run buries. {@see Cli\Judge\Checklist} owns what they mean.
     */
    public const string SINS = 'sins';

    /**
     * The durable-tier folder an ORCHESTRATOR writes its ways of working into — the profiles a team
     * commits and reviews in a diff ({@see Cli\Orchestration\Profiles}). Durable like {@see CUSTOM},
     * and kept out of the folder's `.gitignore` for the same reason: a profile that is not in git did
     * not survive the machine it was written on.
     */
    public const string ORCHESTRATOR = 'orchestrator';

    /**
     * Where the published skills REALLY live, relative to the project root — the one library every
     * agent reads, directly or through a link of its own. `.agents/skills` is the cross-agent
     * location rather than any one assistant's folder, so the agent that reads it natively needs no
     * link at all and the rest get one.
     */
    public const string LIBRARY = '.agents/skills';

    /**
     * The session folder for a run with no session id at all (a human terminal, CI).
     */
    public const string DEFAULT_SESSION = 'default';

    /**
     * How many hex chars of the hashed session id name the folder — short but collision-safe in practice.
     */
    private const int KEY_LENGTH = 5;

    /**
     * How long a sibling session folder may go untouched before {@see prune} sweeps it.
     */
    private const int PRUNE_DAYS = 7;

    public function __construct(
        private readonly string $root,
        private readonly ?string $sessionId = null,
    ) {}

    /**
     * The workspace for $root, resolving the session id when the caller has none: an explicit
     * $sessionId (a hook payload) wins, else the `CLAUDE_CODE_SESSION_ID` env var, else none
     * (→ the {@see DEFAULT_SESSION} folder).
     */
    public static function at(string $root, ?string $sessionId = null): self
    {
        if ($sessionId !== null && $sessionId !== '') {
            return new self($root, $sessionId);
        }

        return new self($root, getenv('CLAUDE_CODE_SESSION_ID') ?: null);
    }

    /**
     * The workspace for the SESSION rather than the directory. A hook resolves its root from the git
     * toplevel of wherever the shell happens to be, which is right for anything belonging to a WORKTREE —
     * a plan is worked in one, and its state should not follow the agent out of it. A conversation is not
     * like that: it is one thing wherever a command was run from, so its journal must be too, or a session
     * that steps into a worktree files half its record there and reads an empty one back at home.
     *
     * The repository's MAIN worktree answers, whatever directory the caller is standing in and whatever
     * the harness stamped: a worktree is its own git toplevel and carries its own `CLAUDE_PROJECT_DIR`,
     * so both the shell and the environment give a lane's own answer inside a lane. $fallback answers
     * outside a repository, where there is nothing to ask.
     */
    public static function ofSession(string $fallback, ?string $sessionId = null): self
    {
        $stated = getenv('CLAUDE_PROJECT_DIR') ?: $fallback;

        // GIT WINS over the stated directory, which is the opposite of everywhere else and is the whole
        // point: `CLAUDE_PROJECT_DIR` says which directory THIS AGENT has, and an agent working in a lane
        // has the lane. Taking it would put the conversation's own record inside a worktree — two boards
        // for one build, each consistent with itself and neither able to see the other.
        return self::at(new GitFiles()->projectRoot($stated) ?? $stated, $sessionId);
    }

    /**
     * The project's hand-written `config.php` — THE durable-tier file (default the cwd). One home,
     * so every consumer (`Config::load`, the config scribes, the disable menu) resolves the same path.
     */
    public static function config(?string $dir = null): string
    {
        return self::at($dir ?? getcwd())->shared('config.php');
    }

    /**
     * The project's `custom/` folder — `<dir>/.commandments/custom` (default the cwd), where a
     * project keeps its OWN detectors, sins, skills and packages. One home, so the scaffolder
     * ({@see Cli\Make\Make}), the loader ({@see Config::load}) and the publisher all resolve it
     * the same way.
     */
    public static function custom(?string $dir = null): string
    {
        return self::at($dir ?? getcwd())->shared(self::CUSTOM);
    }

    /**
     * Every PHP file a project has written into its {@see custom} folder, recursively and in a
     * stable order — the classes {@see Config::load} requires before the config composes, so a
     * `->detector(...)` line can name a class no autoloader knows about.
     *
     * @return list<string>
     */
    public static function customFiles(?string $dir = null): array
    {
        $root = self::custom($dir);

        if (! is_dir($root)) {
            return [];
        }

        $files = [...FileTree::filesIn($root, 'php')];

        sort($files);

        return $files;
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * The skill library's absolute path — `<root>/.agents/skills`. It sits OUTSIDE `.commandments/`
     * because it is not our workspace: it is a directory the agents themselves read, which a project
     * may also keep hand-written skills in. We own only the entries we published there.
     */
    public function library(): string
    {
        return $this->root . '/' . self::LIBRARY;
    }

    /**
     * The folder name for this session — the NAME it was given, else `substr(sha1(id), 0, 5)`, else
     * `default` without an id. A named session's folder IS its name, which is what makes a session
     * something a person can come back to now that it holds its own plan.
     */
    public function sessionKey(): string
    {
        if ($this->sessionId === null) {
            return self::DEFAULT_SESSION;
        }

        foreach (SessionNames::in($this->dir())->nameOf($this->sessionId) as $name) {
            return $name;
        }

        return self::keyFor($this->sessionId);
    }

    /**
     * The folder name $sessionId is filed under. The ONE place the session id becomes a folder, so
     * anything that has to recognise a folder by its session — or a session by its folder — asks here
     * rather than re-deriving a hash that must agree.
     */
    public static function keyFor(string $sessionId): string
    {
        return substr(sha1($sessionId), 0, self::KEY_LENGTH);
    }

    /**
     * The durable tier: `<root>/.commandments`.
     */
    public function dir(): string
    {
        return $this->root . '/' . self::DIR;
    }

    /**
     * This session's folder: `<root>/.commandments/sessions/<key>`.
     */
    public function sessionDir(): string
    {
        return $this->dir() . '/' . self::SESSIONS . '/' . $this->sessionKey();
    }

    /**
     * A session-scoped state file: `<root>/.commandments/sessions/<key>/<file>`.
     */
    public function path(string $file): string
    {
        return $this->sessionDir() . '/' . $file;
    }

    /**
     * The folder this session's judge checklists live in: `<session>/sins`.
     */
    public function checklistDir(): string
    {
        return $this->path(self::SINS);
    }

    /**
     * The live judge worklist — the one an agent works line by line.
     */
    public function checklist(): string
    {
        return $this->checklistDir() . '/sins.md';
    }

    /**
     * One archived run, addressed by the stamp in its name (`--repent=2026-08-29_154514`).
     */
    public function checklistArchive(string $stamp): string
    {
        return $this->checklistDir() . "/sins-{$stamp}.md";
    }

    /**
     * The live worklist WITHOUT the project root, for a display string.
     */
    public function checklistRelative(): string
    {
        return $this->relative(self::SINS . '/sins.md');
    }

    /**
     * A durable-tier file at the `.commandments/` root (`config.php`, `repent.php`, …) — the files
     * that must NOT be session-scoped.
     */
    public function shared(string $file): string
    {
        return $this->dir() . '/' . $file;
    }

    /**
     * The session-scoped path WITHOUT the root — `.commandments/sessions/<key>/<file>` — for display
     * strings and cwd-relative consumers.
     */
    public function relative(string $file): string
    {
        return self::DIR . '/' . self::SESSIONS . '/' . $this->sessionKey() . '/' . $file;
    }

    /**
     * Sweep stale sibling session folders — any `sessions/<key>` dir untouched for $days (default
     * {@see PRUNE_DAYS}), except this session's own. Never touches the durable tier.
     */
    public function prune(int $days = self::PRUNE_DAYS): void
    {
        $cutoff = time() - $days * 86400;

        foreach (glob($this->dir() . '/' . self::SESSIONS . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if ($dir === $this->sessionDir()) {
                continue;
            }

            if ((filemtime($dir) ?: 0) < $cutoff) {
                Directory::delete($dir);
            }
        }
    }
}
