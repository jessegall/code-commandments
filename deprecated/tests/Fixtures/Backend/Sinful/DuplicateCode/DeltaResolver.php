<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\DuplicateCode;

class DeltaResolver
{
    public function resolve(object $request): array
    {
        $node = $this->nodeById($request->id);

        if ($node->isEmpty()) {
            return [];
        }

        $descriptor = $node->getOrThrow();
        $output = $this->findOutput($descriptor);

        if ($output->isControlHandle()) {
            return [];
        }

        $verdicts = [];

        foreach ($output->ports() as $port) {
            $verdicts[$port->id()] = true;
        }

        return $verdicts;
    }
}
