<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\DuplicateCode;

class Beta
{
    public function expandNodes(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if ($node->isValid()) {
                $result[] = $node->expand();
            }
        }

        return $result;
    }
}
