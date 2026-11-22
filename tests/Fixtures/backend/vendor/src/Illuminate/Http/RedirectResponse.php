<?php

namespace Illuminate\Http;

class RedirectResponse extends \Symfony\Component\HttpFoundation\RedirectResponse
{
    use \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Http\ResponseTrait, \Illuminate\Support\Traits\Macroable;
    protected $request = NULL;
    protected $session = NULL;
    /** @return $this */
    public function with($key, $value = NULL) {}
    /** @return $this */
    public function withCookies(array $cookies) {}
    /** @return $this */
    public function withInput(?array $input = NULL) {}
    /** @return array */
    protected function removeFilesFromInput(array $input) {}
    /** @return $this */
    public function onlyInput() {}
    /** @return $this */
    public function exceptInput() {}
    /** @return $this */
    public function withErrors($provider, $key = 'default') {}
    /** @return \Illuminate\Support\MessageBag */
    protected function parseErrors($provider) {}
    /** @return $this */
    public function withFragment($fragment) {}
    /** @return $this */
    public function withoutFragment() {}
    public function enforceSameOrigin(string $fallback, bool $validateScheme = true, bool $validatePort = true): static {}
    /** @return null */
    public function getOriginalContent() {}
    /** @return \Illuminate\Http\Request|null */
    public function getRequest() {}
    /** @return $this */
    public function setRequest(\Illuminate\Http\Request $request) {}
    /** @return \Illuminate\Session\Store|null */
    public function getSession() {}
    /** @return $this */
    public function setSession(\Illuminate\Session\Store $session) {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    /** @return mixed */
    public function macroCall($method, $parameters) {}
}
