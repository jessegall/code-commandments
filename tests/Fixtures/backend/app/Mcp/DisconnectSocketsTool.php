<?php

namespace Shop\Mcp;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\JsonSchema;

/**
 * Righteous twin for NearDuplicateFunctionDetector (with ConnectSocketsTool): the same
 * `schema()` declaration-hook skeleton on a different MCP tool — by-contract similarity,
 * not duplication to extract.
 */
final class DisconnectSocketsTool extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...parent::schema($schema),
            'wire_id' => $schema->string()->description('The identifier of the wire to remove.')->required(),
            'source_node' => $schema->string()->description('The node the wire left from.')->required(),
            'target_node' => $schema->string()->description('The node the wire entered.')->required(),
            'cascade' => $schema->boolean()->description('Also remove now-dangling wires.')->required(),
            'dry_run' => $schema->boolean()->description('Report the effect without applying it.')->required(),
        ];
    }
}
