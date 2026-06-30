<?php

namespace Shop\Mcp;

use JesseGall\CodeCommandments\Sins\Backend\RequestAccessorRecast;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Laravel\Mcp\Request;

final class MoveNodeTool
{
    public function description(): string
    {
        return 'Move a node up or down within its lane.';
    }

    #[Sinful(RequestAccessorRecast::class)]
    public function handle(Request $request): string
    {
        $nodeId = (string) $request->string('nodeId');
        $direction = (string) $request->string('direction');

        return $nodeId.'->'.$direction;
    }

    #[Righteous(RequestAccessorRecast::class)]
    public function handleNamed(MoveNodeRequest $request): string
    {
        $nodeId = $request->nodeId();
        $direction = $request->direction();

        return $nodeId.'->'.$direction;
    }
}
