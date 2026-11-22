<?php

namespace Illuminate\Support;

abstract class ServiceProvider
{
    protected $app = NULL;
    protected $bootingCallbacks = array (
);
    protected $bootedCallbacks = array (
);
    public static $publishes = array (
);
    public static $publishGroups = array (
);
    protected static $publishableMigrationPaths = array (
);
    public static array $optimizeCommands = array (
);
    public static array $optimizeClearCommands = array (
);
    public static array $reloadCommands = array (
);
    public function __construct($app) {}
    /** @return void */
    public function register() {}
    /** @return void */
    public function booting(\Closure $callback) {}
    /** @return void */
    public function booted(\Closure $callback) {}
    /** @return void */
    public function callBootingCallbacks() {}
    /** @return void */
    public function callBootedCallbacks() {}
    /** @return void */
    protected function mergeConfigFrom($path, $key) {}
    /** @return void */
    protected function replaceConfigRecursivelyFrom($path, $key) {}
    /** @return void */
    protected function loadRoutesFrom($path) {}
    /** @return void */
    protected function loadViewsFrom($path, $namespace) {}
    /** @return void */
    protected function loadViewComponentsAs($prefix, array $components) {}
    /** @return void */
    protected function loadTranslationsFrom($path, $namespace = NULL) {}
    /** @return void */
    protected function loadJsonTranslationsFrom($path) {}
    /** @return void */
    protected function loadMigrationsFrom($paths) {}
    /** @return void */
    protected function loadFactoriesFrom($paths) {}
    /** @return void */
    protected function callAfterResolving($name, $callback) {}
    /** @return void */
    protected function publishesMigrations(array $paths, $groups = NULL) {}
    /** @return void */
    protected function publishes(array $paths, $groups = NULL) {}
    /** @return void */
    protected function ensurePublishArrayInitialized($class) {}
    /** @return void */
    protected function addPublishGroup($group, $paths) {}
    /** @return array */
    public static function pathsToPublish($provider = NULL, $group = NULL) {}
    /** @return array */
    protected static function pathsForProviderOrGroup($provider, $group) {}
    /** @return array */
    protected static function pathsForProviderAndGroup($provider, $group) {}
    /** @return array */
    public static function publishableProviders() {}
    /** @return array */
    public static function publishableMigrationPaths() {}
    /** @return array */
    public static function publishableGroups() {}
    /** @return void */
    public function commands($commands) {}
    /** @return void */
    protected function optimizes(?string $optimize = NULL, ?string $clear = NULL, ?string $key = NULL) {}
    /** @return void */
    protected function reloads(string $reload, ?string $key = NULL) {}
    protected function getProviderKey(?string $key = NULL): string {}
    /** @return array */
    public function provides() {}
    /** @return array */
    public function when() {}
    /** @return bool */
    public function isDeferred() {}
    /** @return \Illuminate\Support\DefaultProviders */
    public static function defaultProviders() {}
    /** @return bool */
    public static function addProviderToBootstrapFile(string $provider, ?string $path = NULL) {}
    /** @return bool */
    public static function removeProviderFromBootstrapFile(array|string $providersToRemove, ?string $path = NULL, bool $strict = false) {}
}
