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
    public function globalMiddleware($middleware) {}
    public function globalRequestMiddleware($middleware) {}
    public function globalResponseMiddleware($middleware) {}
    public function globalOptions($options) {}
    public static function response($body = NULL, $status = 200, $headers = array (
)) {}
    public static function psr7Response($body = NULL, $status = 200, $headers = array (
)) {}
    public static function failedRequest($body = NULL, $status = 200, $headers = array (
)) {}
    public static function failedConnection($message = NULL) {}
    public function sequence(array $responses = array (
)) {}
    public function fake($callback = NULL) {}
    public function fakeSequence($url = '*') {}
    public function stubUrl($url, $callback) {}
    public function preventStrayRequests($prevent = true) {}
    public function preventingStrayRequests() {}
    public function allowStrayRequests(?array $only = NULL) {}
    public function record() {}
    public function recordRequestResponsePair($request, $response) {}
    public function assertSent($callback) {}
    public function assertSentInOrder($callbacks) {}
    public function assertNotSent($callback) {}
    public function assertNothingSent() {}
    public function assertSentCount($count) {}
    public function assertSequencesAreEmpty() {}
    public function recorded($callback = NULL) {}
    public function createPendingRequest() {}
    protected function newPendingRequest() {}
    public function getDispatcher() {}
    public function getGlobalMiddleware() {}
    public function __call($method, $parameters) {}
    public function macroCall($method, $parameters) {}
}
