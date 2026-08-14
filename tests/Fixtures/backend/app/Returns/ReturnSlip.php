<?php

namespace Shop\Returns;

final class ReturnSlip
{
    public function __construct(
        public readonly string $reference,
        public readonly string $reason,
        public readonly int $itemCount,
    ) {}

    public static function of(ReturnRequest $request): self
    {
        return new self($request->reference(), $request->reason(), $request->itemCount());
    }
}
