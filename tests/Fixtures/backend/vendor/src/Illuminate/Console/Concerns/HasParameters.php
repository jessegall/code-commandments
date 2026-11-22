<?php

namespace Illuminate\Console\Concerns;

trait HasParameters
{
    /** @return void */
    protected function specifyParameters() {}
    /** @return (InputArgument|array{ */
    protected function getArguments() {}
    /** @return (InputOption|array{ */
    protected function getOptions() {}
}
