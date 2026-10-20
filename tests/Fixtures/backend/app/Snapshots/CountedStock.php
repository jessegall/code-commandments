<?php

namespace Shop\Snapshots;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;

final class CountedStock extends StockSnapshot
{
    /**
     * @var list<string>
     */
    private array $counted = [];

    private int $shrinkage = 0;

    /**
     * The signature is StockSnapshot's, so the array is not this class's to replace with a type: giving it one
     * would leave the parent's contract unanswered, and every other answerer taking a different parameter.
     *
     * @param array<string, mixed> $state
     */
    #[Righteous(ArrayBag::class)]
    public function useState(array $state): void
    {
        $this->counted = $state['counted'];
        $this->shrinkage = $state['shrinkage'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return ['counted' => $this->counted, 'shrinkage' => $this->shrinkage];
    }
}
