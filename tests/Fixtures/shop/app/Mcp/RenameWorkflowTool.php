<?php

namespace Shop\Mcp;

use JesseGall\CodeCommandments\Detectors\Backend\RawRequestInputDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Laravel\Mcp\Request;

/**
 * An MCP tool reaching for raw `->get()` on the MCP Request instead of the typed
 * accessor (`->string()`), the same untyped-read sin as on an HTTP Request.
 */
final class RenameWorkflowTool
{
    #[Sinful(RawRequestInputDetector::class)]
    public function handle(Request $request): string
    {
        $id = $request->get('id');
        $name = $request->get('name');

        return $id . ':' . $name;
    }
}
