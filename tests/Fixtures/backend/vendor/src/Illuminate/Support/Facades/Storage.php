<?php

namespace Illuminate\Support\Facades;

class Storage extends \Illuminate\Support\Facades\Facade
{
    /** @return \Illuminate\Filesystem\LocalFilesystemAdapter */
    public static function fake($disk = NULL, array $config = array (
)) {}
    /** @return \Illuminate\Filesystem\LocalFilesystemAdapter */
    public static function persistentFake($disk = NULL, array $config = array (
)) {}
    protected static function getRootPath(string $disk): string {}
    protected static function buildDiskConfiguration(string $disk, array $config, string $root): array {}
    /** @return string */
    protected static function getFacadeAccessor() {}
}
