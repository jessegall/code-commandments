<?php

namespace Spatie\LaravelData\DataPipes;

interface DataPipe
{
    public function handle(mixed $payload, \Spatie\LaravelData\Support\DataClass $class, array $properties, \Spatie\LaravelData\Support\Creation\CreationContext $creationContext): array;
}
