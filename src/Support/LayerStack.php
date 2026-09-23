<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * A project's declared layers — each a namespace or package, naming the ones it may use — read in one
 * language's spelling of a qualified name: its separator (`\` for PHP, `.` for Python) and whether case counts.
 */
final readonly class LayerStack
{
    /**
     * @param  array<string, list<string>>  $layers  declared layer => the names it may reference
     */
    public function __construct(
        private array $layers,
        private string $separator,
        private bool $caseSensitive,
    ) {}

    public function isEmpty(): bool
    {
        return $this->layers === [];
    }

    /**
     * The declared layer $name falls in — the MOST SPECIFIC one, so declaring both `App\Ui` and
     * `App\Ui\Elements` puts a Button in `Elements` — or none when it falls in none.
     */
    public function layerOf(string $name): ?string
    {
        $found = null;

        foreach (array_keys($this->layers) as $layer) {
            if ($this->within($name, $layer) && strlen($layer) > strlen((string) $found)) {
                $found = $layer;
            }
        }

        return $found;
    }

    /**
     * May code in $layer reference $target? Its own layer always — a layer contains what is nested in it —
     * else one of the names it declared it may use.
     */
    public function mayReference(string $layer, string $target): bool
    {
        if ($this->within($target, $layer)) {
            return true;
        }

        return array_any($this->layers[$layer], fn (string $allowed) => $this->within($target, trim($allowed, $this->separator)));
    }

    /**
     * Is $name $prefix itself, or nested inside it?
     */
    private function within(string $name, string $prefix): bool
    {
        $name = trim($name, $this->separator);
        $prefix = trim($prefix, $this->separator);

        if (! $this->caseSensitive) {
            [$name, $prefix] = [strtolower($name), strtolower($prefix)];
        }

        return $prefix === '' || $name === $prefix || str_starts_with($name, $prefix . $this->separator);
    }
}
