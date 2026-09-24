<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Codebase;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Support\ClassName;

/**
 * What a fixture is before either engine parses a byte of it: a DIRECTORY, the detectors to prove
 * against it, and the directory's OWN `.commandments/config.php` — because a fixture directory is a
 * project. A rule inert until the project declares something (a layer map, a threshold) fires only
 * once declared, so the fixture declares it exactly as a consumer would and tunes the detectors
 * here, before anything is verified. The file is never scanned as fixture SOURCE: every engine
 * prunes `.`-prefixed directories. A declaration aimed at a detector this fixture omits is ignored,
 * since a fixture legitimately holds a subset of the catalog.
 *
 * @see BackendFixture the PHP engine's fixture
 * @see FrontendFixture the Vue engine's — same mechanism
 * @see ModuleFixture the Python and C# engines' — same mechanism
 */
abstract class EngineFixture implements Fixture
{
    /**
     * @var list<Detector>
     */
    protected readonly array $detectors;

    /**
     * @param  list<Detector>  $detectors
     */
    public function __construct(
        protected readonly string $path,
        array $detectors,
    ) {
        Config::load($path)->tune($detectors);

        $this->detectors = $detectors;
    }

    /**
     * The detectors this fixture holds no `$tag` marker for — `fixed` (the resolution a skill publishes
     * as Good) or `righteous` (a look-alike the rule must leave alone) — by short name. A marker names
     * a detector by its class, its sin's class, or its sin's id, short or whole.
     *
     * @return list<string>
     */
    public function withoutMarker(string $tag): array
    {
        $marked = $this->marked($tag);
        $missing = [];

        foreach ($this->detectors as $detector) {
            if (! array_any(ExampleFiles::namesOf($detector), static fn (string $name): bool => isset($marked[$name]))) {
                $missing[] = ClassName::short($detector::class);
            }
        }

        return $missing;
    }

    /**
     * The worked examples of this fixture, per detector — what a skill publishes as each rule's Bad and
     * Good: what its markers carve out, told instead by the files marked `@example` wherever a rule has any.
     *
     * @return array<class-string<Detector>, list<Example>>
     */
    public function examples(): array
    {
        return ExampleFiles::in($this->path)->over($this->carvedExamples(), $this->detectors);
    }

    /**
     * What is wrong with this fixture's `@example` files, one line each: a Name no rule here answers to, a
     * Bad no file of which the rule flags, a Good the rule flags, or a Good no file of which is marked as the
     * rule's resolution or look-alike. An example file is published as the rule's own code, so each half
     * must be code the fixture proves.
     *
     * @return list<string>
     */
    public function unprovenExamples(): array
    {
        $files = ExampleFiles::in($this->path);
        $answered = [];
        $problems = [];

        foreach ($this->detectors as $detector) {
            $names = ExampleFiles::namesOf($detector);
            $bad = array_map(static fn (MarkedSource $file) => $file->file, $files->bad($detector));
            $good = array_map(static fn (MarkedSource $file) => $file->file, $files->good($detector));
            $rule = ClassName::short($detector::class);
            $sinful = $this->markedFiles('sin', $names);
            $resolved = [...$this->markedFiles('fixed', $names), ...$this->markedFiles('righteous', $names)];
            $answered = [...$answered, ...$names];

            if ($bad !== [] && array_intersect($bad, $sinful) === []) {
                $problems[] = "{$rule}: no Bad example file holds a sin marker for it";
            }

            foreach (array_intersect($good, $sinful) as $file) {
                $problems[] = "{$rule}: the Good example file {$file} holds a sin marker for it";
            }

            if ($good !== [] && array_intersect($good, $resolved) === []) {
                $problems[] = "{$rule}: no Good example file holds a fixed or righteous marker for it";
            }
        }

        foreach (array_diff($files->names(), $answered) as $name) {
            $problems[] = "@example {$name}: no rule in this fixture answers to that name";
        }

        return $problems;
    }

    /**
     * The files holding a `$tag` marker under any of $names.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function markedFiles(string $tag, array $names): array
    {
        $marked = $this->marked($tag);
        $files = [];

        foreach ($names as $name) {
            foreach ($marked[$name] ?? [] as $location) {
                $files[] = substr($location, 0, (int) strrpos($location, ':'));
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * The `file:line` of every `$tag` marker in this fixture — `sin`, `fixed` or `righteous` — by the Name
     * it carries.
     *
     * @return array<string, list<string>>
     */
    abstract protected function marked(string $tag): array;

    /**
     * The worked examples its declaration markers carve out of this fixture, per detector.
     *
     * @return array<class-string<Detector>, list<Example>>
     */
    abstract protected function carvedExamples(): array;

    /**
     * Every recurring rule's findings in this fixture, each by file and line with the group it recurs in —
     * what tells a recurrence example which of the marked groups is the one its Good answers.
     *
     * @return array<class-string<Detector>, array<string, array<int, string>>>
     */
    protected function recurringGroups(): array
    {
        $groups = [];

        foreach ($this->detectors as $detector) {
            if (! $detector instanceof RecurrenceDetector) {
                continue;
            }

            foreach ($detector->find($this->codebase()) as $finding) {
                $group = $finding instanceof Located ? $detector->groupKey($finding, $this->codebase()) : null;

                if ($group !== null) {
                    $groups[$detector::class][$finding->file()][$finding->line()] = $group;
                }
            }
        }

        return $groups;
    }

    abstract protected function codebase(): Codebase;
}
