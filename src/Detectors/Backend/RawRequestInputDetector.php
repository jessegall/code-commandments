<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Raw, untyped request reads (`->input()`/`->get()`/`->query()`/`->post()`) on a
 * request from outside the request class — use a typed accessor instead
 * (`->string()`, `->integer()`, …). Covers every Laravel request flavour: the HTTP
 * `Request`, a `FormRequest`, and the MCP `Request` (whose tools reach for raw
 * `->get()` despite having `->string()`/`->boolean()`/`->array()`). Points at the
 * laravel-idioms skill.
 */
final class RawRequestInputDetector implements Detector
{
    /**
     * Laravel request base types whose untyped reads are the smell.
     */
    private const array REQUEST_TYPES = [
        'Illuminate\\Http\\Request',
        'Illuminate\\Foundation\\Http\\FormRequest',
        'Laravel\\Mcp\\Request',
    ];

    public function skill(): string
    {
        return 'laravel-idioms';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('input', 'get', 'query', 'post')
            ->isUsedOn(...self::REQUEST_TYPES)
            ->get();
    }
}
