<?php

namespace Illuminate\Support\Facades;

class Bus extends \Illuminate\Support\Facades\Facade
{
    /** @return \Illuminate\Support\Testing\Fakes\BusFake */
    public static function fake($jobsToFake = array (
), ?\Illuminate\Bus\BatchRepository $batchRepository = NULL) {}
    /** @return \Illuminate\Foundation\Bus\PendingDispatch */
    public static function dispatchChain($jobs) {}
    /** @return string */
    protected static function getFacadeAccessor() {}
}
