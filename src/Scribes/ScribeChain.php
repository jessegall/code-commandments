<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\DetectorStep as BackendDetectorStep;
use JesseGall\CodeCommandments\Scribes\Frontend\DetectorStep as FrontendDetectorStep;

/**
 * The ordered list of rewriting steps `repent` runs — a Laravel-middleware-style chain
 * the package consumer has FULL control over: {@see prepend} / {@see append} /
 * {@see before} / {@see after} / {@see replace} / {@see remove}, by step name.
 *
 * The DEFAULT order is two phases, because order is correctness here: the IN-PLACE
 * fixers run first (PHP maintenance, then the Vue structure fixers — `<SwitchCase>`,
 * control-flow wrapping), and the component EXTRACTORS run LAST. An extractor creates
 * files and rewrites call sites; running it last, on already-fixed markup, means no
 * later step rewrites the file it just edited (each step re-scans, so every one sees
 * the prior edits).
 *
 * A consumer reorders it from `.commandments/repent.php`:
 *
 *   return fn (ScribeChain $chain): ScribeChain => $chain
 *       ->remove('SwitchCaseDetector')
 *       ->before('DeepNestedDetector', new MyStep());
 */
final class ScribeChain
{
    /** @var list<ScribeStep> */
    private array $steps = [];

    /**
     * The built-in chain: in-place fixers, then extractors last.
     */
    public static function default(): self
    {
        $chain = new self();

        foreach (Catalog::all() as $scribe) {
            $chain->append(new MaintenanceStep($scribe));
        }

        $fixers = [];
        $extractors = [];

        // The project's config (disable / register / configure) shapes the detectors `repent`
        // fixes too, so it agrees with `judge`.
        $configured = Config::load()->apply(Detectors::backend(), Detectors::frontend());

        // Backend (PHP AST) Repentables — all in-place fixers.
        foreach ($configured['backend'] as $detector) {
            if ($detector instanceof Repentable) {
                $fixers[] = new BackendDetectorStep($detector);
            }
        }

        // Frontend (Vue) Repentables — fixers in place, extractors run last.
        foreach ($configured['frontend'] as $detector) {
            if (! $detector instanceof Repentable) {
                continue;
            }

            $step = new FrontendDetectorStep($detector);
            $step->extractsComponents() ? $extractors[] = $step : $fixers[] = $step;
        }

        foreach ([...$fixers, ...$extractors] as $step) {
            $chain->append($step);
        }

        return $chain;
    }

    public function prepend(ScribeStep $step): self
    {
        array_unshift($this->steps, $step);

        return $this;
    }

    public function append(ScribeStep $step): self
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * Insert $step immediately before the named step (appends if the name is absent).
     */
    public function before(string $name, ScribeStep $step): self
    {
        return $this->insert($name, $step, 0);
    }

    /**
     * Insert $step immediately after the named step (appends if the name is absent).
     */
    public function after(string $name, ScribeStep $step): self
    {
        return $this->insert($name, $step, 1);
    }

    public function replace(string $name, ScribeStep $step): self
    {
        foreach ($this->steps as $index => $existing) {
            if ($existing->name() === $name) {
                $this->steps[$index] = $step;
            }
        }

        return $this;
    }

    public function remove(string $name): self
    {
        $this->steps = array_values(array_filter($this->steps, static fn (ScribeStep $step): bool => $step->name() !== $name));

        return $this;
    }

    /**
     * Keep only the steps whose name contains $match (a `--only` scope); null keeps all.
     */
    public function matching(?string $match): self
    {
        if ($match !== null) {
            $this->steps = array_values(array_filter($this->steps, static function (ScribeStep $step) use ($match): bool {
                if (stripos($step->name(), $match) !== false) {
                    return true;
                }

                return $step instanceof DetectorStep && $step->matchesSin($match);
            }));
        }

        return $this;
    }

    /**
     * @return list<ScribeStep>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    private function insert(string $name, ScribeStep $step, int $offset): self
    {
        foreach ($this->steps as $index => $existing) {
            if ($existing->name() === $name) {
                array_splice($this->steps, $index + $offset, 0, [$step]);

                return $this;
            }
        }

        return $this->append($step);
    }
}
