<?php

namespace Illuminate\Contracts\Foundation;

interface Application extends \Illuminate\Contracts\Container\Container, \Psr\Container\ContainerInterface
{
    /** @return string */
    public function version();
    /** @return string */
    public function basePath($path = '');
    /** @return string */
    public function bootstrapPath($path = '');
    /** @return string */
    public function configPath($path = '');
    /** @return string */
    public function databasePath($path = '');
    /** @return string */
    public function langPath($path = '');
    /** @return string */
    public function publicPath($path = '');
    /** @return string */
    public function resourcePath($path = '');
    /** @return string */
    public function storagePath($path = '');
    /** @return string|bool */
    public function environment(...$environments);
    /** @return bool */
    public function runningInConsole();
    /** @return bool */
    public function runningUnitTests();
    /** @return bool */
    public function hasDebugModeEnabled();
    /** @return \Illuminate\Contracts\Foundation\MaintenanceMode */
    public function maintenanceMode();
    /** @return bool */
    public function isDownForMaintenance();
    /** @return void */
    public function registerConfiguredProviders();
    /** @return \Illuminate\Support\ServiceProvider */
    public function register($provider, $force = false);
    /** @return void */
    public function registerDeferredProvider($provider, $service = NULL);
    /** @return \Illuminate\Support\ServiceProvider */
    public function resolveProvider($provider);
    /** @return void */
    public function boot();
    /** @return void */
    public function booting($callback);
    /** @return void */
    public function booted($callback);
    /** @return void */
    public function bootstrapWith(array $bootstrappers);
    /** @return string */
    public function getLocale();
    /** @return string */
    public function getNamespace();
    /** @return array */
    public function getProviders($provider);
    /** @return bool */
    public function hasBeenBootstrapped();
    /** @return void */
    public function loadDeferredProviders();
    /** @return void */
    public function setLocale($locale);
    /** @return bool */
    public function shouldSkipMiddleware();
    /** @return \Illuminate\Contracts\Foundation\Application */
    public function terminating($callback);
    /** @return void */
    public function terminate();
}
