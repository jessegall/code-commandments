<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Testing\Example;

use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Sins\Commands;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Renders a skill as a TREE of documents: the `SKILL.md` a reader loads to decide how to write the
 * next line — principle, rules, one worked example, the commands that act on them — beside the
 * `reference/` documents holding what they want only afterwards. All of it a PROJECTION of {@see Skill}
 * and its {@see Sin}s, with no count written down, so the docs cannot drift from the detectors.
 */
final class SkillRenderer
{
    private const string REMINDER = '> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.';

    private const string REMINDER_SELF = '> 🔱 **The rule above all — apply it ALWAYS.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This is that rule.';

    private const string FIX_AT_THE_SOURCE = 'backend/fix-at-the-source';

    /**
     * The generated reference documents, by file stem — stated here so the body's index, the writer
     * and the tests all name them from one place.
     */
    private const string EXAMPLES = 'examples';

    private const string DETECTORS = 'detectors';

    /**
     * The languages the project writes — a skill teaches only what its reader can actually write.
     */
    public function __construct(private readonly Languages $languages = new Languages()) {}

    /**
     * Every file this skill publishes, keyed by its path RELATIVE to the skill directory. The caller
     * decides where that directory is (the package's `skills/`, a consumer's library), so the renderer
     * never has to know a root.
     *
     * @param  array<class-string<Detector>, Example>  $examples
     * @return array<string, string>
     */
    public function documents(Skill $skill, array $examples = []): array
    {
        $sins = $this->sinsOf($skill);
        $worked = $this->workedExamples($sins, $examples);

        $documents = ['SKILL.md' => $this->body($skill, $sins, $worked)];

        if (count($worked) > 1) {
            $documents[self::path(self::EXAMPLES)] = $this->examplesDocument($skill, $worked);
        }

        if ($sins !== []) {
            $documents[self::path(self::DETECTORS)] = $this->detectorsDocument($skill, $sins);
        }

        foreach ($skill->references() as $reference) {
            $documents[self::path($reference->name)] = "# {$reference->title}\n\n" . trim($reference->body) . "\n";
        }

        return $documents;
    }

    /**
     * The `SKILL.md` itself — what a reader has in front of them while writing the line.
     *
     * @param  list<Sin>  $sins
     * @param  list<array{example: Example, sins: list<Sin>}>  $worked
     */
    private function body(Skill $skill, array $sins, array $worked): string
    {
        $blocks = [
            $this->frontmatter($skill),
            "# {$skill->title()}",
            $skill->slug === self::FIX_AT_THE_SOURCE ? self::REMINDER_SELF : self::REMINDER,
            $this->blockquote($skill->intro()),
            "## The principle\n\n" . trim($skill->principle()),
            $this->rules($sins),
            $this->workedExample($worked),
            $this->commands($skill, $sins),
            $this->referenceIndex($skill, $sins, $worked),
            $this->related($skill),
        ];

        return implode("\n\n", array_filter($blocks, static fn (string $block): bool => $block !== '')) . "\n";
    }

    private static function path(string $name): string
    {
        return "reference/{$name}.md";
    }

    /**
     * The rule this half of an example shows, as a divider inside the fence.
     */
    private static function banner(string $half): string
    {
        return '----------[ ' . $half . ' ]----------';
    }

    private function frontmatter(Skill $skill): string
    {
        // The `name` is display-only for some agents (the DIRECTORY name is the invocation) and
        // REQUIRED by others, so it is always written, as the flat id — the same string the
        // directory is named, which is what the spec asks for. It is `[a-z0-9-]` by construction,
        // so it needs no quoting.
        //
        // The `description` is arbitrary prose and MUST be quoted: a plain YAML scalar may not
        // contain `": "`, and a `#` after a space opens a comment. Unquoted, a trigger carrying
        // either is not YAML at all — a lenient reader shrugs, a strict one skips the skill
        // silently. A YAML double-quoted scalar is a superset of a JSON string, so encoding it as
        // JSON is exactly the escape the format wants, for any prose a skill can carry.
        $description = json_encode($skill->trigger(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return "---\nname: {$skill->id()}\ndescription: {$description}\n---";
    }

    private function blockquote(string $text): string
    {
        return implode("\n", array_map(static fn (string $line): string => "> {$line}", explode("\n", $text)));
    }

    /**
     * The `## Rules` section — one directive per sin, written as a checkbox so the ONE list serves
     * both readings a rule gets: the instruction while writing, and the tick-list while reviewing.
     *
     * @param  list<Sin>  $sins
     */
    private function rules(array $sins): string
    {
        $rows = [];

        foreach ($sins as $sin) {
            $row = "- [ ] {$sin->rule()}";

            if (($suggestion = $sin->suggestion()) !== null) {
                $row .= "\n      _{$suggestion}_";
            }

            $rows[] = $row;
        }

        return $rows === [] ? '' : "## Rules\n\n" . implode("\n", $rows);
    }

    /**
     * The `## Commands` section — what a reader can RUN on this skill's rules. A skill that teaches a
     * fix but not the verb that applies it makes every reader rediscover the CLI; the verbs come from
     * {@see Commands}, the one place they are spelled, so this can never disagree with the report that
     * sent the reader here.
     *
     * @param  list<Sin>  $sins
     */
    private function commands(Skill $skill, array $sins): string
    {
        if ($sins === []) {
            return '';
        }

        $detectors = $this->detectorsBySin();
        $names = array_map(static fn (Sin $sin): string => $sin->name(), $sins);
        $repentable = Commands::repentable();
        $scaffoldable = Commands::scaffoldable();

        $rows = [
            '- `' . Commands::judgeSkill($skill->slug) . '` — find every one of these in the codebase.',
            '- `' . Commands::info('<sin>') . '` — what one rule flags, why it is a sin, and the fix. '
                . 'The sins here: ' . self::code($names) . '.',
        ];

        if (($fixable = array_values(array_intersect($names, array_keys($repentable)))) !== []) {
            $rows[] = '- `' . Commands::repent('<sin>') . '` — auto-fix, for ' . self::code($fixable)
                . '. Review it with `--dry-run` first.';
        }

        if (($scaffolds = array_values(array_intersect($names, array_keys($scaffoldable)))) !== []) {
            $rows[] = '- `' . Commands::scaffold('<sin>') . '` — generate the helper the fix reaches for, for '
                . self::code($scaffolds) . '.';
        }

        $bestDesign = false;

        foreach ($sins as $sin) {
            $detector = $detectors[$sin::class] ?? null;
            $bestDesign = $bestDesign || ($detector !== null && Commands::demandsBestDesign($detector));
        }

        $rows[] = '- `' . Commands::report('<Detector>', $bestDesign) . '` — the flagged code is CORRECT '
            . 'under the architecture and the rule is wrong. That is the only thing a report claims: a '
            . 'finding you agree with is yours to fix, however far the fix cascades.';

        return "## Commands\n\n" . implode("\n", $rows);
    }

    /**
     * @param  list<string>  $values
     */
    private static function code(array $values): string
    {
        return implode(', ', array_map(static fn (string $value): string => "`{$value}`", $values));
    }

    /**
     * The `## Reference` section — the documents this skill ships beside its teaching, generated and
     * hand-written alike, each with the question it answers. A pointer a reader cannot tell the
     * purpose of is a pointer they do not follow.
     *
     * @param  list<Sin>  $sins
     * @param  list<array{example: Example, sins: list<Sin>}>  $worked
     */
    private function referenceIndex(Skill $skill, array $sins, array $worked): string
    {
        $rows = [];

        if (count($worked) > 1) {
            $rows[] = '- [Worked examples](' . self::path(self::EXAMPLES) . ') — every rule\'s bad → good, '
                . count($worked) . ' of them.';
        }

        if ($sins !== []) {
            $rows[] = '- [What fires, and why](' . self::path(self::DETECTORS) . ') — the symptom each '
                . 'detector flags, for when you are holding a finding.';
        }

        foreach ($skill->references() as $reference) {
            $rows[] = "- [{$reference->title}](" . self::path($reference->name) . ')';
        }

        return $rows === [] ? '' : "## Reference\n\n" . implode("\n", $rows);
    }

    /**
     * The ONE worked example the body carries — the first rule's, so a reader sees the shape of the
     * sin without scrolling past every other. The rest live in `reference/examples.md`; a body that
     * spends two thirds of itself on before/afters is a body that gets skimmed.
     *
     * @param  list<array{example: Example, sins: list<Sin>}>  $worked
     */
    private function workedExample(array $worked): string
    {
        if ($worked === []) {
            return '';
        }

        $names = self::languagesOf($worked);
        $rendered = $this->example($worked[0]['sins'], $worked[0]['example'], count($names) > 1);

        if ($rendered === '') {
            return '';
        }

        $block = "## Worked example\n\n{$rendered}";

        if (count($worked) > 1) {
            $rest = count($worked) - 1;
            $block .= "\n\nThe other {$rest} — one per rule — are in "
                . '[`' . self::path(self::EXAMPLES) . '`](' . self::path(self::EXAMPLES) . ').';
        }

        return $block;
    }

    /**
     * `reference/examples.md` — every worked example, the body's one included, so the document stands
     * on its own for a reader who opened it directly.
     *
     * @param  list<array{example: Example, sins: list<Sin>}>  $worked
     */
    private function examplesDocument(Skill $skill, array $worked): string
    {
        $names = self::languagesOf($worked);
        $blocks = [];

        foreach ($worked as $group) {
            if (($block = $this->example($group['sins'], $group['example'], count($names) > 1)) !== '') {
                $blocks[] = $block;
            }
        }

        return "# {$skill->title()} — worked examples\n\n"
            . "One bad → good per rule this skill teaches, taken from the fixture that proves the "
            . "detector, so every pair is code that really fires and really passes.\n\n"
            . implode("\n\n", $blocks) . "\n";
    }

    /**
     * `reference/detectors.md` — the symptom-to-detector map. It answers a question a reader has only
     * once they are holding a finding ("what does THIS rule think I did?"), which is why it is not in
     * the body of a document read while writing code.
     *
     * @param  list<Sin>  $sins
     */
    private function detectorsDocument(Skill $skill, array $sins): string
    {
        $detectors = $this->detectorsBySin();
        $rows = [];

        foreach ($sins as $sin) {
            $detector = $detectors[$sin::class] ?? null;
            $rows[] = "- **`{$sin->name()}`** — {$sin->description()}"
                . ($detector === null ? '' : ' — `' . Commands::detectorName($detector) . '`');
        }

        return "# {$skill->title()} — what fires, and why\n\n"
            . "Each row is one rule: the sin's id, the symptom its detector flags, and the detector "
            . "that flags it. The id is what `" . Commands::info('<sin>') . "` takes, and the detector "
            . "name is what `--detector=` takes if the rule turns out to be wrong.\n\n"
            . implode("\n", $rows) . "\n";
    }

    /**
     * Every worked example this skill has, DEDUPED by the bad+good pair so a `#[Sinful]` method
     * carrying several sins shows once, not once per sin — headed by the sin (or sins) it
     * demonstrates, because a reader scrolling a stack of before/afters has to be able to tell which
     * rule each one is about.
     *
     * @param  list<Sin>  $sins
     * @param  array<class-string, Example>  $examples
     * @return list<array{example: Example, sins: list<Sin>}>
     */
    private function workedExamples(array $sins, array $examples): array
    {
        $detectors = $this->detectorsBySin();

        /**
         * @var array<string, array{example: Example, sins: list<Sin>}> $grouped
         */
        $grouped = [];

        foreach ($sins as $sin) {
            $detector = $detectors[$sin::class] ?? null;

            // A rule marked in several LANGUAGES has one example each — a reader working in `.ts`
            // needs the `.ts` one, not a `.vue` one they have to translate. A language the project
            // does not write is not taught, so its example is not printed either.
            foreach ($detector === null ? [] : ($examples[$detector::class] ?? []) as $example) {
                if (! $this->languages->writes($example->language)) {
                    continue;
                }

                // Group by the bad+good PAIR: a `#[Sinful]` method shared by several sins
                // shows once when the fix is the same, but distinct fixes of the same bad
                // (e.g. `<template v-if>` vs `<SwitchCase>`) each still get shown.
                $key = ($example->bad() ?? '') . "\0" . ($example->good() ?? '');

                if ($example->bad() === null || $example->bad() === '') {
                    $key .= "\0" . $sin->name(); // nothing to dedupe on — keep them apart
                }

                $grouped[$key]['example'] = $example;
                $grouped[$key]['sins'][] = $sin;
            }
        }

        return array_values($grouped);
    }

    /**
     * The distinct languages a skill's examples are written in — one for a discipline that lives on
     * a single engine, several for one both engines have.
     *
     * @param  list<array{example: Example, sins: list<Sin>}>  $grouped
     * @return list<string>
     */
    private static function languagesOf(array $grouped): array
    {
        $languages = [];

        foreach ($grouped as $group) {
            $languages[$group['example']->language->value] = true;
        }

        return array_keys($languages);
    }

    /**
     * One before/after, headed by the sin it demonstrates and that sin's one-line symptom, so the
     * example stands on its own instead of relying on its position in the stack.
     *
     * @param  list<Sin>  $sins  every sin this one example demonstrates
     * @param  bool  $nameLanguage  whether this skill teaches more than one language, so each
     *         example has to say which of them it is
     */
    private function example(array $sins, Example $example, bool $nameLanguage = false): string
    {
        $parts = [];

        // A banner rather than a `// Bad` comment: the halves are the two things a reader is here to
        // compare, and a comment line reads as part of the code beneath it.
        if ($example->bad() !== null) {
            $parts[] = self::banner('Bad') . "\n\n{$example->bad()}";
        }

        if ($example->good() !== null) {
            $parts[] = self::banner('Good') . "\n\n{$example->good()}";
        }

        if ($parts === [] || $sins === []) {
            return '';
        }

        $fence = $example->language->value;
        $named = implode(' · ', array_map(static fn (Sin $sin): string => $sin->name(), $sins));
        $heading = '### ' . ($nameLanguage ? "{$named} — in {$example->language->label()}" : $named);

        // One sin: the heading already names it, so the symptom stands alone. Several: each
        // line says which of them it belongs to.
        $symptoms = count($sins) === 1
            ? [$sins[0]->description()]
            : array_map(static fn (Sin $sin): string => "_{$sin->name()}_ — {$sin->description()}", $sins);

        return "{$heading}\n\n" . implode("\n\n", $symptoms) . "\n\n"
            . "```{$fence}\n" . implode("\n\n", $parts) . "\n```";
    }

    /**
     * The `## Related skills` footer — each related skill linked by a path GENERATED from
     * its current slug (never a stale reference), with its note.
     */
    private function related(Skill $skill): string
    {
        if ($skill->related() === []) {
            return '';
        }

        $rows = [];

        foreach ($skill->related() as $class => $note) {
            /**
             * @var Skill $target
             */
            $target = new $class;
            $rows[] = "- [`{$target->slug}`]({$this->relativeLink($skill->slug, $target->slug)}) — {$note}";
        }

        return "## Related skills\n\n" . implode("\n", $rows);
    }

    private function relativeLink(string $from, string $to): string
    {
        [$fromEngine] = explode('/', $from, 2);
        [$toEngine, $toName] = explode('/', $to, 2);

        return $fromEngine === $toEngine ? "../{$toName}/SKILL.md" : "../../{$toEngine}/{$toName}/SKILL.md";
    }

    /**
     * @return list<Sin>
     */
    private function sinsOf(Skill $skill): array
    {
        // The shipped sins AND the project's own ({@see Custom}) — a project skill projects its
        // rules, examples and checklist from its own sins through the very same renderer, so a
        // custom SKILL.md is not a lesser document than a shipped one.
        $sins = [...Sins::every(), ...Custom::sins()];

        return array_values(array_filter($sins, static fn (Sin $sin): bool => $sin->slug() === $skill->slug));
    }

    /**
     * @return array<class-string<Sin>, Detector>
     */
    private function detectorsBySin(): array
    {
        $map = [];

        foreach (Detectors::all() as $detector) {
            $map[$detector->sin()::class] = $detector;
        }

        return $map;
    }
}
