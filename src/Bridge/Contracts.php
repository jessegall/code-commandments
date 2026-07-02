<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

/**
 * The bag of published {@see Contract}s a detector is handed ({@see ConsumesContracts})
 * — every fact the engines put on the {@see Bridge} this run, regardless of which
 * engine produced it. Immutable: {@see with} returns a widened copy, so gathering
 * from many providers never mutates a shared instance.
 *
 * A consumer pulls the KIND it cares about with {@see ofType} and reads that value;
 * the bag itself stays generic, knowing nothing of any particular contract.
 */
final class Contracts
{
    /**
     * @param  list<Contract>  $contracts
     */
    public function __construct(private readonly array $contracts = []) {}

    /**
     * A copy carrying these additional contracts.
     */
    public function with(Contract ...$more): self
    {
        return new self([...$this->contracts, ...$more]);
    }

    /**
     * Every published contract of the given kind.
     *
     * @template T of Contract
     * @param  class-string<T>  $kind
     * @return list<T>
     */
    public function ofType(string $kind): array
    {
        return array_values(array_filter(
            $this->contracts,
            static fn (Contract $contract): bool => $contract instanceof $kind,
        ));
    }
}
