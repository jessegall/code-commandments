<?php

namespace JesseGall\Concurrent;

trait ForwardsCallsToTarget
{
    private function forwardCallTo(mixed $object, string $method, array $parameters): mixed {}
    private function forwardDecoratedCallTo(mixed $object, string $method, array $parameters): mixed {}
}
