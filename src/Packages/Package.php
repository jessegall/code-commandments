<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

/**
 * One third-party package's cross-cutting policy — the extension point where a package declares
 * facts that OTHER detectors must respect, so a general rule never hard-codes a framework's types.
 * Each package is its own class under `Packages/`, discovered by {@see Catalog} the way sins,
 * detectors, and skills are; a consumer's own `Packages\` class auto-enrols to teach the engine
 * about a framework the package doesn't ship for.
 *
 * Today a package declares its framework {@see boundaryTypes}; the abstraction is deliberately open
 * for more cross-detector policy as it's needed.
 */
abstract class Package
{
    /**
     * The framework ENTRY-POINT base types this package introduces — an HTTP request, an MCP
     * request/tool: classes a structural rule must treat as a boundary, not ordinary domain code.
     * A general detector (feature-envy, param-resolved-from-param) exempts these without ever
     * naming the framework — it reads them from {@see Catalog::boundaryTypes}.
     *
     * @return list<class-string>
     */
    public function boundaryTypes(): array
    {
        return [];
    }
}
