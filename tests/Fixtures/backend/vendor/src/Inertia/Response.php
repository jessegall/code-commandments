<?php

namespace Inertia;

class Response implements \Illuminate\Contracts\Support\Responsable
{
    use \Illuminate\Support\Traits\Macroable;
    protected $component = NULL;
    protected $props = NULL;
    protected $rootView = NULL;
    protected $version = NULL;
    protected $clearHistory = NULL;
    protected $preserveFragment = NULL;
    protected $encryptHistory = NULL;
    protected $viewData = array (
);
    protected ?\Closure $urlResolver = NULL;
    protected array $sharedProps = array (
);
    public function __construct(string $component, array $sharedProps, array $props, string $rootView = 'app', string $version = '', bool $encryptHistory = false, ?\Closure $urlResolver = NULL) {}
    public function with($key, $value = NULL): \Inertia\Response {}
    public function withViewData($key, $value = NULL): \Inertia\Response {}
    public function rootView(string $rootView): \Inertia\Response {}
    public function flash(\BackedEnum|\UnitEnum|array|string $key, mixed $value = NULL): \Inertia\Response {}
    /** @return \Symfony\Component\HttpFoundation\Response */
    public function toResponse($request) {}
    protected function resolveClearHistory(\Illuminate\Http\Request $request): array {}
    protected function resolveEncryptHistory(\Illuminate\Http\Request $request): array {}
    protected function resolveFlashData(\Illuminate\Http\Request $request): array {}
    protected function resolvePreserveFragment(\Illuminate\Http\Request $request): array {}
    protected function getUrl(\Illuminate\Http\Request $request): string {}
    protected function finishUrlWithTrailingSlash(string $url): string {}
}
