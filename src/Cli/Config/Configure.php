<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Config;

use JesseGall\CodeCommandments\Sins\Catalog;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Skill;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
/**
 * `commandments disable <sin|skill>` / `commandments enable <sin|skill>` — toggle a rule in the
 * project's `.commandments/config.php` without hand-editing it. The argument is a sin id OR a
 * skill slug (the `--sin=` / `--skill=` keys, matched leniently); it's resolved to its {@see Sin}
 * or {@see Skill} class and added to / removed from the config's `$config->disable(...)` call via
 * the AST ({@see ConfigFile}) — disabling a skill silences every detector it teaches the fix for.
 * One handler for both verbs — it reads which from {@see Input::command}.
 */
final class Configure implements Command
{
    public function names(): array
    {
        return ['disable', 'enable'];
    }

    public function help(): Help
    {
        return Help::of("Toggle a rule in the project's .commandments/config.php — edited through the AST, so the file stays valid PHP and your own lines are untouched.")
            ->form('disable <sin|skill>', 'turn a rule off — a skill silences every detector it teaches the fix for')
            ->form('enable <sin|skill>', 'turn it back on')
            ->note('The argument is a sin id OR a skill slug (the --sin= / --skill= keys), matched leniently. Run '
                . '`commandments judge --list` to see them all.');
    }

    public function run(Input $input): int
    {
        $action = $input->command();
        $named = $input->firstArgument();

        if ($named->isNone()) {
            return HelpScreen::usage($this, "Name the sin or skill to {$action}.");
        }

        $query = $named->unwrap();
        $matches = $this->matching($query);

        if ($matches === []) {
            fwrite(STDERR, "No sin or skill matches \"{$query}\". Run `commandments judge --list` to see them.\n");

            return 2;
        }

        if (count($matches) > 1) {
            $names = implode(', ', array_map(static fn (Sin|Skill $match): string => $match instanceof Skill ? $match->slug : $match->name(), $matches));
            fwrite(STDERR, "\"{$query}\" matches more than one: {$names}. Name the one you mean.\n");

            return 2;
        }

        $target = $matches[0];

        $file = ConfigFile::inProject();
        $changed = $action === 'enable' ? $file->enable($target::class) : $file->disable($target::class);

        $this->report($action, $target, $changed);

        return 0;
    }

    private function report(string $action, Sin|Skill $target, bool $changed): void
    {
        $label = $target instanceof Skill ? "skill `{$target->slug}`" : "`{$target->name()}`";
        $verb = $action === 'enable' ? 'enabled' : 'disabled';
        $noun = $action === 'enable' ? 'was not disabled' : 'already disabled';

        $message = $changed
            ? "\033[32m✓ {$verb} {$label}.\033[0m\n"
            : "\033[2m{$label} {$noun} — nothing to do.\033[0m\n";

        fwrite(STDOUT, $message);
    }

    /**
     * The sin or skill $query names — exactly, or leniently when nothing is named exactly. More than one
     * when a lenient name is shared, as a skill of one discipline is across engines.
     *
     * @return list<Sin|Skill>
     */
    private function matching(string $query): array
    {
        $exact = [
            ...array_filter(Catalog::every(), static fn (Sin $sin): bool => $sin->name() === $query),
            ...array_filter(Skills::all(), static fn (Skill $skill): bool => $skill->slug === $query),
        ];

        if ($exact !== []) {
            return array_values($exact);
        }

        return array_values([
            ...array_filter(Catalog::every(), static fn (Sin $sin): bool => $sin->matches($query)),
            ...array_filter(Skills::all(), static fn (Skill $skill): bool => $skill->matches($query)),
        ]);
    }
}
