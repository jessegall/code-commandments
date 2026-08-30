<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Support\File;
use JesseGall\PhpTypes\Option;

/**
 * A profile role, rendered as a type the harness can start. A role is a BRIEF and a type is what a
 * dispatch names; until they are connected a binding can name an agent nobody can start, which is how an
 * evening of dispatches went to `general-purpose` agents standing in under another name. GENERATED, never
 * copied: a hand-written type holds the role's text a second time and drifts the day one is sharpened.
 */
final readonly class AgentType
{
    private const string FOLDER = '.claude/agents';

    /**
     * What a role may do unless it says otherwise. Read-only, because a role that never says is one
     * nobody has thought about — and the roles this package ships all forbid editing in their own words.
     * A restriction the TYPE enforces cannot be talked out of the way prose can.
     */
    private const string READS_ONLY = 'Bash, Read, Grep, Glob';

    /**
     * The line a role uses to widen that, when it genuinely writes.
     */
    private const string TOOLS = 'tools:';

    public function __construct(
        private string $root,
        private Profile $profile,
    ) {}

    public function pathFor(string $role): string
    {
        return $this->root . '/' . self::FOLDER . '/' . $role . '.md';
    }

    /**
     * Write the type for $role, answering where it went. Absent when the profile has no such role: a
     * type generated from nothing would be an agent that can be started and has nothing to do.
     *
     * @return Option<string>
     */
    public function write(string $role): Option
    {
        foreach ($this->profile->role($role) as $brief) {
            $path = $this->pathFor($role);

            return File::write($path, $this->render($role, $brief)) ? Option::some($path) : Option::none();
        }

        return Option::none();
    }

    /**
     * The type as the harness reads it: frontmatter, then the role's own words as the body. The
     * generated stamp is for a human — a file nobody can tell is derived is one somebody edits.
     */
    private function render(string $role, string $brief): string
    {
        $tools = $this->toolsFor($brief);
        $about = $this->firstProse($brief) ?: "the {$role} role from the `{$this->profile->name}` profile";

        return <<<TEXT
            ---
            name: {$role}
            description: {$about}
            tools: {$tools}
            ---

            <!-- GENERATED from `.commandments/orchestrator/profiles/{$this->profile->name}/roles/{$role}.md`
                 by `commandments agent add {$role}`. Edit the ROLE, not this: the role is the one source
                 and this is rewritten from it. -->

            {$brief}
            TEXT;
    }

    /**
     * The role's own first sentence, which is what a listing shows. Read from the brief rather than
     * restated, so a role rewritten reads differently everywhere at once.
     */
    private function firstProse(string $brief): string
    {
        foreach (explode("\n", $brief) as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, '#') && ! str_starts_with($line, 'type:') && ! str_starts_with($line, 'tools:')) {
                return $line;
            }
        }

        return '';
    }

    /**
     * What this role may reach for — its own `tools:` line, else read-only. A role whose restrictions say
     * it never edits and never commits should not be ABLE to: those were instructions an agent broke
     * tonight, and a type enforces what prose only requests.
     */
    private function toolsFor(string $brief): string
    {
        foreach (explode("\n", $brief) as $line) {
            if (str_starts_with(trim($line), self::TOOLS)) {
                return trim(substr(trim($line), strlen(self::TOOLS)));
            }
        }

        return self::READS_ONLY;
    }
}
