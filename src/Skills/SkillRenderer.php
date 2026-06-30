<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Renders a skill's `SKILL.md` from the catalog — the file is a PROJECTION of the
 * {@see Skill} (entry descriptor + teaching body + related links) and its
 * {@see Sin}s (the "Bad → good" examples and the "When it fires" rows). Nothing is
 * authored in the markdown; regenerating reproduces it, so a fidelity test can lock
 * "generated == committed" and the docs can never drift from the detectors.
 */
final class SkillRenderer
{
    private const string REMINDER = '> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.';

    /**
     * @param  array<class-string<\JesseGall\CodeCommandments\Detectors\Detector>, array{bad: ?string, good: ?string}>  $examples
     *         each detector's fixture-sourced bad/good code (see {@see \JesseGall\CodeCommandments\Testing\FixtureExamples})
     */
    public function render(Skill $skill, array $examples = []): string
    {
        $sins = $this->sinsOf($skill);

        $blocks = [
            $this->frontmatter($skill),
            "# {$skill->title}",
        ];

        if ($this->showsReminder($skill)) {
            $blocks[] = self::REMINDER;
        }

        $blocks[] = $this->blockquote($skill->tagline);
        $blocks[] = $skill->body();

        if (($badGood = $this->badGood($sins, $examples)) !== '') {
            $blocks[] = $badGood;
        }

        $blocks[] = $this->whenItFires($sins);

        if (($related = $this->related($skill)) !== '') {
            $blocks[] = $related;
        }

        return implode("\n\n", $blocks) . "\n";
    }

    private function frontmatter(Skill $skill): string
    {
        $name = $this->tail($skill->slug);

        return "---\nname: {$name}\ndescription: {$skill->description}\n---";
    }

    /**
     * The cardinal-rule reminder rides atop every backend skill — except
     * `fix-at-the-source` itself, which can't tell you to load itself first.
     */
    private function showsReminder(Skill $skill): bool
    {
        return str_starts_with($skill->slug, 'backend/') && $skill->slug !== 'backend/fix-at-the-source';
    }

    private function blockquote(string $text): string
    {
        return implode("\n", array_map(static fn (string $line): string => "> {$line}", explode("\n", $text)));
    }

    /**
     * The "Bad → good" section — one worked example per sin, in catalog order, sourced
     * from the fixture (each sin's `#[Sinful]` bad code and its `#[Righteous]` twin).
     *
     * @param  list<Sin>  $sins
     * @param  array<class-string, array{bad: ?string, good: ?string}>  $examples
     */
    private function badGood(array $sins, array $examples): string
    {
        $detectors = $this->detectorsBySin();
        $blocks = [];

        foreach ($sins as $sin) {
            $detector = $detectors[$sin::class] ?? null;
            $example = $detector === null ? null : ($examples[$detector::class] ?? null);

            if ($example !== null && ($block = $this->example($sin, $example)) !== '') {
                $blocks[] = $block;
            }
        }

        return $blocks === [] ? '' : "## Bad → good\n\n" . implode("\n\n", $blocks);
    }

    /**
     * One sin's bad → good code block, fenced for its engine.
     *
     * @param  array{bad: ?string, good: ?string}  $example
     */
    private function example(Sin $sin, array $example): string
    {
        $parts = [];

        if (($example['bad'] ?? null) !== null) {
            $parts[] = "// Bad\n{$example['bad']}";
        }

        if (($example['good'] ?? null) !== null) {
            $parts[] = "// Good\n{$example['good']}";
        }

        if ($parts === []) {
            return '';
        }

        $fence = str_starts_with($sin->slug(), 'frontend/') ? 'vue' : 'php';

        return "```{$fence}\n" . implode("\n\n", $parts) . "\n```";
    }

    /**
     * The "When it fires" section — each sin's one-line description and the detector
     * that finds it.
     *
     * @param  list<Sin>  $sins
     */
    private function whenItFires(array $sins): string
    {
        $detectors = $this->detectorsBySin();
        $rows = [];

        foreach ($sins as $sin) {
            $detector = $detectors[$sin::class] ?? null;
            $rows[] = $detector === null
                ? "- {$sin->description()}"
                : "- {$sin->description()} — `{$this->shortName($detector::class)}`";
        }

        return "## When it fires\n\n" . implode("\n", $rows);
    }

    /**
     * The "Relationship to the other skills" footer — each related skill linked by a
     * path GENERATED from its current slug (never a stale reference), with its note.
     */
    private function related(Skill $skill): string
    {
        if ($skill->related() === []) {
            return '';
        }

        $rows = [];

        foreach ($skill->related() as $class => $note) {
            /** @var Skill $target */
            $target = new $class;
            $link = $this->relativeLink($skill->slug, $target->slug);
            $rows[] = "- [`{$target->slug}`]({$link}) — {$note}";
        }

        return "## Relationship to the other skills\n\n" . implode("\n", $rows);
    }

    /**
     * A relative link from one skill's `SKILL.md` to another's — `../<name>/SKILL.md`
     * within an engine, `../../<engine>/<name>/SKILL.md` across engines.
     */
    private function relativeLink(string $from, string $to): string
    {
        [$fromEngine] = explode('/', $from, 2);
        [$toEngine, $toName] = explode('/', $to, 2);

        return $fromEngine === $toEngine
            ? "../{$toName}/SKILL.md"
            : "../../{$toEngine}/{$toName}/SKILL.md";
    }

    /**
     * The sins this skill teaches, in catalog order.
     *
     * @return list<Sin>
     */
    private function sinsOf(Skill $skill): array
    {
        return array_values(array_filter(Sins::every(), static fn (Sin $sin): bool => $sin->slug() === $skill->slug));
    }

    /**
     * @return array<class-string<Sin>, \JesseGall\CodeCommandments\Detectors\Detector>  sin class => its detector
     */
    private function detectorsBySin(): array
    {
        $map = [];

        foreach ([...Detectors::all(), ...Detectors::frontend()] as $detector) {
            $map[$detector->sin()::class] = $detector;
        }

        return $map;
    }

    private function shortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    private function tail(string $slug): string
    {
        $parts = explode('/', $slug);

        return end($parts);
    }
}
