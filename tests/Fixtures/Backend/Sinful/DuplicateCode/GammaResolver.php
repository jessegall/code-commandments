<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\DuplicateCode;

class GammaResolver
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

        $candidates = [];

        foreach ($output->ports() as $port) {
            $candidates[] = $port->name();
        }

        return $candidates;
    }
}
