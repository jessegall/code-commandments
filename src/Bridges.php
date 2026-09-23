<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Py\TypeBridge;
use JesseGall\CodeCommandments\Support\HeldTool;

/**
 * The bridges an engine reads another language's compiler through, each sought only when a scan first needs
 * it: Roslyn, which parses C#, and mypy, which types Python.
 */
final readonly class Bridges
{
    /**
     * @param  HeldTool<Bridge>  $roslyn
     * @param  HeldTool<TypeBridge>  $mypy
     */
    public function __construct(
        public HeldTool $roslyn = new HeldTool(Bridge::class),
        public HeldTool $mypy = new HeldTool(TypeBridge::class),
    ) {}
}
