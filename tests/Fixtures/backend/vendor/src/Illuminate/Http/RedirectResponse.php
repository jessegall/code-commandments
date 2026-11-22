<?php

namespace Illuminate\Http;

class RedirectResponse extends \Symfony\Component\HttpFoundation\RedirectResponse
{
    use \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Http\ResponseTrait, \Illuminate\Support\Traits\Macroable;
    protected $request = NULL;
    protected $session = NULL;
    public function with($key, $value = NULL) {}
    public function withCookies(array $cookies) {}
    public function withInput(?array $input = NULL) {}
    protected function removeFilesFromInput(array $input) {}
    public function onlyInput() {}
    public function exceptInput() {}
    public function withErrors($provider, $key = 'default') {}
    protected function parseErrors($provider) {}
    public function withFragment($fragment) {}
    public function withoutFragment() {}
    public function enforceSameOrigin(string $fallback, bool $validateScheme = true, bool $validatePort = true): static {}
    public function getOriginalContent() {}
    public function getRequest() {}
    public function setRequest(\Illuminate\Http\Request $request) {}
    public function getSession() {}
    public function setSession(\Illuminate\Session\Store $session) {}
    public function __call($method, $parameters) {}
    public function macroCall($method, $parameters) {}
}
