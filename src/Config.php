<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use Closure;
use Composer\InstalledVersions;
use InvalidArgumentException;
use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\RequiresNpmPackage;
use JesseGall\CodeCommandments\Frontend\Detector as FrontendDetector;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * Consumer overrides to the shipped detector set (loaded from .commandments/config.php).
 * Methods: disable() suppresses by Detector/Sin/Skill class; detector() adds consumer
 * detectors; package() registers exemptions; configure() tunes thresholds via closure param type.
 */
final class Config
{
    /** @var list<class-string> Sin or Detector classes to suppress. */
    private array $disabled = [];

    /** @var list<class-string<Detector>> Consumer detector classes to add. */
    private array $registered = [];

    /** @var list<class-string<Packages\Package>> Consumer package classes to register exemptions from. */
    private array $packages = [];

    /** @var list<class-string<Cli\Hook>> Consumer Claude Code hook classes to wire alongside the built-ins. */
    private array $hooks = [];

    /** @var list<Closure> One per {@see configure} call — a typed closure that tunes a detector. */
    private array $configurators = [];

    /** The {@see planExecution} closure that composes this project's {@see PlanExecution} profile. */
    private ?Closure $planExecutionConfigurator = null;

    /** @var list<string> The source roots to scan (relative to the project). Empty ⇒ auto-detect + scaffold. */
    private array $roots = [];

    /**
     * Declare the source roots `judge` and `repent` scan — the project's own list of directories,
     * the ONE source of scan scope (both commands read it, so neither can scope differently). On a
     * fresh project this call is auto-scaffolded from composer.json's PSR-4 map (plus `app`/`src`);
     * edit it freely — add a root to scan more, drop one to stop.
     */
    public function paths(string ...$roots): self
    {
        $this->roots = [...$this->roots, ...$roots];

        return $this;
    }

    /**
     * The declared source roots (relative), or `[]` when the project hasn't declared any — in which
     * case the caller auto-detects and scaffolds them into `config.php`.
     *
     * @return list<string>
     */
    public function sourceRoots(): array
    {
        return $this->roots;
    }

    /**
     * Suppress shipped detectors — by a detector's own class, by the {@see Sins\Sin} class it
     * points at (drops every detector for that sin), or by a {@see Skills\Skill} class (drops
     * every detector whose sin that skill teaches — silence a whole discipline in one line).
     *
     * The SAME verb silences a Claude Code {@see Hooks\Hook}: name a hook class here and it drops
     * from both the wiring and the dispatch ({@see enabledHooks}) — so a project turns off a nudge
     * (the judge reminder, say) without hand-editing `settings.json`.
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
     * it). Its {@see Sins\Sin} rides along via `sin()`; a `Frontend\Detector` joins the frontend set,
     * anything else the backend set.
     *
     * @param  class-string<Detector>  ...$detectors
     */
    public function detector(string ...$detectors): self
    {
        $this->registered = [...$this->registered, ...$detectors];

        return $this;
    }

    /**
     * Register a {@see Packages\Package} that lives in the consumer's own codebase (the package's
     * glob only finds the built-in ones). Its {@see Packages\Package::register} exemptions then
     * join the shared {@see Packages\Exemptions} registry — so your framework's types are exempt
     * from the built-in rules, and a custom detector's tag can be fed by anyone.
     *
     * @param  class-string<Packages\Package>  ...$packages
     */
    public function package(string ...$packages): self
    {
        $this->packages = [...$this->packages, ...$packages];

        return $this;
    }

    /**
     * The consumer's own {@see Packages\Package} classes, as class-strings (none by default). The
     * CLI hands these to {@see Packages\Exemptions::usePackages} before any detector runs.
     *
     * @return list<class-string<Packages\Package>>
     */
    public function packages(): array
    {
        return $this->packages;
    }

    /**
     * Register a consumer's own Claude Code {@see Cli\Hook} class, wired into `.claude/settings.json`
     * alongside the built-in hooks on `install`/`sync` — the same open-set pattern as {@see detector}
     * and {@see package}. The hook declares its own events via `bindings()`, so it can react to any
     * Claude Code moment (Stop, PostToolUse, …). Every wired hook is stamped, so re-wiring only ever
     * touches ours, never a hook you hand-wrote in settings.
     *
     * @param  class-string<Cli\Hook>  ...$hooks
     */
    public function hook(string ...$hooks): self
    {
        $this->hooks = [...$this->hooks, ...$hooks];

        return $this;
    }

    /**
     * The consumer's own hook classes added via {@see hook} (none by default).
     *
     * @return list<class-string<Cli\Hook>>
     */
    public function hooks(): array
    {
        return $this->hooks;
    }

    /**
     * The hook classes to actually wire and run for this project — the given set (the builtins plus
     * any {@see hook}) minus any a project silenced by naming its class in {@see disable}. A hook is
     * disabled exactly like a rule; the {@see Hooks\HookRegistry} routes both wiring and dispatch
     * through here, so a disabled hook stays off even before the next `sync` re-wires settings.
     *
     * @param  list<class-string<Hooks\Hook>>  $all
     * @return list<class-string<Hooks\Hook>>
     */
    public function enabledHooks(array $all): array
    {
        return array_values(array_filter(
            $all,
            fn (string $class): bool => ! in_array($class, $this->disabled, true),
        ));
    }

    /**
     * The consumer's own detector classes added via {@see detector} (none by default).
     *
     * @return list<class-string<Detector>>
     */
    public function registeredDetectors(): array
    {
        return $this->registered;
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
     * Declare the plan-execution profile — the branch strategy, push cadence, keep-going policy,
     * and the checks a plan runs at each moment ({@see PlanExecution}). The closure is handed a
     * fresh builder to compose; a `function (PlanExecution $plan) {…}` block and a fluent
     * `fn ($plan) => $plan->…->…` arrow both work, since the builder is mutated in place.
     */
    public function planExecution(Closure $configurator): self
    {
        $this->planExecutionConfigurator = $configurator;

        return $this;
    }

    /**
     * The resolved {@see PlanProfile} — a fresh {@see PlanExecution} builder with the project's
     * configurator applied (an empty default when none was declared), frozen for reading.
     */
    public function planExecutionSettings(): PlanProfile
    {
        $builder = new PlanExecution;

        if ($this->planExecutionConfigurator !== null) {
            ($this->planExecutionConfigurator)($builder);
        }

        return $builder->build();
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
     * Is this detector suppressed — its own class disabled, the sin it points at, or the skill
     * that teaches the fix?
     */
    private function isDisabled(Detector $detector): bool
    {
        $sin = $detector->sin();

        return in_array($detector::class, $this->disabled, true)
            || in_array($sin::class, $this->disabled, true)
            || in_array($sin->skill()::class, $this->disabled, true);
    }

    /**
     * Is this detector's package present? A sin that requires no package always is; one that
     * {@see RequiresComposerPackage} or {@see RequiresNpmPackage} is kept only when `$installed`
     * reports its package in that ecosystem. The ecosystem is stated by the interface, not the
     * rule's engine — a frontend sin may require a Composer package.
     *
     * @param  callable(string, string): bool  $installed  ($package, $ecosystem) => present?
     */
    private function hasPackage(Detector $detector, callable $installed): bool
    {
        $sin = $detector->sin();

        if ($sin instanceof RequiresComposerPackage) {
            return $installed($sin->requiredComposerPackage(), 'composer');
        }

        if ($sin instanceof RequiresNpmPackage) {
            return $installed($sin->requiredNpmPackage(), 'npm');
        }

        return true;
    }

    /**
     * The default package check — Composer's installed set for a Composer requirement, the
     * project's `package.json` for an npm one. Both fall back to "present" when the manifest
     * can't be read, so an unknown environment never over-filters.
     *
     * @return callable(string, string): bool
     */
    private static function defaultPackageCheck(): callable
    {
        return static fn (string $package, string $ecosystem): bool =>
            $ecosystem === 'npm' ? self::inPackageJson($package) : self::inComposer($package);
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
