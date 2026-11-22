<?php

namespace Illuminate\Support\Facades;

class Bus extends \Illuminate\Support\Facades\Facade
{
    public static function fake($jobsToFake = array (
), ?\Illuminate\Bus\BatchRepository $batchRepository = NULL) {}
    public static function dispatchChain($jobs) {}
    protected static function getFacadeAccessor() {}
}
