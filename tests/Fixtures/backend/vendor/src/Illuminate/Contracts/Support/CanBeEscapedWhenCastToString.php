<?php

namespace Illuminate\Contracts\Support;

interface CanBeEscapedWhenCastToString
{
    /** @return $this */
    public function escapeWhenCastingToString($escape = true);
}
