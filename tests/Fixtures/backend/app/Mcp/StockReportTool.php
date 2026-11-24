<?php

namespace Shop\Mcp;

use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Mcp\Arguments\ArgumentRule;
use Shop\Mcp\Arguments\ArgumentSchema;

/**
 * Righteous twin for divergent-twin (with StockMoveTool): each tool declares its own arguments in one returned
 * array, so a limit one declares and the other does not is a different input, not a missed step.
 */
final class StockReportTool
{
    /**
     * @return array<string, ArgumentRule>
     */
    #[Righteous(DivergentTwin::class)]
    public function arguments(ArgumentSchema $schema): array
    {
        return [
            'warehouse' => $schema->text()->max(80),
            'page' => $schema->number()->min(1),
            'sku' => $schema->text()->required(),
        ];
    }
}
