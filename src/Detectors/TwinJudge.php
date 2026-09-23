<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Ast\Support\ReachedUnit;

/**
 * What one engine answers for {@see DivergentTwins} — the questions whose answers live in that language:
 * which resources are subjects rather than verbs, whether two units answer one contract, whether their
 * results could be the same thing, and who calls a unit.
 */
interface TwinJudge
{
    /**
     * Is $resource a type — the subject two units work on — rather than a verb, a step one takes?
     */
    public function isType(string $resource): bool;

    /**
     * Are these the same method of one declared contract, implemented by different classes — meant to
     * differ, the one doing less answering for a different case?
     */
    public function arePolymorphicSiblings(ReachedUnit $poorer, ReachedUnit $richer): bool;

    /**
     * Could these two not be producing the same thing — both declare a result, and the results cannot hold
     * one value?
     */
    public function resultsAreIncomparable(ReachedUnit $poorer, ReachedUnit $richer): bool;

    /**
     * The units that call $unit, by the key units are known by.
     *
     * @return list<string>
     */
    public function callersOf(ReachedUnit $unit): array;
}
