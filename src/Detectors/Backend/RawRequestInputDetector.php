<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Raw, untyped request reads (`->input()`/`->get()`/`->query()`) on a Request
 * from outside the request class. Points at the laravel-idioms skill.
 */
final class RawRequestInputDetector implements Detector
{
    public function skill(): string
    {
        return 'laravel-idioms';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('input', 'get', 'query', 'post')
            ->isUsedOn('Illuminate\\Http\\Request')
            ->get();
    }
}
