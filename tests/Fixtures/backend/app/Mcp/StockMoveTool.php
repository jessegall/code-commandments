<?php

namespace Shop\Mcp;

use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Mcp\Arguments\ArgumentRule;
use Shop\Mcp\Arguments\ArgumentSchema;

/**
 * Righteous twin for divergent-twin (with StockReportTool): its arguments are its own declaration.
 */
final class StockMoveTool
{
    /**
     * @return array<string, ArgumentRule>
     */
    #[Righteous(DivergentTwin::class)]
    public function writeArguments(ArgumentSchema $schema): array
    {
        return [
            'sku' => $schema->text()->required(),
            'quantity' => $schema->number()->min(1)->max(500),
            'note' => $schema->text()->max(200)->nullable(),
        ];
    }
}
