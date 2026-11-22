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
    public function register() {}
    public function booting(\Closure $callback) {}
    public function booted(\Closure $callback) {}
    public function callBootingCallbacks() {}
    public function callBootedCallbacks() {}
    protected function mergeConfigFrom($path, $key) {}
    protected function replaceConfigRecursivelyFrom($path, $key) {}
    protected function loadRoutesFrom($path) {}
    protected function loadViewsFrom($path, $namespace) {}
    protected function loadViewComponentsAs($prefix, array $components) {}
    protected function loadTranslationsFrom($path, $namespace = NULL) {}
    protected function loadJsonTranslationsFrom($path) {}
    protected function loadMigrationsFrom($paths) {}
    protected function loadFactoriesFrom($paths) {}
    protected function callAfterResolving($name, $callback) {}
    protected function publishesMigrations(array $paths, $groups = NULL) {}
    protected function publishes(array $paths, $groups = NULL) {}
    protected function ensurePublishArrayInitialized($class) {}
    protected function addPublishGroup($group, $paths) {}
    public static function pathsToPublish($provider = NULL, $group = NULL) {}
    protected static function pathsForProviderOrGroup($provider, $group) {}
    protected static function pathsForProviderAndGroup($provider, $group) {}
    public static function publishableProviders() {}
    public static function publishableMigrationPaths() {}
    public static function publishableGroups() {}
    public function commands($commands) {}
    protected function optimizes(?string $optimize = NULL, ?string $clear = NULL, ?string $key = NULL) {}
    protected function reloads(string $reload, ?string $key = NULL) {}
    protected function getProviderKey(?string $key = NULL): string {}
    public function provides() {}
    public function when() {}
    public function isDeferred() {}
    public static function defaultProviders() {}
    public static function addProviderToBootstrapFile(string $provider, ?string $path = NULL) {}
    public static function removeProviderFromBootstrapFile(array|string $providersToRemove, ?string $path = NULL, bool $strict = false) {}
}
