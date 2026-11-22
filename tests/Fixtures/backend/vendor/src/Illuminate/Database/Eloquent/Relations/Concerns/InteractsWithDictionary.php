<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait InteractsWithDictionary
{
    /** @return string|int|null */
    protected function getDictionaryKey($attribute) {}
}
