<?php

namespace Illuminate\Support\Facades;

class Storage extends \Illuminate\Support\Facades\Facade
{
    public static function fake($disk = NULL, array $config = array (
)) {}
    public static function persistentFake($disk = NULL, array $config = array (
)) {}
    protected static function getRootPath(string $disk): string {}
    protected static function buildDiskConfiguration(string $disk, array $config, string $root): array {}
    protected static function getFacadeAccessor() {}
}
