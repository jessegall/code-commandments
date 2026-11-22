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
    /** @return $this */
    public function preserveQuery() {}
    /** @return $this */
    public function withQuery(array $query) {}
    public function count(): int {}
    /** @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable */
    public function toArray(\Illuminate\Http\Request $request) {}
    /** @return \Illuminate\Http\JsonResponse */
    public function toResponse($request) {}
    /** @return \Illuminate\Http\JsonResponse */
    protected function preparePaginatedResponse($request) {}
}
