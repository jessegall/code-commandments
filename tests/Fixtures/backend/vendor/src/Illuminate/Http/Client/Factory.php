<?php

namespace Illuminate\Http\Client;

class Factory
{
    use \Illuminate\Support\Traits\Macroable;
    protected $dispatcher = NULL;
    protected $globalMiddleware = array (
);
    protected $globalOptions = array (
);
    protected $stubCallbacks = NULL;
    protected $recording = false;
    protected $recorded = array (
);
    protected $responseSequences = array (
);
    protected $preventStrayRequests = false;
    protected $allowedStrayRequestUrls = array (
);
    public function __construct(?\Illuminate\Contracts\Events\Dispatcher $dispatcher = NULL) {}
    /** @return $this */
    public function globalMiddleware($middleware) {}
    /** @return $this */
    public function globalRequestMiddleware($middleware) {}
    /** @return $this */
    public function globalResponseMiddleware($middleware) {}
    /** @return $this */
    public function globalOptions($options) {}
    /** @return \GuzzleHttp\Promise\PromiseInterface */
    public static function response($body = NULL, $status = 200, $headers = array (
)) {}
    /** @return \GuzzleHttp\Psr7\Response */
    public static function psr7Response($body = NULL, $status = 200, $headers = array (
)) {}
    /** @return \Illuminate\Http\Client\RequestException */
    public static function failedRequest($body = NULL, $status = 200, $headers = array (
)) {}
    /** @return \Closure(\Illuminate\Http\Client\Request): */
    public static function failedConnection($message = NULL) {}
    /** @return \Illuminate\Http\Client\ResponseSequence */
    public function sequence(array $responses = array (
)) {}
    /** @return $this */
    public function fake($callback = NULL) {}
    /** @return \Illuminate\Http\Client\ResponseSequence */
    public function fakeSequence($url = '*') {}
    /** @return $this */
    public function stubUrl($url, $callback) {}
    /** @return $this */
    public function preventStrayRequests($prevent = true) {}
    /** @return bool */
    public function preventingStrayRequests() {}
    /** @return $this */
    public function allowStrayRequests(?array $only = NULL) {}
    /** @return $this */
    public function record() {}
    /** @return void */
    public function recordRequestResponsePair($request, $response) {}
    /** @return void */
    public function assertSent($callback) {}
    /** @return void */
    public function assertSentInOrder($callbacks) {}
    /** @return void */
    public function assertNotSent($callback) {}
    /** @return void */
    public function assertNothingSent() {}
    /** @return void */
    public function assertSentCount($count) {}
    /** @return void */
    public function assertSequencesAreEmpty() {}
    /** @return \Illuminate\Support\Collection<int, */
    public function recorded($callback = NULL) {}
    /** @return \Illuminate\Http\Client\PendingRequest */
    public function createPendingRequest() {}
    /** @return \Illuminate\Http\Client\PendingRequest */
    protected function newPendingRequest() {}
    /** @return \Illuminate\Contracts\Events\Dispatcher|null */
    public function getDispatcher() {}
    /** @return array */
    public function getGlobalMiddleware() {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    /** @return mixed */
    public function macroCall($method, $parameters) {}
}
