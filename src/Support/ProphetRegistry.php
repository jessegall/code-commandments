<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Doctrines\DoctrineRegistry;
use JesseGall\CodeCommandments\Results\Severity;
use Illuminate\Support\Collection;

/**
 * Registry of all prophets (commandment validators).
 * Manages the collection of prophets and their numbering.
 */
class ProphetRegistry
{
    /**
     * @var array<string, array<class-string<Commandment>>>
     */
    protected array $prophets = [];

    /**
     * Pre-built, fluently-configured prophet instances keyed by scroll + class —
     * the new `ProphetClass::make()->severity(...)->...` config form. When present
     * the instance is used as-is (it already carries its settings) instead of
     * `new $class()`.
     *
     * @var array<string, array<class-string<Commandment>, Commandment>>
     */
    protected array $prophetInstances = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $scrollConfigs = [];

    /**
     * Per-prophet configuration.
     *
     * @var array<string, array<class-string<Commandment>, array<string, mixed>>>
     */
    protected array $prophetConfigs = [];

    /**
     * Register a prophet for a scroll.
     *
     * @param string $scroll The scroll (group) name
     * @param class-string<Commandment> $prophetClass
     */
    public function register(string $scroll, string $prophetClass): void
    {
        if (!isset($this->prophets[$scroll])) {
            $this->prophets[$scroll] = [];
        }

        if (!in_array($prophetClass, $this->prophets[$scroll], true)) {
            $this->prophets[$scroll][] = $prophetClass;
        }
    }

    /**
     * Register multiple prophets for a scroll.
     *
     * Supports two formats:
     * - Indexed array: [ProphetClass::class, AnotherProphet::class]
     * - Associative array: [ProphetClass::class => ['config' => 'value']]
     * - Mixed: [ProphetClass::class, AnotherProphet::class => ['config' => 'value']]
     *
     * @param string $scroll
     * @param array<int|class-string<Commandment>, class-string<Commandment>|array<string, mixed>> $prophetClasses
     */
    public function registerMany(string $scroll, array $prophetClasses): void
    {
        foreach ($prophetClasses as $key => $value) {
            if ($value instanceof Commandment) {
                // Fluent: a pre-configured `ProphetClass::make()->severity(...)` instance.
                $class = $value::class;
                $this->register($scroll, $class);
                $this->prophetInstances[$scroll][$class] = $value;
            } elseif (is_string($key)) {
                // Associative: class => config
                $this->register($scroll, $key);
                $this->setProphetConfig($scroll, $key, $value);
            } else {
                // Indexed: just the class
                $this->register($scroll, $value);
            }
        }
    }

    /**
     * Set configuration for a specific prophet in a scroll.
     *
     * @param string $scroll
     * @param class-string<Commandment> $prophetClass
     * @param array<string, mixed> $config
     */
    public function setProphetConfig(string $scroll, string $prophetClass, array $config): void
    {
        if (!isset($this->prophetConfigs[$scroll])) {
            $this->prophetConfigs[$scroll] = [];
        }

        $this->prophetConfigs[$scroll][$prophetClass] = $config;
    }

    /**
     * Get configuration for a specific prophet in a scroll.
     *
     * @param string $scroll
     * @param class-string<Commandment> $prophetClass
     * @return array<string, mixed>
     */
    public function getProphetConfig(string $scroll, string $prophetClass): array
    {
        return $this->prophetConfigs[$scroll][$prophetClass] ?? [];
    }

    /**
     * Set the configuration for a scroll.
     *
     * @param string $scroll
     * @param array<string, mixed> $config
     */
    public function setScrollConfig(string $scroll, array $config): void
    {
        $this->scrollConfigs[$scroll] = $config;
    }

    /**
     * Get all prophets for a scroll.
     *
     * @return Collection<int, Commandment>
     */
    public function getProphets(string $scroll): Collection
    {
        $classes = $this->prophets[$scroll] ?? [];
        $thresholds = $this->scrollConfigs[$scroll]['thresholds'] ?? [];

        return collect($classes)->map(function (string $class) use ($scroll, $thresholds) {
            // A fluently-built instance is already configured — use it as-is so its
            // settings aren't clobbered by the threshold merge.
            $prophet = $this->prophetInstances[$scroll][$class] ?? null;

            if ($prophet === null) {
                $prophet = new $class();

                if (method_exists($prophet, 'configure')) {
                    $prophetConfig = $this->prophetConfigs[$scroll][$class] ?? [];
                    $prophet->configure(array_merge($thresholds, $prophetConfig));
                }
            }

            $this->applyDoctrineSeverity($scroll, $prophet);

            return $prophet;
        })->filter(fn (Commandment $prophet) => $prophet->supported());
    }

    /**
     * Layer a doctrine- or band-level severity default UNDER an unset prophet: a
     * `doctrines` config map (`['totality' => 'sin', 'totality.band.5' => 'warning',
     * 'idiomatic-iteration' => 'off']`) sets the stake for a whole doctrine or one
     * band. A band-specific entry wins over the doctrine-wide one; an explicit
     * prophet-level override always wins over both.
     */
    private function applyDoctrineSeverity(string $scroll, Commandment $prophet): void
    {
        if (! $prophet instanceof BaseCommandment || $prophet->severityOverride() !== null) {
            return;
        }

        $location = DoctrineRegistry::locate($prophet::class);

        if ($location === null) {
            return;
        }

        $doctrines = $this->scrollConfigs[$scroll]['doctrines'] ?? [];
        $byBand = $location['doctrine'] . '.band.' . $location['band'];
        $value = $doctrines[$byBand] ?? $doctrines[$location['doctrine']] ?? null;

        if ($value === null) {
            return;
        }

        $severity = $value instanceof Severity ? $value : (is_string($value) ? Severity::fromName($value) : null);

        if ($severity !== null) {
            $prophet->severity($severity);
        }
    }

    /**
     * Get all prophets across all scrolls.
     *
     * @return Collection<string, Collection<int, Commandment>>
     */
    public function getAllProphets(): Collection
    {
        return collect($this->getScrolls())->mapWithKeys(function (string $scroll) {
            return [$scroll => $this->getProphets($scroll)];
        });
    }

    /**
     * Get a specific prophet by its commandment number within a scroll.
     *
     * @param string $scroll
     * @param int $number 1-indexed commandment number
     */
    public function getProphetByNumber(string $scroll, int $number): ?Commandment
    {
        return $this->getProphets($scroll)->get($number - 1);
    }

    /**
     * Get the commandment number for a prophet class within its scroll.
     *
     * @param string $scroll
     * @param class-string<Commandment> $prophetClass
     * @return int|null 1-indexed number or null if not found
     */
    public function getCommandmentNumber(string $scroll, string $prophetClass): ?int
    {
        $index = array_search($prophetClass, $this->prophets[$scroll] ?? [], true);

        return $index !== false ? $index + 1 : null;
    }

    /**
     * Get all scroll names.
     *
     * @return array<string>
     */
    public function getScrolls(): array
    {
        return array_keys($this->prophets);
    }

    /**
     * Get the configuration for a scroll.
     *
     * @return array<string, mixed>
     */
    public function getScrollConfig(string $scroll): array
    {
        return $this->scrollConfigs[$scroll] ?? [];
    }

    /**
     * Check if a scroll exists.
     */
    public function hasScroll(string $scroll): bool
    {
        return isset($this->prophets[$scroll]);
    }

    /**
     * Get the total count of prophets in a scroll.
     */
    public function count(string $scroll): int
    {
        return count($this->prophets[$scroll] ?? []);
    }

    /**
     * Get the total count of all prophets.
     */
    public function totalCount(): int
    {
        return array_sum(array_map('count', $this->prophets));
    }

    /**
     * Find a prophet by class name (short or fully qualified).
     *
     * @return array{scroll: string, number: int, prophet: Commandment}|null
     */
    public function findProphet(string $name): ?array
    {
        foreach ($this->prophets as $scroll => $prophetClasses) {
            foreach ($prophetClasses as $index => $prophetClass) {
                $shortName = class_basename($prophetClass);

                if ($prophetClass === $name || $shortName === $name || $shortName === $name . 'Prophet') {
                    return [
                        'scroll' => $scroll,
                        'number' => $index + 1,
                        'prophet' => new $prophetClass(),
                    ];
                }
            }
        }

        return null;
    }
}
