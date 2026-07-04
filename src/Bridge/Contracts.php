<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

/**
 * Immutable bag of published contracts. Consumers pull by type with {@see ofType};
 * new contracts added via {@see with} return a widened copy, never mutating shared instances.
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
