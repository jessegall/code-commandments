<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Support\PhpFile;

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

    private const string SESSIONS = 'sessions';

    /**
     * The durable-tier folder a PROJECT writes its own code into — its custom detectors, sins,
     * skills and packages ({@see custom}). The one folder under `.commandments/` that is neither
     * generated nor session-scoped, so it is the one (besides `config.php`) kept out of the
     * folder's `.gitignore`.
     */
    public const string CUSTOM = 'custom';

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
        return new self($root, ($sessionId ?? '') !== '' ? $sessionId : (getenv('CLAUDE_CODE_SESSION_ID') ?: null));
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

        $files = [];

        /**
         * @var \SplFileInfo $file
         */
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        ) as $file) {
            if (PhpFile::is($file)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * The 5-char folder name for this session — `substr(sha1(id), 0, 5)`, or `default` without an id.
     */
    public function sessionKey(): string
    {
        return $this->sessionId === null
            ? self::DEFAULT_SESSION
            : substr(sha1($this->sessionId), 0, self::KEY_LENGTH);
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
                self::deleteDir($dir);
            }
        }
    }

    private static function deleteDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? self::deleteDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
