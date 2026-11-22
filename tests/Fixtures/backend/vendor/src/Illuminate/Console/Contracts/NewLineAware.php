<?php

namespace Illuminate\Console\Contracts;

interface NewLineAware
{
    /** @return int */
    public function newLinesWritten();
    /** @return bool */
    public function newLineWritten();
}
