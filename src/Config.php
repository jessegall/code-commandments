<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use Closure;
use Composer\InstalledVersions;
use InvalidArgumentException;
use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Vue\Detector as FrontendDetector;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * A consumer's overrides to the shipped detector set — the runtime twin of the doc-generating
 * {@see Detectors\Catalog}. Loaded from `.commandments/config.php` (the CLI `require`s it, so it
 * works without any framework), which returns a `fn (Config): void` that composes three moves:
 *
 *   return function (Config $config): void {
 *       $config
 *           ->disable(NonFinalData::class, DeepNestingDetector::class)   // suppress a built-in
 *           ->register(MyCustomDetector::class)                          // add your own finder
 *           ->configure(fn (DeepNestedDetector $d) => $d->maxDepth(10)); // tune a threshold
 *   };
 *
 * {@see disable} takes a Sin OR a Detector class (a sin drops every detector that points at it);
 * {@see register} adds a detector living in the CONSUMER's codebase (the package can't glob it);
 * {@see configure} takes a closure whose FIRST PARAMETER TYPE names the detector to tune — the
 * matching instance is reflected out of the configured set and handed in, so the closure just
 * calls its fluent setters. The package's own {@see Detectors\Catalog} stays pure (the fixtures
 * and generated docs always run the defaults); this layer applies only at CLI runtime.
 */
final class Config
{
    /** @var list<class-string> Sin or Detector classes to suppress. */
    private array $disabled = [];

    /** @var list<class-string<Detector>> Consumer detector classes to add. */
    private array $registered = [];

    /** @var list<Closure> One per {@see configure} call — a typed closure that tunes a detector. */
    private array $configurators = [];

    /**
     * Suppress a shipped detector — by its own class, or by the {@see Sins\Sin} class it points
     * at (which drops every detector for that sin).
     *
     * @param  class-string  ...$classes
     */
    public function disable(string ...$classes): self
    {
        $this->disabled = [...$this->disabled, ...$classes];

        return $this;
    }

    /**
     * Add a detector that lives in the consumer's own codebase (so the package's glob never sees
     * it). Its {@see Sins\Sin} rides along via `sin()`; a `Vue\Detector` joins the frontend set,
     * anything else the backend set.
     *
     * @param  class-string<Detector>  ...$detectors
     */
    public function register(string ...$detectors): self
    {
        $this->registered = [...$this->registered, ...$detectors];

        return $this;
    }

    /**
     * Tune a detector: the closure's first parameter type names the detector, whose configured
     * instance is injected so the closure can call its fluent setters — `configure(fn
     * (DeepNestedDetector $d) => $d->maxDepth(10))`.
     */
    public function configure(Closure $configurator): self
    {
        $this->configurators[] = $configurator;

        return $this;
    }

    /**
     * The {@see Config} for a project — read from `<dir>/.commandments/config.php` (default the
     * cwd), or an empty (no-op) config when there is none. The file returns a callable given the
     * fresh Config to compose; if it returns a Config, that one wins (either style works).
     */
    public static function load(?string $dir = null): self
    {
        $config = new self;
        $file = ($dir ?? getcwd()) . '/.commandments/config.php';

        if (! is_file($file)) {
            return $config;
        }

        $compose = require $file;

        if (is_callable($compose) && ($returned = $compose($config)) instanceof self) {
            return $returned;
        }

        return $config;
    }

    /**
     * The effective detector sets for THIS project, split by engine: rules whose required
     * package isn't installed are dropped, then the disabled ones, the registered ones are
     * added, and the configurators run. `$installed` decides package availability — defaults to
     * Composer's own installed set; tests inject a fake.
     *
     * @param  list<Detector>  $backend
     * @param  list<Detector>  $frontend
     * @param  (callable(string, bool): bool)|null  $installed  ($package, $isFrontend) => present?
     * @return array{backend: list<Detector>, frontend: list<Detector>}
     */
    public function apply(array $backend, array $frontend, ?callable $installed = null): array
    {
        $installed ??= self::defaultPackageCheck();

        $keep = fn (Detector $d): bool => $this->hasPackage($d, $installed) && ! $this->isDisabled($d);
        $detectors = array_filter([...$backend, ...$frontend], $keep);

        foreach ($this->registered as $class) {
            $detector = new $class;

            if ($keep($detector)) {
                $detectors[] = $detector;
            }
        }

        $detectors = array_values($detectors);
        $this->runConfigurators($detectors);

        return [
            'backend' => array_values(array_filter($detectors, static fn (Detector $d): bool => ! $d instanceof FrontendDetector)),
            'frontend' => array_values(array_filter($detectors, static fn (Detector $d): bool => $d instanceof FrontendDetector)),
        ];
    }

    /**
     * Is this detector suppressed — its own class disabled, or the sin it points at?
     */
    private function isDisabled(Detector $detector): bool
    {
        return in_array($detector::class, $this->disabled, true)
            || in_array($detector->sin()::class, $this->disabled, true);
    }

    /**
     * Is this detector's package present? A sin that doesn't {@see RequiresPackage} always is;
     * one that does is kept only when `$installed` reports its package. The rule's engine picks
     * the ecosystem — a frontend rule's package is an npm one, a backend rule's a Composer one.
     *
     * @param  callable(string, bool): bool  $installed
     */
    private function hasPackage(Detector $detector, callable $installed): bool
    {
        $sin = $detector->sin();

        return ! $sin instanceof RequiresPackage
            || $installed($sin->requiredPackage(), $detector instanceof FrontendDetector);
    }

    /**
     * The default package check — Composer's installed set for a backend rule, the project's
     * `package.json` for a frontend one. Both fall back to "present" when the manifest can't be
     * read, so an unknown environment never over-filters.
     *
     * @return callable(string, bool): bool
     */
    private static function defaultPackageCheck(): callable
    {
        return static fn (string $package, bool $frontend): bool =>
            $frontend ? self::inPackageJson($package) : self::inComposer($package);
    }

    private static function inComposer(string $package): bool
    {
        return ! class_exists(InstalledVersions::class) || InstalledVersions::isInstalled($package);
    }

    private static function inPackageJson(string $package): bool
    {
        $manifest = getcwd() . '/package.json';

        if (! is_file($manifest)) {
            return true;
        }

        $json = (array) json_decode((string) file_get_contents($manifest), true);
        $dependencies = [...(array) ($json['dependencies'] ?? []), ...(array) ($json['devDependencies'] ?? [])];

        return array_key_exists($package, $dependencies);
    }

    /**
     * Hand each configurator its detector, resolved by the closure's first parameter type.
     *
     * @param  list<Detector>  $detectors
     */
    private function runConfigurators(array $detectors): void
    {
        foreach ($this->configurators as $configurator) {
            $type = (new ReflectionFunction($configurator))->getParameters()[0] ?? null;
            $class = $type?->getType() instanceof ReflectionNamedType ? $type->getType()->getName() : null;

            if ($class === null) {
                throw new InvalidArgumentException('A configure() closure must type-hint the detector to configure, e.g. fn (MyDetector $d) => …');
            }

            $target = null;

            foreach ($detectors as $detector) {
                if ($detector instanceof $class) {
                    $target = $detector;

                    break;
                }
            }

            if ($target === null) {
                throw new InvalidArgumentException("configure({$class}): that detector is not registered, or was disabled.");
            }

            $configurator($target);
        }
    }
}
