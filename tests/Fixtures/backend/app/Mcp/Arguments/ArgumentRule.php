<?php

namespace Shop\Mcp\Arguments;

/**
 * One argument's type and the limits it is held to.
 */
final readonly class ArgumentRule
{
    /**
     * @param  array<string, int|bool>  $limits
     */
    public function __construct(public string $type, public array $limits = []) {}

    public function max(int $limit): self
    {
        return $this->with('max', $limit);
    }

    public function min(int $limit): self
    {
        return $this->with('min', $limit);
    }

    public function required(): self
    {
        return $this->with('required', true);
    }

    public function nullable(): self
    {
        return $this->with('nullable', true);
    }

    private function with(string $limit, int|bool $value): self
    {
        return new self($this->type, [...$this->limits, $limit => $value]);
    }
}
