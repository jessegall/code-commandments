<?php

namespace Illuminate\Http\Concerns;

trait InteractsWithContentTypes
{
    public function isJson() {}
    public function expectsJson() {}
    public function wantsJson() {}
    public function wantsMarkdown() {}
    public function accepts($contentTypes) {}
    public function prefers($contentTypes) {}
    public function acceptsAnyContentType() {}
    public function acceptsJson() {}
    public function acceptsMarkdown() {}
    public function acceptsHtml() {}
    public static function matchesType($actual, $type) {}
    public function format($default = 'html') {}
}
