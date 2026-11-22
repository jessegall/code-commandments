<?php

namespace Illuminate\Foundation;

class Application extends \Illuminate\Container\Container implements \Illuminate\Contracts\Foundation\Application, \Illuminate\Contracts\Foundation\CachesConfiguration, \Illuminate\Contracts\Foundation\CachesRoutes, \Symfony\Component\HttpKernel\HttpKernelInterface
{
    use \Illuminate\Support\Traits\Macroable;
    public const VERSION = '12.65.0';
    protected $basePath = NULL;
    protected $registeredCallbacks = array (
);
    protected $hasBeenBootstrapped = false;
    protected $booted = false;
    protected $bootingCallbacks = array (
);
    protected $bootedCallbacks = array (
);
    protected $terminatingCallbacks = array (
);
    protected $serviceProviders = array (
);
    protected $loadedProviders = array (
);
    protected $deferredServices = array (
);
    protected $bootstrapPath = NULL;
    protected $appPath = NULL;
    protected $configPath = NULL;
    protected $databasePath = NULL;
    protected $langPath = NULL;
    protected $publicPath = NULL;
    protected $storagePath = NULL;
    protected $environmentPath = NULL;
    protected $environmentFile = '.env';
    protected $isRunningInConsole = NULL;
    protected $namespace = NULL;
    protected $mergeFrameworkConfiguration = true;
    protected $absoluteCachePathPrefixes = array (
  0 => '/',
  1 => '\\',
);
    public function __construct($basePath = NULL) {}
    public static function configure(?string $basePath = NULL) {}
    public static function inferBasePath() {}
    public function version() {}
    protected function registerBaseBindings() {}
    protected function registerBaseServiceProviders() {}
    protected function registerLaravelCloudServices() {}
    public function bootstrapWith(array $bootstrappers) {}
    public function afterLoadingEnvironment(\Closure $callback) {}
    public function beforeBootstrapping($bootstrapper, \Closure $callback) {}
    public function afterBootstrapping($bootstrapper, \Closure $callback) {}
    public function hasBeenBootstrapped() {}
    public function setBasePath($basePath) {}
    protected function bindPathsInContainer() {}
    public function path($path = '') {}
    public function useAppPath($path) {}
    public function basePath($path = '') {}
    public function bootstrapPath($path = '') {}
    public function getBootstrapProvidersPath() {}
    public function useBootstrapPath($path) {}
    public function configPath($path = '') {}
    public function useConfigPath($path) {}
    public function databasePath($path = '') {}
    public function useDatabasePath($path) {}
    public function langPath($path = '') {}
    public function useLangPath($path) {}
    public function publicPath($path = '') {}
    public function usePublicPath($path) {}
    public function storagePath($path = '') {}
    public function useStoragePath($path) {}
    public function resourcePath($path = '') {}
    public function viewPath($path = '') {}
    public function joinPaths($basePath, $path = '') {}
    public function environmentPath() {}
    public function useEnvironmentPath($path) {}
    public function loadEnvironmentFrom($file) {}
    public function environmentFile() {}
    public function environmentFilePath() {}
    public function environment(...$environments) {}
    public function isLocal() {}
    public function isProduction() {}
    public function detectEnvironment(\Closure $callback) {}
    public function runningInConsole() {}
    public function runningConsoleCommand(...$commands) {}
    public function runningUnitTests() {}
    public function hasDebugModeEnabled() {}
    public function registered($callback) {}
    public function registerConfiguredProviders() {}
    public function register($provider, $force = false) {}
    public function getProvider($provider) {}
    public function getProviders($provider) {}
    public function resolveProvider($provider) {}
    protected function markAsRegistered($provider) {}
    public function loadDeferredProviders() {}
    public function loadDeferredProvider($service) {}
    public function registerDeferredProvider($provider, $service = NULL) {}
    public function make($abstract, array $parameters = array (
)) {}
    protected function resolve($abstract, $parameters = array (
), $raiseEvents = true) {}
    protected function loadDeferredProviderIfNeeded($abstract) {}
    public function bound($abstract) {}
    public function isBooted() {}
    public function boot() {}
    protected function bootProvider(\Illuminate\Support\ServiceProvider $provider) {}
    public function booting($callback) {}
    public function booted($callback) {}
    protected function fireAppCallbacks(array &$callbacks) {}
    public function handle(\Symfony\Component\HttpFoundation\Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): \Symfony\Component\HttpFoundation\Response {}
    public function handleRequest(\Illuminate\Http\Request $request) {}
    public function handleCommand(\Symfony\Component\Console\Input\InputInterface $input) {}
    public function shouldMergeFrameworkConfiguration() {}
    public function dontMergeFrameworkConfiguration() {}
    public function shouldSkipMiddleware() {}
    public function getCachedServicesPath() {}
    public function getCachedPackagesPath() {}
    public function configurationIsCached() {}
    public function getCachedConfigPath() {}
    public function routesAreCached() {}
    public function getCachedRoutesPath() {}
    public function eventsAreCached() {}
    public function getCachedEventsPath() {}
    protected function normalizeCachePath($key, $default) {}
    public function addAbsoluteCachePathPrefix($prefix) {}
    public function maintenanceMode() {}
    public function isDownForMaintenance() {}
    public function abort($code, $message = '', array $headers = array (
)) {}
    public function terminating($callback) {}
    public function terminate() {}
    public function getLoadedProviders() {}
    public function providerIsLoaded(string $provider) {}
    public function getDeferredServices() {}
    public function setDeferredServices(array $services) {}
    public function isDeferredService($service) {}
    public function addDeferredServices(array $services) {}
    public function removeDeferredServices(array $services) {}
    public function provideFacades($namespace) {}
    public function getLocale() {}
    public function currentLocale() {}
    public function getFallbackLocale() {}
    public function setLocale($locale) {}
    public function setFallbackLocale($fallbackLocale) {}
    public function isLocale($locale) {}
    public function registerCoreContainerAliases() {}
    public function flush() {}
    public function getNamespace() {}
}
