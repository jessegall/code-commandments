<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\NearDuplicateFunction;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A till's opening and a till's count are each written by a named constructor that builds its own unsaved row and
 * saves it: every type builds itself, so the shared outline is no function to fold.
 */
final class TillOpening
{
    private bool $saved = false;

    private function __construct(public readonly string $till, public readonly int $floatCents, public readonly string $cashier, public readonly string $openedAt) {}

    #[Righteous(NearDuplicateFunction::class)]
    public static function create(string $till, int $floatCents, string $cashier, string $openedAt): self
    {
        $opening = self::unsaved($till, $floatCents, $cashier, $openedAt);
        $opening->save();

        return $opening;
    }

    public static function unsaved(string $till, int $floatCents, string $cashier, string $openedAt): self
    {
        return new self($till, $floatCents, $cashier, $openedAt);
    }

    public function save(): void
    {
        $this->saved = true;
    }

    public function isSaved(): bool
    {
        return $this->saved;
    }
}

final class TillCount
{
    private bool $saved = false;

    /**
     * @param  array<int, int>  $coins
     */
    private function __construct(public readonly string $till, public readonly array $coins, public readonly bool $blind, public readonly string $countedBy) {}

    /**
     * @param  array<int, int>  $coins
     */
    #[Righteous(NearDuplicateFunction::class)]
    public static function create(string $till, array $coins, bool $blind, string $countedBy): self
    {
        $count = self::unsaved($till, $coins, $blind, $countedBy);
        $count->save();

        return $count;
    }

    /**
     * @param  array<int, int>  $coins
     */
    public static function unsaved(string $till, array $coins, bool $blind, string $countedBy): self
    {
        return new self($till, $coins, $blind, $countedBy);
    }

    public function save(): void
    {
        $this->saved = true;
    }

    public function isSaved(): bool
    {
        return $this->saved;
    }
}
