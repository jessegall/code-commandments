<?php

namespace Shop\Mcp;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\JsonSchema;

/**
 * Righteous twin for NearDuplicateFunctionDetector (with DisconnectSocketsTool):
 * `schema()` is an MCP `Tool`'s per-subclass DECLARATION HOOK — every tool states its
 * own input fields with the same `$schema->...()` skeleton by contract, so the
 * structural twin must NOT be flagged as a near-duplicate, exactly like `rules()`.
 */
final class ConnectSocketsTool extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...parent::schema($schema),
            'source_node' => $schema->string()->description('The node the wire leaves from.')->required(),
            'source_socket' => $schema->string()->description('The output socket on the source.')->required(),
            'target_node' => $schema->string()->description('The node the wire enters.')->required(),
            'target_socket' => $schema->string()->description('The input socket on the target.')->required(),
            'replace_existing' => $schema->boolean()->description('Replace a wire already on the target.')->required(),
        ];
    }
}
