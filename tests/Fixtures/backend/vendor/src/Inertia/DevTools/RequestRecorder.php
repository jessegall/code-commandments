<?php

namespace Inertia\DevTools;

class RequestRecorder
{
    protected array $shareSources = array (
);
    protected ?\Inertia\DevTools\Collector $collector = NULL;
    public function requestStarted(\Illuminate\Http\Request $request): void {}
    public function sharedPropsResolved(object $middleware, array $shared): void {}
    public function propsShared(array $keys): void {}
    public function pageRendering(string $component, \Inertia\Response $response, array $sharedProps): void {}
    public function propResolved(string $path, mixed $prop): void {}
    public function propRescued(string $path, mixed $prop): void {}
    protected function recordProp(string $path, mixed $prop, bool $rescued = false): void {}
    public function pageRendered(\Illuminate\Http\Request $request, array $page, array $resolvedProps): void {}
    public function respondedWith(\Illuminate\Http\Request $request, \Symfony\Component\HttpFoundation\Response $response): void {}
    protected function recordResponse(\Illuminate\Http\Request $request, \Symfony\Component\HttpFoundation\Response $response): void {}
    protected function setShareSource(array $keys, array $source): void {}
    protected function topLevelSharedKeys(array $sharedProps): array {}
    protected function isInitialHtmlResponse(\Illuminate\Http\Request $request, \Symfony\Component\HttpFoundation\Response $response): bool {}
    protected function injectDevToolsIdTag(\Symfony\Component\HttpFoundation\Response $response, string $id, string $basePath): void {}
    protected function resolveOutgoingParentId(bool $isPrefetch, string $id, ?string $batchId): string {}
    protected function resolveLineage(\Illuminate\Http\Request $request, string $id): array {}
}
