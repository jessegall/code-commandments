<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Detectors\Detector;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Renders a skill's `SKILL.md` from the catalog — a FIXED 10-section layout, identical
 * for every skill. Sections 1–5 + 10 come from the {@see Skill} (entry descriptor +
 * conceptual prose + related), and the enumerable sections (Rules, Bad → good, When it
 * fires, Checklist) are PROJECTED from the skill's {@see Sin}s. Nothing is authored in
 * the markdown and no count is written down, so the docs can't drift from the detectors.
 */
final class SkillRenderer
{
    private const string REMINDER = '> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.';

    private const string REMINDER_SELF = '> 🔱 **The rule above all — apply it ALWAYS.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This is that rule.';

    private const string FIX_AT_THE_SOURCE = 'backend/fix-at-the-source';

    /**
     * @param  array<class-string<Detector>, array{bad: ?string, good: ?string}>  $examples
     */
    public function render(Skill $skill, array $examples = []): string
    {
        $sins = $this->sinsOf($skill);

        $blocks = [
            $this->frontmatter($skill),
            "# {$skill->title()}",
            $skill->slug === self::FIX_AT_THE_SOURCE ? self::REMINDER_SELF : self::REMINDER,
            $this->blockquote($skill->intro()),
            "## The principle\n\n" . trim($skill->principle()),
            $this->rules($sins),
            $this->badGood($sins, $examples),
            $this->whenItFires($sins),
            $this->checklist($sins),
            $this->related($skill),
        ];

        return implode("\n\n", array_filter($blocks, static fn (string $block): bool => $block !== '')) . "\n";
    }

    private function frontmatter(Skill $skill): string
    {
        return "---\nname: {$this->tail($skill->slug)}\ndescription: {$skill->description()}\n---";
    }

    private function blockquote(string $text): string
    {
        return implode("\n", array_map(static fn (string $line): string => "> {$line}", explode("\n", $text)));
    }

    /**
     * The `## Rules` section — one loud directive per sin.
     *
     * @param  list<Sin>  $sins
     */
    private function rules(array $sins): string
    {
        $rows = [];

        foreach ($sins as $sin) {
            $row = "- {$sin->rule()}";

            if (($suggestion = $sin->suggestion()) !== null) {
                $row .= "\n  _{$suggestion}_";
            }

            $rows[] = $row;
        }

        return $rows === [] ? '' : "## Rules\n\n" . implode("\n", $rows);
    }

    /**
     * The `## Checklist` section — the same rules as scannable checkboxes.
     *
     * @param  list<Sin>  $sins
     */
    private function checklist(array $sins): string
    {
        $rows = array_map(static fn (Sin $sin): string => "- [ ] {$sin->rule()}", $sins);

        return $rows === [] ? '' : "## Checklist\n\n" . implode("\n", $rows);
    }

    /**
     * The `## Bad → good` section — one worked example per sin from the fixture, DEDUPED
     * by bad source so a `#[Sinful]` method carrying several sins shows once, not once
     * per sin.
     *
     * @param  list<Sin>  $sins
     * @param  array<class-string, array{bad: ?string, good: ?string}>  $examples
     */
    private function badGood(array $sins, array $examples): string
    {
        $detectors = $this->detectorsBySin();
        $blocks = [];
        $seen = [];

        foreach ($sins as $sin) {
            $detector = $detectors[$sin::class] ?? null;
            $example = $detector === null ? null : ($examples[$detector::class] ?? null);

            if ($example === null) {
                continue;
            }

            // Dedupe by the bad+good PAIR: a `#[Sinful]` method shared by several sins
            // shows once when the fix is the same, but distinct fixes of the same bad
            // (e.g. `<template v-if>` vs `<SwitchCase>`) each still get shown.
            $key = ($example['bad'] ?? '') . "\0" . ($example['good'] ?? '');

            if (($example['bad'] ?? '') !== '' && isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (($block = $this->example($sin, $example)) !== '') {
                $blocks[] = $block;
            }
        }

        return $blocks === [] ? '' : "## Bad → good\n\n" . implode("\n\n", $blocks);
    }

    /**
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
     * The `## When it fires` section — each sin's symptom and the detector that flags it.
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

        return $rows === [] ? '' : "## When it fires\n\n" . implode("\n", $rows);
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
            /** @var Skill $target */
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
        return array_values(array_filter(Sins::every(), static fn (Sin $sin): bool => $sin->slug() === $skill->slug));
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
