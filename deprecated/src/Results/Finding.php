<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * A single flattened finding (one sin or one warning) lifted out of the
 * per-file / per-prophet judgment structure so it can be ordered, deferred,
 * absolved, and presented one at a time.
 *
 * Tier and the supersedes list are captured at construction (from the
 * prophet) so the ordering layer stays pure and never has to reinstantiate
 * prophets.
 */
final class Finding
{
    /**
     * @param  'sin'|'warning'  $kind
     * @param  list<string>  $supersedes  prophet classes whose findings this one defers
     * @param  list<string>  $rootCauses  prophet classes that may be this finding's root cause
     */
    public function __construct(
        public readonly string $prophetClass,
        public readonly string $prophetShort,
        public readonly string $filePath,
        public readonly string $relativePath,
        public readonly string $kind,
        public readonly ?int $line,
        public readonly string $message,
        public readonly ?string $snippet,
        public readonly ?string $suggestion,
        public readonly ?string $symbol,
        public readonly ?Advisory $advisory,
        public readonly Tier $tier,
        public readonly array $supersedes,
        public readonly string $fingerprint,
        public readonly bool $autoFixable = false,
        public readonly array $rootCauses = [],
        /** Set by the root-cause resolver when an unresolved in-region cause is found. */
        public readonly ?RootCauseHint $rootCauseHint = null,
        /**
         * True when the resolver ran the root-cause check and found NONE — a
         * confirmed genuine absence (the symptom's own suggestion is correct).
         */
        public readonly bool $rootCauseChecked = false,
    ) {}

    /**
     * A copy carrying the resolved root-cause annotation. `$hint` non-null marks
     * an unresolved in-region cause; `$hint` null + `$checked` true marks a
     * confirmed genuine absence.
     */
    public function withRootCauseHint(?RootCauseHint $hint, bool $checked = true): self
    {
        return new self(
            prophetClass: $this->prophetClass,
            prophetShort: $this->prophetShort,
            filePath: $this->filePath,
            relativePath: $this->relativePath,
            kind: $this->kind,
            line: $this->line,
            message: $this->message,
            snippet: $this->snippet,
            suggestion: $this->suggestion,
            symbol: $this->symbol,
            advisory: $this->advisory,
            tier: $this->tier,
            supersedes: $this->supersedes,
            fingerprint: $this->fingerprint,
            autoFixable: $this->autoFixable,
            rootCauses: $this->rootCauses,
            rootCauseHint: $hint,
            rootCauseChecked: $checked,
        );
    }

    public function isSin(): bool
    {
        return $this->kind === 'sin';
    }

    public function isWarning(): bool
    {
        return $this->kind === 'warning';
    }

    public function location(): string
    {
        return $this->line !== null
            ? $this->relativePath . ':' . $this->line
            : $this->relativePath;
    }
}
