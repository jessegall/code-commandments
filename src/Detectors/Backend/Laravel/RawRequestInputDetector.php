<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\RawRequestInput;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An untyped read off a Laravel request — `->input(...)`, `->get(...)`, `->query(...)`,
 * `->post(...)` on a `Request`/`FormRequest`/MCP request. The value arrives as `mixed`; read it
 * through a typed accessor (a form-request method, a DTO) so the type is honest. Points at
 * laravel-idioms. A request reading its OWN input is exempt.
 */
final class RawRequestInputDetector implements Detector
{
    public function sin(): Sin
    {
        return new RawRequestInput();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('input', 'get', 'query', 'post')
            ->isUsedOn(...LaravelNode::REQUEST_TYPES)
            ->get();
    }
}
