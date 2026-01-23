<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\Commandment;
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
            if (is_string($key)) {
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
            $prophet = app($class);

            if (method_exists($prophet, 'configure')) {
                $prophetConfig = $this->prophetConfigs[$scroll][$class] ?? [];
                $prophet->configure(array_merge($thresholds, $prophetConfig));
            }

            return $prophet;
        });
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
                        'prophet' => app($prophetClass),
                    ];
                }
            }
        }

        return null;
    }
}
