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

    /**
     * What a role may state for itself. Read from the role rather than inferred, because a description is
     * matched to decide whether this agent is the one for a job — a guessed one is a guess about when the
     * agent gets used at all.
     */
    private const string DESCRIBED = 'description:';

    /**
     * The model a role runs on. Stated per role because the cost of a role is a property of the WORK it
     * does, and the role is the only place that knows what that work is.
     */
    private const string MODEL = 'model:';

    /**
     * The lines a role uses to declare itself, which are therefore not part of what it SAYS.
     */
    private const array DECLARATIONS = [self::TOOLS, self::DESCRIBED, self::MODEL, self::SKILLS, 'type:'];

    /**
     * Skills the harness loads into the agent before it starts. Worth stating because the body REPLACES
     * the whole system prompt rather than adding to it, so a role gets none of the disciplines this
     * project works under unless it asks for them by name.
     */
    private const string SKILLS = 'skills:';

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
        $tools = $this->declared($brief, self::TOOLS) ?: self::READS_ONLY;
        $about = $this->declared($brief, self::DESCRIBED) ?: $this->firstProse($brief);
        $about = $about ?: "the {$role} role from the `{$this->profile->name}` profile";
        $model = $this->declared($brief, self::MODEL);
        $runsOn = $model === '' ? '' : "\nmodel: {$model}";
        $body = $this->body($brief);
        $skills = $this->declared($brief, self::SKILLS);
        $loads = $skills === '' ? '' : "\nskills: {$skills}";

        return <<<TEXT
            ---
            name: {$role}
            description: {$about}
            tools: {$tools}{$runsOn}{$loads}
            ---

            <!-- GENERATED from `.commandments/orchestrator/profiles/{$this->profile->name}/roles/{$role}.md`
                 by `commandments orchestrate agent {$role}`. Edit the ROLE, not this: the role is the one source
                 and this is rewritten from it. -->

            {$body}
            TEXT;
    }

    /**
     * The role's opening SENTENCE — a fallback for a role that did not describe itself, never the
     * preferred path. Read a line at a time this truncated every role that wrapped (the published
     * `reviewer` ended on the dash where its sentence continued), and read a paragraph at a time it is
     * too long: a description is matched to decide whether this agent is chosen at all, and every
     * custom agent's description is charged against ONE budget, so a verbose one taxes the others.
     */
    private function firstProse(string $brief): string
    {
        $paragraph = [];

        foreach (explode("\n", $brief) as $line) {
            $line = trim($line);

            if ($this->isDeclaration($line) || str_starts_with($line, '#')) {
                continue;
            }

            if ($line === '') {
                if ($paragraph !== []) {
                    break;
                }

                continue;
            }

            $paragraph[] = $line;
        }

        return $this->firstSentence(implode(' ', $paragraph));
    }

    /**
     * Up to the first full stop, the whole thing where it has none.
     */
    private function firstSentence(string $prose): string
    {
        $end = strpos($prose, '. ');

        return $end === false ? $prose : substr($prose, 0, $end + 1);
    }

    /**
     * The role's words, with its declarations taken out. What is in the frontmatter is already in front
     * of the agent, and a system prompt that repeats its own metadata spends the agent's attention
     * telling it what it is instead of what to do.
     */
    private function body(string $brief): string
    {
        $kept = [];

        foreach (explode("\n", $brief) as $line) {
            if (! $this->isDeclaration(trim($line))) {
                $kept[] = $line;
            }
        }

        return trim(implode("\n", $kept));
    }

    /**
     * A line a role uses to declare something about itself rather than to say something.
     */
    private function isDeclaration(string $line): bool
    {
        foreach (self::DECLARATIONS as $declaration) {
            if (str_starts_with($line, $declaration)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the role DECLARED under $key, empty where it declared nothing. One reader for every such line,
     * so a key added later is read the same way as the ones already here.
     */
    private function declared(string $brief, string $key): string
    {
        foreach (explode("\n", $brief) as $line) {
            if (str_starts_with(trim($line), $key)) {
                return trim(substr(trim($line), strlen($key)));
            }
        }

        return '';
    }


}
