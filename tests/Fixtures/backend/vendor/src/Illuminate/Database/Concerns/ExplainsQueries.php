<?php

namespace Illuminate\Database\Concerns;

trait ExplainsQueries
{
    /** @return \Illuminate\Support\Collection */
    public function explain() {}
}
