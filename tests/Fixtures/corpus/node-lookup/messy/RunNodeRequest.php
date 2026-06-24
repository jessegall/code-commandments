<?php

namespace App\NodeLookup;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and types the inbound "run this node" payload.
 */
final class RunNodeRequest extends FormRequest
{
    public function __construct(
        private readonly NodeRepository $nodes,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'node_id' => ['required', 'string', new NodeExistsRule($this->nodes)],
        ];
    }

    public function nodeId(): NodeId
    {
        return new NodeId($this->string('node_id'));
    }
}
