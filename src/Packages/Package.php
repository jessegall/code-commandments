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
 * A package declares its framework {@see boundaryTypes} and {@see contractMethods}; the
 * abstraction is deliberately open for more cross-detector policy as it's needed.
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

    /**
     * The framework CONTRACT methods this package introduces — each base class mapped to the
     * methods a subclass MUST declare to state its own contract, whose SHAPE and (array) RETURN the
     * framework dictates: a `FormRequest`'s `rules()`, an MCP tool's `schema()`, an Eloquent model's
     * `casts()`. A subclass can't parameterise them into one shared method or return a typed object
     * instead, so structural rules exempt them — near-duplicate ignores the shared skeleton,
     * array-return-bag ignores the mandated array — read from {@see Catalog::contractMethods},
     * never a name heuristic.
     *
     * @return array<class-string, list<string>>
     */
    public function contractMethods(): array
    {
        return [];
    }

    /**
     * The framework CONFIG base types this package introduces — classes whose WHOLE JOB is to hand
     * the framework arrays (a `FormRequest`, an MCP request/tool): `rules()`, `messages()`,
     * `attributes()`, `schema()` and any sibling the framework adds are all array-shaped by
     * contract, and a subclass can't type them away. So array-return-bag exempts a subclass's array
     * returns wholesale (unlike {@see contractMethods}, which names individual methods) — the
     * class-level exemption stays correct even for framework hooks a rule can't enumerate. Read
     * from {@see Catalog::arrayReturningTypes}.
     *
     * @return list<class-string>
     */
    public function arrayReturningTypes(): array
    {
        return [];
    }

    /**
     * The framework NO-CONTAINER types this package introduces — bases/contracts whose subclasses
     * the framework `new`-instantiates ITSELF, with no service container and no constructor DI: an
     * Eloquent attribute cast (`CastsAttributes`), which the framework hands the raw `$attributes`
     * array by key. There is nothing to inject, so a loose array/primitive parameter is the
     * framework's calling convention, not a bag the author chose — value-object rules exempt it.
     * Read from {@see Catalog::noContainerTypes}.
     *
     * @return list<class-string>
     */
    public function noContainerTypes(): array
    {
        return [];
    }
}
