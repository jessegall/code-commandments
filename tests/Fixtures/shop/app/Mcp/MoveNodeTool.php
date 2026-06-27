<?php

namespace Shop\Mcp;

use JesseGall\CodeCommandments\Detectors\Backend\RequestAccessorRecastDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Laravel\Mcp\Request;

final class MoveNodeTool
{
    public function description(): string
    {
        return 'Move a node up or down within its lane.';
    }

    #[Sinful(RequestAccessorRecastDetector::class)]
    public function handle(Request $request): string
    {
        $nodeId = (string) $request->string('nodeId');
        $direction = (string) $request->string('direction');

        return $nodeId.'->'.$direction;
    }
}
