<?php

namespace Illuminate\Http\Concerns;

trait InteractsWithContentTypes
{
    /** @return bool */
    public function isJson() {}
    /** @return bool */
    public function expectsJson() {}
    /** @return bool */
    public function wantsJson() {}
    /** @return bool */
    public function wantsMarkdown() {}
    /** @return bool */
    public function accepts($contentTypes) {}
    /** @return string|null */
    public function prefers($contentTypes) {}
    /** @return bool */
    public function acceptsAnyContentType() {}
    /** @return bool */
    public function acceptsJson() {}
    /** @return bool */
    public function acceptsMarkdown() {}
    /** @return bool */
    public function acceptsHtml() {}
    /** @return bool */
    public static function matchesType($actual, $type) {}
    /** @return string */
    public function format($default = 'html') {}
}
