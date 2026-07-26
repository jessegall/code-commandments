<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Config;

use JesseGall\CodeCommandments\Support\ClassName;

/**
 * The layer declaration of {@see \JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector}
 * — the ONE rule in the catalog a project must state before it can be broken, since only the project
 * knows its own stack. Declared top-down in `.commandments/config.php`:
 *
 * ```php
 * $config->configure(fn (NamespaceDependencyDetector $d) => $d
 *     ->layer('App\\Ui\\Elements')                                // primitives: itself only
 *     ->layer('App\\Ui\\Shared', mayUse: ['App\\Ui\\Elements'])   // built FROM the primitives
 * );
 * ```
 *
 * Undeclared namespaces (the framework, vendor, anything you never named) are always allowed — the
 * declaration constrains the layering you chose, it never invents one.
 */
trait NamespaceDependencyConfig
{
    /**
     * @var array<string, list<string>>  declared layer namespace => the namespaces it may reference
     */
    private array $layers = [];

    /**
     * Declare one layer and what it may reach. `mayUse` names namespaces (declared layers, or any
     * namespace at all), and a layer may ALWAYS reference itself — including everything nested
     * under it, since `App\Ui\Elements\Button` lives inside `App\Ui\Elements`.
     *
     * @param  list<string>  $mayUse
     */
    public function layer(string $namespace, array $mayUse = []): static
    {
        $this->layers[trim($namespace, '\\')] = array_values($mayUse);

        return $this;
    }

    /**
     * The declared layer a fully-qualified name falls in, or null when it falls in none (framework,
     * vendor, undeclared app code). The MOST SPECIFIC layer wins, so declaring both `App\Ui` and
     * `App\Ui\Elements` puts a Button in `Elements` — the narrower claim is the one you meant.
     */
    private function layerOf(string $fqcn): ?string
    {
        $found = null;

        foreach (array_keys($this->layers) as $layer) {
            if (ClassName::within($fqcn, $layer) && strlen($layer) > strlen((string) $found)) {
                $found = $layer;
            }
        }

        return $found;
    }

    /**
     * May code in $layer reference $target? Its own layer always (a layer contains its nested
     * namespaces), else one of the namespaces it declared in `mayUse`.
     */
    private function mayReference(string $layer, string $target): bool
    {
        if (ClassName::within($target, $layer)) {
            return true;
        }

        foreach ($this->layers[$layer] as $allowed) {
            if (ClassName::within($target, trim($allowed, '\\'))) {
                return true;
            }
        }

        return false;
    }
}
