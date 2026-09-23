<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Config;

use JesseGall\CodeCommandments\Support\LayerStack;

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
        $this->layers[trim($namespace, self::SEPARATOR)] = array_values($mayUse);

        return $this;
    }

    /**
     * The declared layers, read in the spelling of the language the detector judges — its SEPARATOR and
     * whether its names are CASE_SENSITIVE, each a constant of the class using this.
     */
    private function stack(): LayerStack
    {
        return new LayerStack($this->layers, self::SEPARATOR, self::CASE_SENSITIVE);
    }
}
