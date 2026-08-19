<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use Closure;
use Composer\InstalledVersions;
use JesseGall\CodeCommandments\Sins\RequiresPackage;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * Consumer overrides to the shipped detector set (loaded from .commandments/config.php).
 * Methods: disable() suppresses by Detector/Sin/Skill class; detector() adds consumer
 * detectors; package() registers exemptions; configure() tunes thresholds via closure param type.
 */
final class Config
{
    /**
     * @var list<class-string> Sin or Detector classes to suppress.
     */
    private array $disabled = [];

    /**
     * @var list<Language> Languages this project does not write.
     */
    private array $disabledLanguages = [];

    /**
     * @var list<class-string<Detector>> Consumer detector classes to add.
     */
    private array $registered = [];

    /**
     * @var list<class-string<Packages\Package>> Consumer package classes to register exemptions from.
     */
    private array $packages = [];

    /**
     * @var list<class-string<Cli\Hook>> Consumer Claude Code hook classes to wire alongside the built-ins.
     */
    private array $hooks = [];

    /**
     * @var list<class-string<Agents\Agent>>
     */
    private array $agents = [];

    /**
     * @var list<Closure> One per {@see configure} call — a typed closure that tunes a detector.
     */
    private array $configurators = [];

    /**
     * The {@see planExecution} closure that composes this project's {@see PlanExecution} profile.
     */
    private ?Closure $planExecutionConfigurator = null;

    /**
     * @var list<string> The source roots to scan (relative to the project). Empty ⇒ auto-detect + scaffold.
     */
    private array $roots = [];

    /**
     * @var list<string> Paths (relative to the project) subtracted from the scan — never a target.
     */
    private array $excluded = [];

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
     * Does `judge` scan $file — i.e. does it sit under a declared source root? $root is the project
     * the roots are relative to; $file may be absolute or project-relative.
     *
     * The question belongs here rather than at each caller: a hook that only speaks about judged code
     * and a hook that only speaks about UNjudged code are asking the same thing, and answering it
     * twice is how the two drift apart. With no roots declared yet nothing is provably judged, and
     * the answer is `false`.
     */
    public function isJudged(string $root, string $file): bool
    {
        $home = rtrim($root, '/');
        $absolute = str_starts_with($file, '/') ? $file : $home . '/' . ltrim($file, '/');

        foreach ($this->roots as $relative) {
            $dir = $relative === '.' ? $home : $home . '/' . trim($relative, '/');

            if ($absolute === $dir || str_starts_with($absolute, $dir . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Subtract explicit paths from the scan — declared ON TOP of the inclusive {@see paths}, so a
     * directory or file listed here (relative to the project) is NEVER a target: judge never reports
     * a sin in it and repent never rewrites it, however it was reached (a broad root, `--changes`,
     * `--branch`). An entry is a project-relative directory or file, or a glob over one — so a
     * monorepo covers every sub-project's build output in a single starred line rather than one
     * each. The tree is pruned from the WALK, so an excluded path costs the run nothing to skip.
     */
    public function exclude(string ...$paths): self
    {
        $this->excluded = [...$this->excluded, ...$paths];

        return $this;
    }

    /**
     * The paths this project excludes from the scan (relative to the project; none by default). The
     * CLI resolves these into a {@see Cli\Scope\ExcludedScope} compounded into every run's scope.
     *
     * @return list<string>
     */
    public function excludedPaths(): array
    {
        return $this->excluded;
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
     * And the same verb silences a whole LANGUAGE: `disable(Language::TypeScript)` drops every `.ts`
     * file from the scan and every TypeScript example from the published skills, so a project that
     * writes no TypeScript is never judged against rules it cannot break.
     *
     * @param  class-string|Language  ...$classes
     */
    public function disable(string|Language ...$classes): self
    {
        foreach ($classes as $class) {
            if ($class instanceof Language) {
                $this->disabledLanguages[] = $class;

                continue;
            }

            $this->disabled[] = $class;
        }

        return $this;
    }

    /**
     * The languages this project does not write — nothing in them is scanned, and nothing written in
     * them is taught.
     *
     * @return list<Language>
     */
    public function disabledLanguages(): array
    {
        return $this->disabledLanguages;
    }

    /**
     * Is $language one this project writes? The single question the scan and the skill library both
     * ask, so neither has to know how the answer was configured.
     */
    public function writes(Language $language): bool
    {
        return ! in_array($language, $this->disabledLanguages, true);
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
        return $this->enabled($all);
    }

    /**
     * $all minus whatever the project named in {@see disable} — the one filter behind every open set
     * that is silenced by class. Takes instances or class names, so a registry that discovers objects
     * ({@see Agents\Catalog}) and one that carries class strings ({@see Hooks\HookRegistry}) ask the
     * same question rather than each keeping its own answer.
     *
     * @template T of object|string
     *
     * @param  list<T>  $all
     * @return list<T>
     */
    public function enabled(array $all): array
    {
        return array_values(array_filter(
            $all,
            fn (object|string $item): bool => ! in_array(is_object($item) ? $item::class : $item, $this->disabled, true),
        ));
    }

    /**
     * Register a consumer's own {@see Agents\Agent} — an assistant this package should publish into
     * beyond the ones it ships. The open-set pattern again, and the same `disable()` verb turns a
     * shipped one off.
     *
     * @param  class-string<Agents\Agent>  ...$agents
     */
    public function agent(string ...$agents): self
    {
        $this->agents = [...$this->agents, ...$agents];

        return $this;
    }

    /**
     * The consumer's own agent classes added via {@see agent} (none by default).
     *
     * @return list<class-string<Agents\Agent>>
     */
    public function agents(): array
    {
        return $this->agents;
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
     *
     * The project's own classes are loaded FIRST ({@see Custom::load}), so a config line may name a
     * detector, sin, skill or package the project wrote into `.commandments/custom/` without any
     * PSR-4 wiring — the folder IS the autoloader for a project's own rules.
     */
    public static function load(?string $dir = null): self
    {
        Custom::load($dir);

        $config = new self;
        $file = Workspace::config($dir);

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
     * Say ONCE which configured detectors could not be loaded — a project that keeps its rules in a
     * folder it forgot to commit names every one of them, and a paragraph repeated per class buries
     * the run's actual output.
     *
     * @param  list<string>  $missing
     */
    private function reportMissing(array $missing): void
    {
        if ($missing === []) {
            return;
        }

        fwrite(STDERR, '⚠ ' . count($missing) . " configured detector(s) could not be loaded, and were skipped:\n  "
            . implode("\n  ", $missing)
            . "\n  A project's own rules live in .commandments/" . Workspace::CUSTOM
            . "/ (loaded by file, so they need no autoloader) — check they are present and committed,"
            . " or that composer autoloads their namespace.\n\n");
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

        $missing = [];

        foreach ($this->registered as $class) {
            // A class the autoloader can't find reached `new` and took the WHOLE config down with an
            // uncaught Error — so one custom detector that wasn't committed, or isn't autoloaded on
            // this machine, cost the project its paths() and exclude() as well. It is reported and
            // skipped instead: a rule we cannot load is one rule missing, not a run that won't start.
            if (! class_exists($class)) {
                $missing[] = $class;

                continue;
            }

            $detector = new $class;

            if ($keep($detector)) {
                $detectors[] = $detector;
            }
        }

        $this->reportMissing($missing);

        $detectors = array_values($detectors);

        foreach ($this->tune($detectors) as $class) {
            throw InvalidConfiguration::unknownDetector($class);
        }

        return [
            'backend' => self::ofEngine($detectors, Engine::Backend),
            'frontend' => self::ofEngine($detectors, Engine::Frontend),
        ];
    }

    /**
     * The detectors of one engine, in the order they were registered.
     *
     * @param  list<Detector>  $detectors
     * @return list<Detector>
     */
    private static function ofEngine(array $detectors, Engine $engine): array
    {
        return array_values(array_filter($detectors, static fn (Detector $d): bool => Engine::of($d) === $engine));
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
            || in_array($sin->skillClass(), $this->disabled, true);
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

        // The sin says which package and which manifest answers for it; nothing here reads its type
        // to find out. A new ecosystem is then a sin that says so, not another branch in this method.
        return ! $sin instanceof RequiresPackage || $installed($sin->requiredPackage(), $sin->ecosystem());
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
     * Apply ONLY this config's {@see configure} tuning to the given detectors — no package
     * filtering, no disabling — mutating each targeted detector in place, and REPORT the
     * configured classes the set didn't contain instead of throwing. {@see apply} turns that
     * report into the hard error a project's own config deserves; a caller holding a deliberate
     * SUBSET tunes what it has and ignores the rest.
     *
     * That subset caller is the fixture harness: a fixture directory is itself a project, so it
     * declares its detectors' settings in its own `.commandments/config.php`
     * ({@see Testing\FixtureConfig}) — which is what lets a detector that stays INERT until
     * declared (layer declarations, a threshold) be proven on a fixture at all.
     *
     * @param  list<Detector>  $detectors
     * @return list<class-string>  the configured classes absent from $detectors
     */
    public function tune(array $detectors): array
    {
        $unmatched = [];

        foreach ($this->configurators as $configurator) {
            $type = (new ReflectionFunction($configurator))->getParameters()[0] ?? null;
            $class = $type?->getType() instanceof ReflectionNamedType ? $type->getType()->getName() : null;

            if ($class === null) {
                throw InvalidConfiguration::untypedConfigurator();
            }

            $target = array_find($detectors, static fn (Detector $detector): bool => $detector instanceof $class);

            if ($target === null) {
                $unmatched[] = $class;

                continue;
            }

            $configurator($target);
        }

        return $unmatched;
    }
}
