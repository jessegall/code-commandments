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
    public static function make(...$parameters) {}
    public static function collection($resource) {}
    protected static function newCollection($resource) {}
    public function resolve($request = NULL) {}
    public function toAttributes(\Illuminate\Http\Request $request) {}
    public function resolveResourceData(\Illuminate\Http\Request $request) {}
    public function toArray(\Illuminate\Http\Request $request) {}
    public function toJson($options = 0) {}
    public function toPrettyJson(int $options = 0) {}
    public function with(\Illuminate\Http\Request $request) {}
    public function additional(array $data) {}
    public function jsonOptions() {}
    public function withResponse(\Illuminate\Http\Request $request, \Illuminate\Http\JsonResponse $response) {}
    protected function resolveRequestFromContainer() {}
    public static function wrap($value) {}
    public static function withoutWrapping() {}
    public function response($request = NULL) {}
    public function toResponse($request) {}
    public function jsonSerialize(): array {}
    public static function flushState() {}
}
