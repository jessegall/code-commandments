<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * The applicability rubric for an advisory (warning-emitting) prophet.
 *
 * Sins are imperative — they carry no rubric because there is no judgment
 * to make. Warnings DO require judgment, and that judgment is exactly what
 * agents get wrong when left implicit. An Advisory forces every advisory
 * prophet to answer three questions in a fixed, unmissable shape:
 *
 *   - applyWhen   — the conditions under which the agent SHOULD act
 *   - leaveWhen   — the conditions under which the code is fine as-is
 *   - whenUnsure  — the default stance when neither clearly holds
 *
 * Built fluently:
 *
 *   Advisory::make()
 *       ->applyWhen('The method has many callers...')
 *       ->leaveWhen('There are one or two callers...')
 *       ->whenUnsure('Leave it.');
 */
final class Advisory
{
    public function __construct(
        public readonly string $applyWhen = '',
        public readonly string $leaveWhen = '',
        public readonly string $whenUnsure = '',
    ) {}

    public static function make(): self
    {
        return new self();
    }

    public function applyWhen(string $condition): self
    {
        return new self($condition, $this->leaveWhen, $this->whenUnsure);
    }

    public function leaveWhen(string $condition): self
    {
        return new self($this->applyWhen, $condition, $this->whenUnsure);
    }

    public function whenUnsure(string $stance): self
    {
        return new self($this->applyWhen, $this->leaveWhen, $stance);
    }

    /**
     * Whether all three facets have been filled in.
     */
    public function isComplete(): bool
    {
        return $this->applyWhen !== ''
            && $this->leaveWhen !== ''
            && $this->whenUnsure !== '';
    }

    /**
     * The rubric as labelled lines, for inline rendering under a finding.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = [];

        if ($this->applyWhen !== '') {
            $lines[] = 'APPLY WHEN:  ' . $this->applyWhen;
        }

        if ($this->leaveWhen !== '') {
            $lines[] = 'LEAVE WHEN:  ' . $this->leaveWhen;
        }

        if ($this->whenUnsure !== '') {
            $lines[] = 'IF UNSURE:   ' . $this->whenUnsure;
        }

        return $lines;
    }
}
