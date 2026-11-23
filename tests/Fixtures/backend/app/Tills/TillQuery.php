<?php

namespace Shop\Tills;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use JesseGall\CodeCommandments\Sins\Backend\FlagArgument;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Narrows a search over tills, one filter at a time, applied when the search runs.
 */
final class TillQuery
{
    /**
     * @var list<Closure(Builder): Builder>
     */
    private array $filters = [];

    public function scope(Closure $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    #[Sinful(FlagArgument::class)]
    public function refunded(bool $refunded = true): self
    {
        return $this->scope(fn (Builder $builder) => match ($refunded) {
            true => $builder->whereHas('refunds'),
            false => $builder->whereDoesntHave('refunds'),
        });
    }

    public function run(Builder $builder): Builder
    {
        return array_reduce($this->filters, static fn (Builder $narrowed, Closure $filter): Builder => $filter($narrowed), $builder);
    }
}
