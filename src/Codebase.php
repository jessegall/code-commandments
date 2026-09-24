<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The root type both parse engines share: a parsed body of code to judge — PHP
 * files ({@see Ast\Codebase}) or Vue components ({@see Vue\Codebase}). Each exposes
 * its own fluent selectors (the languages differ), but everything that doesn't parse
 * or detect — the runner, the fixture verifiers, the canon — names a codebase by
 * this base type, so it never has to know which engine it is holding.
 */
interface Codebase
{
    /**
     * The same parse seen through a SUBSET of its files — nothing re-read and nothing re-parsed, so
     * a selector visits those files alone and a rule costs what the files cost rather than what the
     * tree costs. How a scoped run ({@see Cli\Judge\Views}) judges a diff without judging the tree.
     *
     * Every whole-program answer is drawn from the VIEW as well, so this is sound only for a rule
     * that reads no further than the file it judges — {@see Detectors\CrossFileSet} is what knows
     * which rules those are. A path the parse never held simply isn't there.
     */
    public function focusedOn(string ...$paths): static;

    /**
     * How many files the parse holds — what a clean run says it judged.
     */
    public function fileCount(): int;

    /**
     * Build, now and in this process, the whole-program readings a rule would otherwise build on first use — so a
     * run that forks its workers after this hands every worker the readings copy-on-write, instead of each worker
     * building them again. A codebase with nothing costly to prepare does nothing.
     */
    public function warm(): void;
}
