<?php

namespace Illuminate\Http\Resources\Json;

class ResourceCollection extends \Illuminate\Http\Resources\Json\JsonResource implements \Countable, \IteratorAggregate, \Traversable
{
    use \Illuminate\Http\Resources\CollectsResources;
    public $collects = NULL;
    public $collection = NULL;
    protected $preserveAllQueryParameters = false;
    protected $queryParameters = NULL;
    public function __construct($resource) {}
    public function preserveQuery() {}
    public function withQuery(array $query) {}
    public function count(): int {}
    public function toArray(\Illuminate\Http\Request $request) {}
    public function toResponse($request) {}
    protected function preparePaginatedResponse($request) {}
}
