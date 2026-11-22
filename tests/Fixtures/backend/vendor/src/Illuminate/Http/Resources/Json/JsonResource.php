<?php

namespace Illuminate\Http\Resources\Json;

class JsonResource implements \ArrayAccess, \JsonSerializable, \Illuminate\Contracts\Support\Responsable, \Illuminate\Contracts\Routing\UrlRoutable
{
    use \Illuminate\Http\Resources\ConditionallyLoadsAttributes, \Illuminate\Http\Resources\DelegatesToResource;
    public $resource = NULL;
    public $with = array (
);
    public $additional = array (
);
    public static $wrap = 'data';
    public static bool $forceWrapping = false;
    public function __construct($resource) {}
    /** @return static */
    public static function make(...$parameters) {}
    /** @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection */
    public static function collection($resource) {}
    /** @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection */
    protected static function newCollection($resource) {}
    /** @return array */
    public function resolve($request = NULL) {}
    /** @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable */
    public function toAttributes(\Illuminate\Http\Request $request) {}
    /** @return array */
    public function resolveResourceData(\Illuminate\Http\Request $request) {}
    /** @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable */
    public function toArray(\Illuminate\Http\Request $request) {}
    /** @return string */
    public function toJson($options = 0) {}
    /** @return string */
    public function toPrettyJson(int $options = 0) {}
    /** @return array */
    public function with(\Illuminate\Http\Request $request) {}
    /** @return $this */
    public function additional(array $data) {}
    /** @return int */
    public function jsonOptions() {}
    /** @return void */
    public function withResponse(\Illuminate\Http\Request $request, \Illuminate\Http\JsonResponse $response) {}
    /** @return \Illuminate\Http\Request */
    protected function resolveRequestFromContainer() {}
    /** @return void */
    public static function wrap($value) {}
    /** @return void */
    public static function withoutWrapping() {}
    /** @return \Illuminate\Http\JsonResponse */
    public function response($request = NULL) {}
    /** @return \Illuminate\Http\JsonResponse */
    public function toResponse($request) {}
    public function jsonSerialize(): array {}
    /** @return void */
    public static function flushState() {}
}
