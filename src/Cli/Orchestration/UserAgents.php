<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Agents\SkillLink;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * The user's own agents folder, where a role becomes startable from ANY checkout rather than only the
 * project that declares it. Published as a LINK to the profile's generated file, never a copy: a copy is
 * one fact in two places, and the first edit to the role makes them disagree.
 */
final readonly class UserAgents
{
    /**
     * Where the harness reads a user's own agents from.
     */
    private const string FOLDER = '/agents';

    /**
     * The config directory when nothing says otherwise. `CLAUDE_CONFIG_DIR` wins where it is set, since
     * an agent given an isolated world has one and publishing into the real home would defeat it.
     */
    private const string HOME = '/.claude';

    public function __construct(
        private Workspace $workspace,
        private SkillLink $links = new SkillLink,
    ) {}

    /**
     * Where the links live, absent when there is no home to write into.
     *
     * @return Option<string>
     */
    public function folder(): Option
    {
        $configured = getenv('CLAUDE_CONFIG_DIR');

        if (is_string($configured) && $configured !== '') {
            return Option::some($configured . self::FOLDER);
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE');

        return is_string($home) && $home !== ''
            ? Option::some($home . self::HOME . self::FOLDER)
            : Option::none();
    }

    /**
     * What THIS session's links are called. Prefixed with the session, because the folder is shared by
     * every session on the machine while a role name is chosen per project: two sessions publishing a
     * `reviewer` would otherwise land on one name and the second would silently win. That is the same
     * shape as a stash ref shared by nine worktrees — a namespace addressed by a name that looks unique,
     * with no error when two writers meet.
     */
    public function nameFor(string $role): string
    {
        return $this->workspace->sessionKey() . '-' . $role . '.md';
    }

    /**
     * Point the user's folder at $target for $role, answering where the link went.
     *
     * @return Option<string>
     */
    public function publish(string $role, string $target): Option
    {
        foreach ($this->folder() as $folder) {
            $link = $folder . '/' . $this->nameFor($role);

            return $this->links->point($link, $target) ? Option::some($link) : Option::none();
        }

        return Option::none();
    }

    /**
     * Drop every link THIS session published, answering how many went. Scoped by the prefix so a sweep
     * never touches another session's — the folder outlives any one run, and a cleanup that clears the
     * whole thing takes work nobody asked it to.
     */
    public function sweep(): int
    {
        $gone = 0;

        foreach ($this->folder() as $folder) {
            foreach (glob($folder . '/' . $this->workspace->sessionKey() . '-*.md') ?: [] as $link) {
                $gone += unlink($link) ? 1 : 0;
            }
        }

        return $gone;
    }
}
