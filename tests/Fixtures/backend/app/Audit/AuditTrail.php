<?php

namespace Shop\Audit;

final class AuditTrail
{
    public function __construct(private readonly string $channel) {}

    public function record(string $event): string
    {
        return $this->channel . ':' . $event;
    }
}
