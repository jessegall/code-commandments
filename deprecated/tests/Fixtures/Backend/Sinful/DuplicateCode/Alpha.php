<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\DuplicateCode;

class Alpha
{
    public function expandRoots(array $roots): array
    {
        $out = [];

        foreach ($roots as $root) {
            if ($root->isValid()) {
                $out[] = $root->expand();
            }
        }

        return $out;
    }

    public function onlyHere(): int
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;

        return $a + $b + $c + $d;
    }
}
