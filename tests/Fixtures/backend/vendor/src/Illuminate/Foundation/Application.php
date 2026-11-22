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
    /** @return \Illuminate\Foundation\Configuration\ApplicationBuilder */
    public static function configure(?string $basePath = NULL) {}
    /** @return string */
    public static function inferBasePath() {}
    /** @return string */
    public function version() {}
    /** @return void */
    protected function registerBaseBindings() {}
    /** @return void */
    protected function registerBaseServiceProviders() {}
    /** @return void */
    protected function registerLaravelCloudServices() {}
    /** @return void */
    public function bootstrapWith(array $bootstrappers) {}
    /** @return void */
    public function afterLoadingEnvironment(\Closure $callback) {}
    /** @return void */
    public function beforeBootstrapping($bootstrapper, \Closure $callback) {}
    /** @return void */
    public function afterBootstrapping($bootstrapper, \Closure $callback) {}
    /** @return bool */
    public function hasBeenBootstrapped() {}
    /** @return $this */
    public function setBasePath($basePath) {}
    /** @return void */
    protected function bindPathsInContainer() {}
    /** @return string */
    public function path($path = '') {}
    /** @return $this */
    public function useAppPath($path) {}
    /** @return string */
    public function basePath($path = '') {}
    /** @return string */
    public function bootstrapPath($path = '') {}
    /** @return string */
    public function getBootstrapProvidersPath() {}
    /** @return $this */
    public function useBootstrapPath($path) {}
    /** @return string */
    public function configPath($path = '') {}
    /** @return $this */
    public function useConfigPath($path) {}
    /** @return string */
    public function databasePath($path = '') {}
    /** @return $this */
    public function useDatabasePath($path) {}
    /** @return string */
    public function langPath($path = '') {}
    /** @return $this */
    public function useLangPath($path) {}
    /** @return string */
    public function publicPath($path = '') {}
    /** @return $this */
    public function usePublicPath($path) {}
    /** @return string */
    public function storagePath($path = '') {}
    /** @return $this */
    public function useStoragePath($path) {}
    /** @return string */
    public function resourcePath($path = '') {}
    /** @return string */
    public function viewPath($path = '') {}
    /** @return string */
    public function joinPaths($basePath, $path = '') {}
    /** @return string */
    public function environmentPath() {}
    /** @return $this */
    public function useEnvironmentPath($path) {}
    /** @return $this */
    public function loadEnvironmentFrom($file) {}
    /** @return string */
    public function environmentFile() {}
    /** @return string */
    public function environmentFilePath() {}
    /** @return string|bool */
    public function environment(...$environments) {}
    /** @return bool */
    public function isLocal() {}
    /** @return bool */
    public function isProduction() {}
    /** @return string */
    public function detectEnvironment(\Closure $callback) {}
    /** @return bool */
    public function runningInConsole() {}
    /** @return bool */
    public function runningConsoleCommand(...$commands) {}
    /** @return bool */
    public function runningUnitTests() {}
    /** @return bool */
    public function hasDebugModeEnabled() {}
    /** @return void */
    public function registered($callback) {}
    /** @return void */
    public function registerConfiguredProviders() {}
    /** @return \Illuminate\Support\ServiceProvider */
    public function register($provider, $force = false) {}
    /** @return \Illuminate\Support\ServiceProvider|null */
    public function getProvider($provider) {}
    /** @return array */
    public function getProviders($provider) {}
    /** @return \Illuminate\Support\ServiceProvider */
    public function resolveProvider($provider) {}
    /** @return void */
    protected function markAsRegistered($provider) {}
    /** @return void */
    public function loadDeferredProviders() {}
    /** @return void */
    public function loadDeferredProvider($service) {}
    /** @return void */
    public function registerDeferredProvider($provider, $service = NULL) {}
    /** @return ($abstract */
    public function make($abstract, array $parameters = array (
)) {}
    /** @return ($abstract */
    protected function resolve($abstract, $parameters = array (
), $raiseEvents = true) {}
    /** @return void */
    protected function loadDeferredProviderIfNeeded($abstract) {}
    /** @return bool */
    public function bound($abstract) {}
    /** @return bool */
    public function isBooted() {}
    /** @return void */
    public function boot() {}
    /** @return void */
    protected function bootProvider(\Illuminate\Support\ServiceProvider $provider) {}
    /** @return void */
    public function booting($callback) {}
    /** @return void */
    public function booted($callback) {}
    /** @return void */
    protected function fireAppCallbacks(array &$callbacks) {}
    public function handle(\Symfony\Component\HttpFoundation\Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): \Symfony\Component\HttpFoundation\Response {}
    /** @return void */
    public function handleRequest(\Illuminate\Http\Request $request) {}
    /** @return int */
    public function handleCommand(\Symfony\Component\Console\Input\InputInterface $input) {}
    /** @return bool */
    public function shouldMergeFrameworkConfiguration() {}
    /** @return $this */
    public function dontMergeFrameworkConfiguration() {}
    /** @return bool */
    public function shouldSkipMiddleware() {}
    /** @return string */
    public function getCachedServicesPath() {}
    /** @return string */
    public function getCachedPackagesPath() {}
    /** @return bool */
    public function configurationIsCached() {}
    /** @return string */
    public function getCachedConfigPath() {}
    /** @return bool */
    public function routesAreCached() {}
    /** @return string */
    public function getCachedRoutesPath() {}
    /** @return bool */
    public function eventsAreCached() {}
    /** @return string */
    public function getCachedEventsPath() {}
    /** @return string */
    protected function normalizeCachePath($key, $default) {}
    /** @return $this */
    public function addAbsoluteCachePathPrefix($prefix) {}
    /** @return \Illuminate\Contracts\Foundation\MaintenanceMode */
    public function maintenanceMode() {}
    /** @return bool */
    public function isDownForMaintenance() {}
    /** @return never */
    public function abort($code, $message = '', array $headers = array (
)) {}
    /** @return $this */
    public function terminating($callback) {}
    /** @return void */
    public function terminate() {}
    /** @return array<string, */
    public function getLoadedProviders() {}
    /** @return bool */
    public function providerIsLoaded(string $provider) {}
    /** @return array */
    public function getDeferredServices() {}
    /** @return void */
    public function setDeferredServices(array $services) {}
    /** @return bool */
    public function isDeferredService($service) {}
    /** @return void */
    public function addDeferredServices(array $services) {}
    /** @return void */
    public function removeDeferredServices(array $services) {}
    /** @return void */
    public function provideFacades($namespace) {}
    /** @return string */
    public function getLocale() {}
    /** @return string */
    public function currentLocale() {}
    /** @return string */
    public function getFallbackLocale() {}
    /** @return void */
    public function setLocale($locale) {}
    /** @return void */
    public function setFallbackLocale($fallbackLocale) {}
    /** @return bool */
    public function isLocale($locale) {}
    /** @return void */
    public function registerCoreContainerAliases() {}
    /** @return void */
    public function flush() {}
    /** @return string */
    public function getNamespace() {}
}
