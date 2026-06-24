<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\Archetype;
use JesseGall\CodeCommandments\Support\RegistryShape;
use JesseGall\CodeCommandments\Support\RoleInference;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Role-vs-behaviour incoherence (#134): a class whose NAME/ROLE declares one job
 * but whose BODY is a second engine. The generalisation of
 * {@see RegistryNamingHonestyProphet} / {@see RegistryPatternProphet} /
 * {@see RegistryBaseBypassProphet} ("the name should match the single job")
 * beyond registries to every role the codebase names (`*Registry`, `*Data`/DTO,
 * `*Resolver`/`*Factory`, …).
 *
 *     // a *Registry (store + lookup) that is really a reflection compiler
 *     use ReflectionClass;
 *
 *     class DefinitionRegistry          // ← role: registry
 *     {
 *         private array $definitions = [];
 *         public function register(string $k, $v): void { $this->definitions[$k] = $v; }
 *         public function get(string $k) { return $this->definitions[$k]; }
 *         private function reflect(string $class): array { return (new ReflectionClass($class))->...; }
 *         // ~28 reflect.../extract... methods — a second job
 *     }
 *
 * GENERIC BY DESIGN. The backbone is framework-agnostic STRUCTURAL/AST signals —
 * it must produce sensible results on a plain-PHP, non-Laravel, non-Spatie
 * codebase:
 *   - reflection use detected GENERICALLY by the `Reflection*` class-name family
 *     (a native-PHP namespace), never a Laravel/Spatie import;
 *   - store-shape via the name-free {@see RegistryShape} detector;
 *   - assembler-method CLUSTERS (>= 2 of the make, build, assemble, hydrate, load,
 *     compile prefixes) for the DTO role;
 *   - constructor SERVICE injection inferred from the AST (a non-readonly,
 *     non-enum, non-Data class param with no default = a service dep);
 *   - verb-cluster diversity (>= 2 distinct FOREIGN prefix families).
 * Framework-specific collaborators (`Illuminate\*`, `Spatie\*`, `GuzzleHttp`,
 * `PDO`, `DOMDocument`) survive ONLY as OPTIONAL, config-overridable DEFAULTS in
 * the role catalogue — they are NEVER the sole or dominant trigger. Remove them
 * from the config and the prophet still fires on the generic structural signals.
 *
 * It does NOT try to "measure SRP" (undecidable, FP-prone). It detects
 * INCOHERENCE the way the existing registry rules do — MARKER-driven + explicit
 * exemptions (the same discipline as RegistryReturnContract): a class carries a
 * ROLE (by name suffix, base class, interface, or attribute) AND its body shows
 * a STRUCTURAL second-engine signal for that role. The marker GATES; the
 * structural signal is the sharp, low-FP trigger.
 *
 * Advisory, never a sin; not auto-fixable — extraction is a design call (same
 * stance as RegistryPattern / PreferTotalOverNullable). The finding SUGGESTS the
 * cut ("extract the reflection into a *Reflector collaborator"), never rewrites.
 *
 *
 *
 *
 * @method-generated-start
 * @method static exemptAttributes(array $value)
 * @method static exemptBases(array $value)
 * @method static exemptSuffixes(array $value)
 * @method static minVerbFamilies(int $value)
 * @method static roles(mixed $value)
 * @method-generated-end
 */
#[IntroducedIn('2.5.0')]
class OutOfPurposeProphet extends PhpCommandment
{
    /**
     * The default role catalogue. Each role declares:
     *  - markers: how the role is recognised — name `suffix`es, `base` class
     *    short-name fragments, `interface` short-name fragments, and `attribute`
     *    short names. ANY match marks the class with that role. (GENERIC — pure
     *    naming/structure, no framework knowledge.)
     *  - forbidden: short collaborator names (imported, `new X`, or `X::`) the
     *    role has no business with. These are OPTIONAL, framework-specific
     *    DEFAULTS — sharpeners, never the backbone. Reflection is detected
     *    GENERICALLY (the `Reflection*` class family) regardless of this list, so
     *    the registry role still fires on plain PHP with the list emptied.
     *  - forbidden_namespaces: namespace prefixes (e.g. `Spatie\\…`,
     *    `Illuminate\\…`, `GuzzleHttp`) whose members are forbidden collaborators
     *    — also OPTIONAL framework-specific defaults, config-overridable away.
     *
     * Config-overridable wholesale via the `roles` key.
     *
     * `verbs` are the role's OWN legitimate method-verb families — excluded from
     * the secondary verb-cluster signal so a cohesive registry whose methods are
     * `findByName`/`registerMany` (its own job) never trips the multiple-jobs
     * heuristic; only FOREIGN verb families count toward it.
     *
     * NOTE: the `pipe` role is deliberately ABSENT. A pipe whose handle() step IS
     * reflection/DOM/parsing (a static-analysis pipe) is perfectly on-purpose, so
     * an import-based forbidden list over-fires; and the verb-cluster signal could
     * not be made low-FP enough on a single-method-interface role to ship in v1.
     *
     * @var array<string, array{markers: array{suffix?: list<string>, base?: list<string>, interface?: list<string>, attribute?: list<string>}, forbidden: list<string>, forbidden_namespaces: list<string>, verbs: list<string>, second_job: string, cut: string}>
     */
    private const DEFAULT_ROLES = [
        'registry' => [
            'markers' => [
                'suffix' => ['Registry', 'Map', 'Catalog'],
                'base' => ['Registry'],
                'interface' => ['Registry'],
                'attribute' => ['Registry'],
            ],
            // Reflection is caught GENERICALLY (the Reflection* family) even with
            // this list empty. These are OPTIONAL framework-specific sharpeners.
            'forbidden' => [
                'DOMDocument', 'PDO',
            ],
            'forbidden_namespaces' => [
                'Spatie\\StructureDiscoverer',
                'Illuminate\\Http',
                'Illuminate\\Database',
                'GuzzleHttp',
            ],
            'verbs' => ['register', 'add', 'put', 'set', 'find', 'get', 'has', 'all', 'keys', 'values', 'remove', 'forget', 'count', 'each', 'map', 'first', 'lookup', 'resolve'],
            'second_job' => 'reflection/compilation/discovery or I/O',
            'cut' => 'Extract it into a dedicated collaborator (e.g. a *Reflector / *Discoverer) and keep the registry a store + lookup.',
        ],
        'data' => [
            'markers' => [
                'suffix' => ['Data'],
                'base' => ['Data'],
                'interface' => [],
                'attribute' => [],
            ],
            // The data role does NOT trigger on imports — a DTO that references a
            // type is fine. It fires only on a STRUCTURAL assembler signal (a
            // builder-method cluster, optionally + an injected service). The list
            // stays empty so no single import can flag a pure payload.
            'forbidden' => [],
            'forbidden_namespaces' => [],
            'verbs' => ['get', 'is', 'has', 'with', 'to', 'from', 'as'],
            'second_job' => 'assembling/hydrating itself (a builder/assembler engine, not a payload)',
            'cut' => 'Move the assembly into a dedicated factory/assembler and keep the DTO a pure payload constructed via ::from().',
        ],
        'set' => [
            'markers' => [
                'suffix' => ['Set'],
                'base' => ['Set'],
                'interface' => ['Set'],
                'attribute' => ['Set'],
            ],
            // A set is a membership store + iteration. Like a registry, it must not
            // reflect, discover, or do I/O on the side — that is a second engine.
            // Reflection is caught GENERICALLY (the Reflection* family) even with
            // this list empty; these are OPTIONAL framework-specific sharpeners.
            'forbidden' => [
                'DOMDocument', 'PDO',
            ],
            'forbidden_namespaces' => [
                'Spatie\\StructureDiscoverer',
                'Illuminate\\Http',
                'Illuminate\\Database',
                'GuzzleHttp',
            ],
            'verbs' => ['add', 'all', 'values', 'has', 'contains', 'count', 'each', 'map', 'filter', 'remove', 'forget', 'clear', 'first', 'keys', 'merge', 'with'],
            'second_job' => 'reflection/compilation/discovery or I/O',
            'cut' => 'Extract it into a dedicated collaborator (e.g. a *Discoverer / *Reflector) and keep the set a membership store + iteration.',
        ],
        'resolver' => [
            'markers' => [
                'suffix' => ['Resolver', 'Factory'],
                'base' => ['Resolver', 'Factory'],
                'interface' => ['Resolver', 'Factory'],
                'attribute' => [],
            ],
            // A resolver/factory DERIVES on demand; it must not own a registration store.
            'forbidden' => [],
            'forbidden_namespaces' => [],
            'verbs' => ['resolve', 'make', 'create', 'build', 'for', 'from', 'get'],
            'second_job' => 'a registration store (register/add into a keyed array) — that is a registry, not a resolver',
            'cut' => 'Split the store into a dedicated *Registry and have the resolver derive over it, or rename the class to *Registry.',
        ],
    ];

    /**
     * The native-PHP reflection class family, matched GENERICALLY by short-name
     * prefix (`ReflectionClass`, `ReflectionMethod`, `ReflectionEnum`, …) so the
     * registry role catches reflection without enumerating each class and WITHOUT
     * any framework knowledge. This is part of the framework-agnostic backbone,
     * not the config-overridable forbidden list.
     */
    private const REFLECTION_PREFIX = 'Reflection';

    /**
     * Builder-method verb families. A `*Data` DTO fires only when ONE of these
     * methods ACTUALLY USES a constructor-injected SERVICE (it pulls a
     * collaborator in to build itself — a factory's job). A bare cluster of these
     * methods that touch no injected engine is on-purpose (a view-model building
     * its own props) and does NOT fire. GENERIC — these are the universal
     * vocabulary of construction, not framework-specific.
     */
    private const ASSEMBLER_VERBS = ['make', 'build', 'assemble', 'hydrate', 'load', 'compile'];

    /**
     * Base short-name fragments that exempt a class outright: a service provider
     * legitimately imports a wide binding surface, and a fluent builder/DSL whose
     * builder IS its executor is cohesive by design.
     */
    private const DEFAULT_EXEMPT_BASES = ['ServiceProvider'];

    /** Name suffixes that exempt a class outright (e.g. a *ServiceProvider). */
    private const DEFAULT_EXEMPT_SUFFIXES = ['ServiceProvider'];

    /** Attribute short names that opt a class out. */
    private const DEFAULT_EXEMPT_ATTRIBUTES = ['OutOfPurposeExempt'];

    /** Min distinct FOREIGN verb-cluster families before the secondary signal can fire. */
    private const DEFAULT_MIN_VERB_FAMILIES = 2;

    /**
     * Minimum methods a foreign family must have to count as a genuine second
     * ENGINE (not just two incidental helpers). The secondary signal is the
     * weakest evidence, so it is deliberately demanding.
     */
    private const FOREIGN_FAMILY_MIN_METHODS = 3;

    /**
     * Generic helper verbs that are the universal grammar of ANY cohesive class
     * (a getter, a predicate, a small transform) — they are NOT a distinct job, so
     * they never count toward the foreign-verb-cluster signal. Without this, a
     * cohesive analyzer/pipe with `is*`/`get*`/`find*`/`collect*` helpers (its own
     * single job) would FALSELY read as "multiple engines". The sharp signal is the
     * forbidden collaborator; this keeps the secondary from being an FP machine.
     */
    private const GENERIC_HELPER_VERBS = [
        'is', 'has', 'are', 'can', 'should', 'was', 'were',
        'get', 'set', 'with', 'to', 'from', 'as', 'of', 'on', 'in', 'at', 'by',
        'find', 'fetch', 'read', 'load', 'collect', 'gather',
        'make', 'build', 'create', 'new', 'init',
        'resolve', 'detect', 'classify', 'describe', 'inspect', 'check', 'ensure', 'assert', 'validate',
        'add', 'append', 'push', 'put', 'remove', 'forget', 'clear',
        'map', 'filter', 'reduce', 'each', 'apply', 'walk', 'visit',
        'format', 'normalize', 'normalise', 'sanitize', 'sanitise', 'clean', 'trim',
        'count', 'first', 'last', 'all', 'any', 'some', 'none', 'keys', 'values',
        'handle', 'process', 'run', 'do', 'call', 'invoke', 'execute',
        'param', 'name', 'type', 'node', 'enclosing', 'class', 'method', 'prop', 'arg', 'line', 'snippet',
    ];

    public function description(): string
    {
        return 'A class with a role marker (*Registry/*Data/*Resolver) whose body shows a structural second-engine signal (reflection in a registry, an assembler cluster in a DTO, a store in a resolver) is doing a second job — extract it';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A class carries a ROLE marker (name suffix / base class / interface / attribute — `*Registry`, `*Data`/DTO, `*Resolver`/`*Factory`) AND its body shows a STRUCTURAL second-engine signal for that role: a `*Registry` that reflects (any `Reflection*` class) or reaches INLINE for a configured forbidden collaborator (DOM/PDO/discovery/HTTP/DB), or whose methods spread across >= 2 foreign verb engines; a `Data` DTO that pulls a constructor-injected SERVICE in and ACTUALLY USES it inside a make*/build*/assemble*/hydrate*/load*/compile* method (a factory over an injected engine); a `*Resolver`/`*Factory` that owns a real REGISTRATION store (a public mutator writes `$this->store[$k] = $param` from a method parameter — a registry, not a resolver). The role declares one job and the body is a second engine.')
            ->leaveWhen('the class is a `ServiceProvider` (binding/const lists are legitimately import-heavy), a fluent builder/DSL whose builder IS its executor (methods return `self`/`$this`, e.g. a `Pipeline`), it is opted out via config `exclude` or a `#[OutOfPurposeExempt]` attribute, it is a pure-payload DTO that merely references/returns a type or a view-model that builds its OWN props with build*/load* methods touching no injected engine (NOT an assembler over a service), a `*Data` whose only injected ctor params are enums / payload collections (`DataCollection` / value-object holders — value-object state, not services), a resolver whose keyed writes only memoize a COMPUTED value (`$this->cache[$k] = $this->build($k)` — a cache, not a store), a class that merely TYPE-HINTS a forbidden class (a closure-param/return type, never used inline), a focused pipe/analyzer whose single job genuinely IS reflection/parsing, OR it has NO recognised role marker (nothing to be incoherent with).')
            ->whenUnsure('ask what the ROLE promises and whether the body keeps that promise. A registry stores + looks up — it does not reflect, scan, parse, or do I/O. A DTO is a payload — it does not pull a service in to assemble itself. A view-model building its own presentation props is fine; the smell is a builder that REACHES an injected collaborator. A memo (`$this->cache[$k] ??= compute()`) is on-purpose for a resolver; a register-store that assigns a PARAMETER is not. If the structural signal is real, extract it into a dedicated collaborator and keep the role coherent; the finding names the cut.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A class whose NAME/ROLE declares one job but whose BODY is a second engine is
"out of purpose". This is the same instinct as RegistryNamingHonesty /
RegistryPattern / RegistryBaseBypass — the name should match the single job —
generalised to every role the codebase names.

GENERIC BY DESIGN. The backbone is framework-AGNOSTIC STRUCTURAL/AST signals, so
the rule produces sensible results on a plain-PHP, non-Laravel, non-Spatie
codebase. Framework-specific collaborators (Illuminate\*, Spatie\*, GuzzleHttp,
PDO, DOMDocument) survive ONLY as OPTIONAL, config-overridable DEFAULTS in the
role catalogue — they are never the sole or dominant trigger.

It does NOT try to measure single-responsibility (undecidable, and a generic
"too many methods" rule is a false-positive machine — a fluent builder, a
provider's binding list, and a cohesive parser are all legitimately large).
Instead it detects INCOHERENCE the way the registry rules do: MARKER-driven +
explicit exemptions.

WHAT FIRES — a class carries a ROLE (by name suffix, base class, interface, or
attribute) AND its body shows a STRUCTURAL second-engine signal for that role:

  | Role (marker)                | Out-of-purpose when…                                          |
  |------------------------------|---------------------------------------------------------------|
  | *Registry / extends Registry | it REFLECTS (any native `Reflection*` class — generic), or    |
  |   / #[Registry]              |   reaches a configured forbidden default (DOMDocument, PDO,    |
  |                              |   Spatie\StructureDiscoverer, Illuminate\Http|Database,        |
  |                              |   GuzzleHttp), OR its methods spread across >= 2 foreign       |
  |                              |   verb engines.                                               |
  | *Data DTO / extends Data     | it is a genuine ASSEMBLER: a CLUSTER of make*/build*/assemble*/|
  |                              |   hydrate*/load*/compile* methods (optionally plus a          |
  |                              |   constructor-injected service). Merely referencing ONE       |
  |                              |   service or returning a type does NOT fire.                  |
  | *Resolver / *Factory         | it owns a registration STORE (register()/add() into a keyed   |
  |                              |   array — RegistryShape). That is a registry, not a resolver. |

Reflection is detected GENERICALLY by the `Reflection*` class-name family (a
native-PHP namespace) from the `use` import list PLUS `new X` / `X::` name nodes
— no framework knowledge needed. `A *Registry that uses ReflectionClass` is
almost always doing too much — very low FP.

SECONDARY (registry only; NEVER fires alone): VERB-CLUSTER diversity — the
registry's methods fall into >= 2 distinct FOREIGN prefix families (`reflect*` +
`hydrate*`), each backed by >= 3 methods. Multiple verb families on one noun =
multiple jobs. Size proxies are a pure TIE-BREAKER, never the trigger.

There is no `pipe` role: a pipe whose handle() step IS reflection/parsing is
on-purpose, so a forbidden-import list over-fires on static-analysis pipes.

WHAT DOES NOT FIRE (mandatory exemptions, same FP discipline as the registry
rules):
  * a ServiceProvider (extends *ServiceProvider / named *ServiceProvider) — its
    binding/const lists are legitimately import-heavy;
  * a fluent builder/DSL whose builder IS its executor — every (or nearly every)
    method returns self/$this (a Pipeline-like DSL);
  * a pure-payload DTO that merely references/returns a type (not an assembler);
  * a focused class whose single job genuinely IS reflection/parsing but has no
    role marker;
  * a class opted out via config `exclude` or a `#[OutOfPurposeExempt]` attribute;
  * a class with NO recognised role marker — there is nothing to be incoherent
    with.

Advisory — extraction is a design call. Not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];
        $aliases = $this->collectImportAliases($ast);

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null || $class->isAbstract()) {
                continue;
            }

            if ($this->isExempt($class)) {
                continue;
            }

            $roles = $this->rolesOf($class);

            if ($roles === []) {
                continue; // no role marker → nothing to be incoherent with
            }

            // Collaborators this class actually REACHES FOR — inline `new X` /
            // `X::` references only (which may be unqualified), each resolved to
            // its FQ name via the file's `use` aliases so a namespace-prefix
            // forbidden match still works. A type-hint-only import (e.g. a
            // closure-param/return type-hint on a value object) is NOT a reach-for
            // and must never count; only INLINE construction/static access does,
            // so a registry that merely TYPES a forbidden class in a signature
            // stays quiet.
            $collaborators = $this->resolveAliases($this->inlineReferences($class), $aliases);

            foreach ($roles as $roleName => $role) {
                $finding = $this->incoherence($class, $roleName, $role, $collaborators);

                if ($finding === null) {
                    continue;
                }

                $name = $class->name->toString();
                $warnings[] = $this->warningAt(
                    $class->getStartLine(),
                    sprintf(
                        'A %s that %s is doing a second job (%s). %s',
                        $this->roleLabel($roleName),
                        $finding,
                        $role['second_job'],
                        $role['cut'],
                    ),
                    $this->lineSnippet($content, $class->getStartLine()),
                    'out-of-purpose:' . $roleName . ':' . $name,
                );

                break; // one finding per class is enough to point at the cut
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * The clause describing WHY the class is out of purpose for $role, or null
     * when it is coherent. Each role has its OWN structural trigger; none leans on
     * a single import alone.
     *
     *  - resolver: owns a register()+keyed-array STORE (RegistryShape) — a registry.
     *  - registry: REFLECTS (generic `Reflection*` family) or reaches a configured
     *    forbidden default, OR (secondary) spreads across >= 2 foreign verb engines.
     *  - data: is a genuine ASSEMBLER — a cluster of builder methods (optionally
     *    plus a constructor-injected service). A pure payload never fires.
     *
     * @param  array{markers: array, forbidden: list<string>, forbidden_namespaces: list<string>, verbs: list<string>, second_job: string, cut: string}  $role
     * @param  list<string>  $collaborators  FQ or short collaborator names this class imports / references
     */
    private function incoherence(Node\Stmt\Class_ $class, string $roleName, array $role, array $collaborators): ?string
    {
        return match ($roleName) {
            'resolver' => $this->resolverIncoherence($class),
            'data' => $this->dataIncoherence($class),
            default => $this->collaboratorIncoherence($class, $role, $collaborators),
        };
    }

    /**
     * A resolver/factory whose "second job" is a registration STORE it owns — not
     * an import. The shape ({@see RegistryShape}) is necessary but NOT sufficient:
     * a MEMO/cache has the same write-then-read shape (`$this->cache[$k] =
     * $this->build($k)`) yet is on-purpose for a resolver. The discriminator is
     * the RHS of the keyed write: a real registration store assigns a method
     * PARAMETER value (`register($k, $item)` → `$this->store[$k] = $item`); a memo
     * assigns a COMPUTED value (a method call, a ternary, a `??=`). Only the
     * former is "a registry, not a resolver".
     */
    private function resolverIncoherence(Node\Stmt\Class_ $class): ?string
    {
        if (RegistryShape::detect($class) === null) {
            return null;
        }

        return $this->ownsRegistrationStore($class)
            ? 'owns a `register`/keyed-array store'
            : null;
    }

    /**
     * Whether a PUBLIC mutator writes its keyed store DIRECTLY from a method
     * PARAMETER (`$this->store[$k] = $item` where `$item` is one of the method's
     * params) — the registration signal. A class whose ONLY keyed writes assign a
     * computed expression (a method call / ternary / `??= compute()`) is a
     * cache/memo, not a registration store, and does NOT qualify. GENERIC — pure
     * AST, no name list.
     */
    private function ownsRegistrationStore(Node\Stmt\Class_ $class): bool
    {
        $finder = new NodeFinder;

        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic() || $method->isStatic() || $method->stmts === null) {
                continue;
            }

            $params = [];

            foreach ($method->params as $param) {
                if ($param->var instanceof Expr\Variable && is_string($param->var->name)) {
                    $params[$param->var->name] = true;
                }
            }

            if ($params === []) {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, Expr\Assign::class) as $assign) {
                if (! $assign->var instanceof Expr\ArrayDimFetch || $this->thisProp($assign->var) === null) {
                    continue;
                }

                if ($assign->expr instanceof Expr\Variable
                    && is_string($assign->expr->name)
                    && isset($params[$assign->expr->name])
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The store property name a `$this->prop[...]` dim-fetch targets, or null when
     * the node is not a keyed fetch off a `$this->` property.
     */
    private function thisProp(Expr\ArrayDimFetch $node): ?string
    {
        $var = $node->var;

        if ($var instanceof Expr\PropertyFetch
            && $var->var instanceof Expr\Variable
            && $var->var->name === 'this'
            && $var->name instanceof Node\Identifier
        ) {
            return $var->name->toString();
        }

        return null;
    }

    /**
     * A DTO is out of purpose only when it is a genuine ASSEMBLER that pulls a
     * COLLABORATOR in to build itself — a STRUCTURAL signal, sharp and low-FP:
     * a constructor-injected genuine SERVICE dep AND a builder method that
     * ACTUALLY USES that service (`$this->service->…` inside a make/build/…
     * method). That is a DTO doing a factory's job over an injected engine.
     *
     * A bare CLUSTER of builder methods is NO LONGER a finding on its own: a
     * presentation/view-model DTO legitimately hosts a cluster of build/load*
     * methods that produce its OWN props (Lazy closures, #[Computed]) without any
     * injected engine — the standalone cluster signal over-fired on those. A
     * pure payload that merely references/returns a type does NOT fire either.
     */
    private function dataIncoherence(Node\Stmt\Class_ $class): ?string
    {
        $service = $this->injectedService($class);

        if ($service === null) {
            return null;
        }

        // A genuine injected SERVICE (after injectedService excludes enums, payload
        // collections, and framework-populated params) AND a builder method that
        // ACTUALLY CONSUMES it (`$this->service->…` inside a make*/build*/… method)
        // is the "DTO orchestrates its own assembly" smell. Requiring real use is
        // what separates a true assembler from a self-resolving view-model that just
        // holds a dep for a prop hook (the WarehouseShowPage FP).
        $builder = $this->builderUsingProperty($class, $service['property']);

        if ($builder === null) {
            return null;
        }

        return sprintf('injects the service `%s` to assemble itself via %s()', $service['type'], $builder);
    }

    /**
     * The collaborator/verb path used by the registry role. PRIMARY trigger: it
     * REFLECTS (the generic `Reflection*` family — framework-agnostic) or reaches
     * a configured forbidden default. SECONDARY (registry only; never alone): its
     * methods spread across >= N foreign verb engines.
     *
     * @param  array{forbidden: list<string>, forbidden_namespaces: list<string>, verbs: list<string>}  $role
     * @param  list<string>  $collaborators
     */
    private function collaboratorIncoherence(Node\Stmt\Class_ $class, array $role, array $collaborators): ?string
    {
        $hit = $this->reflectionCollaborator($collaborators) ?? $this->forbiddenCollaborator($role, $collaborators);

        if ($hit !== null) {
            return sprintf('uses `%s`', $hit);
        }

        // SECONDARY (never alone — only behind the marker): the class's methods
        // spread across >= N distinct FOREIGN verb-cluster families — verb prefixes
        // that are NOT part of the role's own vocabulary, each backed by >= 3
        // methods. A registry's own `findByName`/`registerMany` verbs are excluded,
        // so a cohesive registry with no foreign engine does NOT fire; only a
        // genuine second engine (reflect* + hydrate*) does. Size alone is never the
        // trigger.
        $foreign = $this->foreignVerbFamilies($class, $role['verbs']);

        if (count($foreign) >= $this->minVerbFamilies()) {
            return sprintf(
                'spreads across %d foreign method-verb families (%s) unrelated to its role',
                count($foreign),
                implode(', ', array_map(static fn (string $f): string => $f . '*', array_slice($foreign, 0, 3))),
            );
        }

        return null;
    }

    /**
     * The first native-PHP reflection collaborator (the generic `Reflection*`
     * short-name family) the class reaches for INLINE, or null. This is the
     * framework-agnostic backbone for the registry role — it catches
     * `ReflectionClass`, `ReflectionMethod`, `ReflectionEnum`, … used inline as
     * `new ReflectionClass(...)` / `\ReflectionClass::...`, with no class list to
     * keep up to date and no Laravel/Spatie dependency. A type-hint-only import is
     * NOT a reach-for and is excluded upstream (only inline refs are passed in).
     *
     * @param  list<string>  $collaborators
     */
    private function reflectionCollaborator(array $collaborators): ?string
    {
        foreach ($collaborators as $collaborator) {
            $short = $this->shortName($collaborator);

            if (str_starts_with($short, self::REFLECTION_PREFIX) && $short !== self::REFLECTION_PREFIX) {
                return $short;
            }
        }

        return null;
    }

    /**
     * The first forbidden collaborator (by short name or namespace prefix) the
     * class reaches for INLINE, or null. These are OPTIONAL framework-specific
     * defaults, config-overridable away — the generic backbone is
     * {@see reflectionCollaborator}. Compared against both the alias-resolved FQ
     * name and its short name so an unqualified `new DOMDocument` is caught too.
     * Only INLINE references are passed in, so a type-hint-only import (a
     * closure-param/return type on a value object) never matches.
     *
     * @param  array{forbidden: list<string>, forbidden_namespaces: list<string>}  $role
     * @param  list<string>  $collaborators
     */
    private function forbiddenCollaborator(array $role, array $collaborators): ?string
    {
        foreach ($collaborators as $collaborator) {
            $short = $this->shortName($collaborator);

            if (in_array($short, $role['forbidden'], true)) {
                return $short;
            }

            $normalized = ltrim($collaborator, '\\');

            foreach ($role['forbidden_namespaces'] as $prefix) {
                $prefix = ltrim($prefix, '\\');

                if ($normalized === $prefix || str_starts_with($normalized, $prefix . '\\')) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /**
     * The roles a class carries, keyed by role name.
     *
     * TIER A (declared) — a role matches when ANY of its markers (name suffix /
     * base fragment / interface fragment / attribute) matches. A class can carry
     * more than one role; the first incoherent one is reported.
     *
     * TIER B (inferred, #135) — when the class declares NO `registry` marker but
     * its SHAPE fingerprints as an encapsulated keyed store ({@see RoleInference}
     * → {@see Archetype::StoreRegistry}: a keyed array written by a public mutator
     * AND read back by lookups on the same prop), it is given the `registry` role
     * structurally. This makes the registry incoherence checks (reflection /
     * forbidden collaborator / foreign verb clusters) fire on an UNMARKED store —
     * closing the #119 gap where a `ResourceRegistry` slipped because the marker
     * path needed a `*Registry`-style name. The store SHAPE alone is never a
     * finding: the incoherence trigger still needs a second engine, so a plain
     * unmarked store stays quiet. Tier B never re-adds a role already matched by a
     * Tier-A marker (that would just relabel it).
     *
     * @return array<string, array{markers: array, forbidden: list<string>, forbidden_namespaces: list<string>, second_job: string, cut: string, inferred?: bool}>
     */
    private function rolesOf(Node\Stmt\Class_ $class): array
    {
        $matched = [];

        foreach ($this->roles() as $roleName => $role) {
            if ($this->classMatchesRole($class, $role['markers'])) {
                $matched[$roleName] = $role;
            }
        }

        // Tier B: an UNMARKED class that structurally fingerprints as a store is
        // given the registry role so the registry incoherence checks apply.
        if (! isset($matched['registry']) && $this->inferredStore($class)) {
            $registry = $this->roles()['registry'] ?? null;

            if ($registry !== null) {
                $registry['inferred'] = true;
                $matched['registry'] = $registry;
            }
        }

        return $matched;
    }

    /**
     * Whether the class fingerprints as an encapsulated keyed store by its strong
     * Tier-B shape alone (#135) — markerless, framework-agnostic. The single gate
     * for promoting an UNMARKED class into the registry role.
     */
    private function inferredStore(Node\Stmt\Class_ $class): bool
    {
        // The store fingerprint alone also matches a MEMO/CACHE (`$this->cache[$k] =
        // $this->compute()`), which is NOT a registration store — it stores values it
        // DERIVED, not values registered from outside. Require a real registration
        // mutator (a public method whose keyed write assigns a PARAMETER) so a cache
        // /memoizing resolver is not promoted into the registry role.
        return RoleInference::infer($class)->archetype() === Archetype::StoreRegistry
            && $this->ownsRegistrationStore($class);
    }

    /**
     * @param  array{suffix?: list<string>, base?: list<string>, interface?: list<string>, attribute?: list<string>}  $markers
     */
    private function classMatchesRole(Node\Stmt\Class_ $class, array $markers): bool
    {
        $name = $class->name?->toString() ?? '';

        foreach ($markers['suffix'] ?? [] as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        if ($class->extends instanceof Node\Name) {
            $parent = $class->extends->getLast();

            foreach ($markers['base'] ?? [] as $base) {
                if (str_ends_with($parent, $base)) {
                    return true;
                }
            }
        }

        foreach ($class->implements as $interface) {
            $last = $interface->getLast();

            foreach ($markers['interface'] ?? [] as $needle) {
                if (str_ends_with($last, $needle)) {
                    return true;
                }
            }
        }

        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                foreach ($markers['attribute'] ?? [] as $needle) {
                    if ($attr->name->getLast() === $needle) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * A constructor-injected SERVICE dependency, defined STRUCTURALLY (not by a
     * name list): a PROMOTED constructor param typed as a single class that is
     *   - NOT a scalar / pseudo-type,
     *   - NOT a value-object payload (another `*Data`, an enum, or a payload
     *     collection — a DataCollection / Traversable / ArrayAccess / Collection
     *     holding value objects; resolved via reflection with an AST fallback),
     *   - NOT readonly (a readonly promoted param is value-object state, not a
     *     collaborator), and
     *   - has NO default (an injected service is required, not configured).
     * Such a param is a collaborator the DTO pulls in to assemble itself. Returns
     * `['type' => shortName, 'property' => promotedPropName]` or null. This is
     * purely AST/type-driven — no `*Service` suffix list.
     *
     * @return array{type: string, property: string}|null
     */
    private function injectedService(Node\Stmt\Class_ $class): ?array
    {
        $ctor = null;

        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === '__construct') {
                $ctor = $method;

                break;
            }
        }

        if ($ctor === null) {
            return null;
        }

        foreach ($ctor->params as $param) {
            // Only a PROMOTED param becomes class state the builders can reach
            // via `$this->prop`; a plain ctor arg is local and cannot be the
            // service a builder uses. (flags != 0 ⇒ a visibility/readonly modifier.)
            if ($param->flags === 0 || ! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $type = $param->type;

            // Unwrap a nullable/single-name; skip unions and scalars.
            if ($type instanceof Node\NullableType) {
                $type = $type->type;
            }

            if (! $type instanceof Node\Name) {
                continue;
            }

            $short = $type->getLast();

            if ($this->isScalarOrPseudo($short)) {
                continue;
            }

            // A nested value-object payload (another *Data), an enum (value-object
            // state, NOT a service), or a payload collection (DataCollection /
            // Traversable / ArrayAccess / Collection of value objects) is fine.
            if (str_ends_with($short, 'Data') || $this->isValueObjectParamType($type)) {
                continue;
            }

            // A readonly promoted param is value-object state; a defaulted param
            // is configured, not injected. Neither is a service collaborator.
            if ($this->isReadonlyParam($param) || $param->default !== null) {
                continue;
            }

            return ['type' => $short, 'property' => $param->var->name];
        }

        return null;
    }

    /**
     * Whether a constructor param's type is value-object state rather than a
     * service collaborator: an ENUM, or a PAYLOAD COLLECTION (Spatie
     * DataCollection or a Traversable / ArrayAccess / Collection-shaped holder of
     * value objects). Resolved GENERICALLY via reflection (`class_exists` +
     * `ReflectionClass` over the inheritance/interface chain) with an AST/name
     * fallback for unloadable types — never a hardcoded class list (the
     * `DataCollection` short-name and `Collection`/`Traversable`/`ArrayAccess`
     * interface names are the universal shape, not a framework whitelist).
     */
    private function isValueObjectParamType(Node\Name $type): bool
    {
        $fqcn = $this->typeFqcn($type);
        $short = $type->getLast();

        if (class_exists($fqcn) || interface_exists($fqcn) || enum_exists($fqcn)) {
            try {
                $reflection = new \ReflectionClass($fqcn);
            } catch (\Throwable) {
                return $this->isValueObjectByName($short);
            }

            if ($reflection->isEnum()) {
                return true;
            }

            foreach (['Traversable', 'ArrayAccess', 'IteratorAggregate', 'Iterator'] as $iface) {
                if ($reflection->implementsInterface($iface)) {
                    return true;
                }
            }

            // A `*Collection` / `*DataCollection` value-object holder that is not
            // loadable as Traversable still reads as a payload collection by name.
            return $this->isValueObjectByName($reflection->getShortName());
        }

        // Unloadable (test fixture / unscanned) — fall back to the name shape.
        return $this->isValueObjectByName($short);
    }

    /**
     * Name-shape fallback for {@see isValueObjectParamType} when the type is not
     * loadable: a `DataCollection` or any `*Collection` short name is a payload
     * collection. This is the LAST-resort fallback for unscanned code, not the
     * primary classifier (reflection is preferred above).
     */
    private function isValueObjectByName(string $short): bool
    {
        return $short === 'DataCollection' || str_ends_with($short, 'Collection');
    }

    /**
     * The fully-qualified name of a type node — prefers the name-resolver's
     * resolvedName attribute, falls back to the literal (FQ or as-written).
     */
    private function typeFqcn(Node\Name $type): string
    {
        $resolved = $type->getAttribute('resolvedName');

        if ($resolved instanceof Node\Name) {
            return ltrim($resolved->toString(), '\\');
        }

        return ltrim($type->toString(), '\\');
    }

    /**
     * The first builder method (an assembler-verb prefix: make/build/assemble/
     * hydrate/load/compile) that ACTUALLY USES the injected service property
     * (`$this->prop` anywhere in its body), or null. This is the sharp half of the
     * data-role trigger: a DTO that pulls a service in AND consumes it inside a
     * builder is doing a factory's job; a builder that ignores the prop (or a dep
     * held only for a `#[Computed]`/prop hook, as in a view-model) is not.
     * GENERIC — pure AST.
     */
    private function builderUsingProperty(Node\Stmt\Class_ $class, string $property): ?string
    {
        $finder = new NodeFinder;

        foreach ($class->getMethods() as $method) {
            $name = $method->name->toString();

            if (str_starts_with($name, '__') || $method->stmts === null) {
                continue;
            }

            $prefix = $this->verbPrefix($name);

            if ($prefix === null || ! in_array(strtolower($prefix), self::ASSEMBLER_VERBS, true)) {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, Expr\PropertyFetch::class) as $fetch) {
                if ($fetch->var instanceof Expr\Variable
                    && $fetch->var->name === 'this'
                    && $fetch->name instanceof Node\Identifier
                    && $fetch->name->toString() === $property
                ) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Whether a promoted constructor param is declared `readonly` (PhpParser
     * surfaces this via the param's modifier flags).
     */
    private function isReadonlyParam(Node\Param $param): bool
    {
        return ($param->flags & Node\Stmt\Class_::MODIFIER_READONLY) !== 0;
    }

    /**
     * The distinct FOREIGN verb-cluster families of a class's methods: the
     * leading lowercase-verb prefix of each method name (the run of letters up to
     * the first uppercase, e.g. `reflectClass` → `reflect`, `makeTriggerPipe` →
     * `make`), EXCLUDING (a) the role's own legitimate verbs ($roleVerbs) and (b)
     * the GENERIC helper verbs that appear in every cohesive class (is/get/find/
     * collect/…). A family must be backed by >= FOREIGN_FAMILY_MIN_METHODS methods
     * so a couple of incidental helpers is not a "job". What remains is a genuine
     * second engine (`reflect*` + `discover*` + `hydrate*`), not a cohesive
     * analyzer's helper grammar. Returns the foreign family names, most-populous
     * first.
     *
     * @param  list<string>  $roleVerbs
     * @return list<string>
     */
    private function foreignVerbFamilies(Node\Stmt\Class_ $class, array $roleVerbs): array
    {
        $excluded = array_map('strtolower', array_merge($roleVerbs, self::GENERIC_HELPER_VERBS));
        $counts = [];

        foreach ($class->getMethods() as $method) {
            $name = $method->name->toString();

            if (str_starts_with($name, '__')) {
                continue;
            }

            $prefix = $this->verbPrefix($name);

            if ($prefix === null || in_array(strtolower($prefix), $excluded, true)) {
                continue; // the role's own vocabulary / universal helper grammar is not a foreign job
            }

            $counts[$prefix] = ($counts[$prefix] ?? 0) + 1;
        }

        // A foreign family must be a real engine: >= FOREIGN_FAMILY_MIN_METHODS
        // methods. A lone (or paired) verb is not a second job.
        $families = array_filter($counts, static fn (int $n): bool => $n >= self::FOREIGN_FAMILY_MIN_METHODS);

        arsort($families);

        return array_keys($families);
    }

    /**
     * The leading verb of a camelCase method name — the run of leading
     * lowercase letters (`reflectClass` → `reflect`). null when the name has no
     * lowercase-then-uppercase boundary (a single-word getter like `all`,
     * `keys`, `register` collapses to itself and is treated as its own family,
     * which is fine — they still need a SECOND family to fire).
     */
    private function verbPrefix(string $name): ?string
    {
        if ($name === '' || ! ctype_lower($name[0])) {
            return null;
        }

        $prefix = '';

        foreach (str_split($name) as $char) {
            if (ctype_lower($char)) {
                $prefix .= $char;

                continue;
            }

            break;
        }

        return $prefix === '' ? null : $prefix;
    }

    /**
     * Whether the class is exempt outright: a service provider (base or name), a
     * fluent builder/DSL whose builder IS its executor (every concrete method
     * returns self/$this), config `exclude`, or an opt-out attribute.
     */
    private function isExempt(Node\Stmt\Class_ $class): bool
    {
        if ($this->extendsExemptBase($class) || $this->nameIsExemptSuffix($class) || $this->hasExemptAttribute($class)) {
            return true;
        }

        return $this->isFluentBuilder($class);
    }

    private function extendsExemptBase(Node\Stmt\Class_ $class): bool
    {
        if (! $class->extends instanceof Node\Name) {
            return false;
        }

        $parent = $class->extends->getLast();

        foreach ($this->exemptBases() as $fragment) {
            if (str_ends_with($parent, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function nameIsExemptSuffix(Node\Stmt\Class_ $class): bool
    {
        $name = $class->name?->toString() ?? '';

        foreach ($this->exemptSuffixes() as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function hasExemptAttribute(Node\Stmt\Class_ $class): bool
    {
        $attributes = $this->exemptAttributes();

        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if (in_array($attr->name->getLast(), $attributes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A fluent builder/DSL whose builder IS its executor: it has >= 2 concrete
     * public methods AND every one of them returns `self`/`static`/`$this` (or
     * the class itself). A `Pipeline`-like DSL is cohesive by design — the very
     * "second engine" shape would otherwise misfire on it.
     */
    private function isFluentBuilder(Node\Stmt\Class_ $class): bool
    {
        $selfShort = strtolower($class->name?->toString() ?? '');
        $concrete = 0;
        $fluent = 0;

        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic() || $method->isStatic() || $method->stmts === null) {
                continue;
            }

            $name = $method->name->toString();

            if (str_starts_with($name, '__')) {
                continue;
            }

            $concrete++;

            if ($this->returnsSelf($method, $selfShort)) {
                $fluent++;
            }
        }

        return $concrete >= 2 && $fluent === $concrete;
    }

    private function returnsSelf(Node\Stmt\ClassMethod $method, string $selfShort): bool
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Node\Identifier) {
            $lower = strtolower($type->toString());

            if ($lower === 'self' || $lower === 'static') {
                return true;
            }
        }

        if ($type instanceof Node\Name && (strtolower($type->getLast()) === $selfShort || strtolower($type->toString()) === 'self' || strtolower($type->toString()) === 'static')) {
            return true;
        }

        // No declared self return type — fall back to `return $this;` in the body.
        foreach ((new NodeFinder)->findInstanceOf($method->stmts ?? [], Node\Stmt\Return_::class) as $return) {
            if ($return->expr instanceof Expr\Variable && $return->expr->name === 'this') {
                return true;
            }
        }

        return false;
    }

    /**
     * Class names referenced inline via `new X(...)` or `X::...` — the only
     * "reach-for" signal the collaborator checks count, so a type-hint-only
     * import (a closure-param/return type) never registers as a collaborator.
     * An unqualified `new ReflectionClass(...)` (or a `\ReflectionClass::...`)
     * registers even when not imported.
     *
     * @return list<string>
     */
    private function inlineReferences(Node\Stmt\Class_ $class): array
    {
        $names = [];
        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($class->stmts, Expr\New_::class) as $new) {
            if ($new->class instanceof Node\Name) {
                $names[] = $new->class->toString();
            }
        }

        foreach ($finder->findInstanceOf($class->stmts, Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name) {
                $names[] = $call->class->toString();
            }
        }

        foreach ($finder->findInstanceOf($class->stmts, Expr\ClassConstFetch::class) as $fetch) {
            if ($fetch->class instanceof Node\Name) {
                $names[] = $fetch->class->toString();
            }
        }

        return $names;
    }

    /**
     * The file's `use` aliases as `short-alias => fully-qualified` — used to
     * resolve an inline reference written with its short name (`Discover::in()`)
     * back to the imported FQ class (`Spatie\StructureDiscoverer\Discover`) so a
     * namespace-prefix forbidden match still works WITHOUT counting the import
     * itself as a reach-for (only the inline use is).
     *
     * @param  array<Node>  $ast
     * @return array<string, string>
     */
    private function collectImportAliases(array $ast): array
    {
        $aliases = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Use_::class) as $use) {
            foreach ($use->uses as $useUse) {
                $fqcn = $useUse->name->toString();
                $aliases[$useUse->getAlias()->toString()] = $fqcn;
            }
        }

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\GroupUse::class) as $group) {
            $prefix = $group->prefix->toString();

            foreach ($group->uses as $useUse) {
                $fqcn = $prefix . '\\' . $useUse->name->toString();
                $aliases[$useUse->getAlias()->toString()] = $fqcn;
            }
        }

        return $aliases;
    }

    /**
     * Resolve each inline reference to its FQ name via the file's `use` aliases:
     * a fully-qualified name (`\ReflectionClass`) is kept as-is; an
     * unqualified/aliased name whose first segment matches an alias is rewritten
     * to the imported FQ (`Discover` → `Spatie\StructureDiscoverer\Discover`);
     * anything else is left as written.
     *
     * @param  list<string>  $references
     * @param  array<string, string>  $aliases
     * @return list<string>
     */
    private function resolveAliases(array $references, array $aliases): array
    {
        $resolved = [];

        foreach ($references as $reference) {
            if (str_starts_with($reference, '\\')) {
                $resolved[] = ltrim($reference, '\\');

                continue;
            }

            $segments = explode('\\', $reference);
            $first = $segments[0];

            if (isset($aliases[$first])) {
                $segments[0] = $aliases[$first];
                $resolved[] = implode('\\', $segments);

                continue;
            }

            $resolved[] = $reference;
        }

        return $resolved;
    }

    private function roleLabel(string $roleName): string
    {
        return match ($roleName) {
            'registry' => '*Registry',
            'set' => '*Set',
            'data' => '*Data DTO',
            'resolver' => '*Resolver/*Factory',
            default => $roleName,
        };
    }

    /**
     * @return array<string, array{markers: array, forbidden: list<string>, forbidden_namespaces: list<string>, second_job: string, cut: string}>
     */
    private function roles(): array
    {
        $configured = $this->config('roles', null);

        if (! is_array($configured) || $configured === []) {
            return self::DEFAULT_ROLES;
        }

        $roles = [];

        foreach ($configured as $name => $role) {
            if (! is_array($role)) {
                continue;
            }

            $roles[(string) $name] = [
                'markers' => is_array($role['markers'] ?? null) ? $role['markers'] : [],
                'forbidden' => is_array($role['forbidden'] ?? null) ? array_values(array_map('strval', $role['forbidden'])) : [],
                'forbidden_namespaces' => is_array($role['forbidden_namespaces'] ?? null) ? array_values(array_map('strval', $role['forbidden_namespaces'])) : [],
                'second_job' => isset($role['second_job']) ? (string) $role['second_job'] : 'work outside its role',
                'cut' => isset($role['cut']) ? (string) $role['cut'] : 'Extract it into a dedicated collaborator and keep the role coherent.',
            ];
        }

        return $roles === [] ? self::DEFAULT_ROLES : $roles;
    }

    /**
     * @return list<string>
     */
    private function exemptBases(): array
    {
        $value = $this->config('exempt_bases', self::DEFAULT_EXEMPT_BASES);

        return is_array($value) && $value !== [] ? array_values(array_map('strval', $value)) : self::DEFAULT_EXEMPT_BASES;
    }

    /**
     * @return list<string>
     */
    private function exemptSuffixes(): array
    {
        $value = $this->config('exempt_suffixes', self::DEFAULT_EXEMPT_SUFFIXES);

        return is_array($value) && $value !== [] ? array_values(array_map('strval', $value)) : self::DEFAULT_EXEMPT_SUFFIXES;
    }

    /**
     * @return list<string>
     */
    private function exemptAttributes(): array
    {
        $value = $this->config('exempt_attributes', self::DEFAULT_EXEMPT_ATTRIBUTES);

        return is_array($value) && $value !== [] ? array_values(array_map('strval', $value)) : self::DEFAULT_EXEMPT_ATTRIBUTES;
    }

    private function minVerbFamilies(): int
    {
        $value = $this->config('min_verb_families', self::DEFAULT_MIN_VERB_FAMILIES);

        return is_int($value) && $value >= 2 ? $value : self::DEFAULT_MIN_VERB_FAMILIES;
    }

    private function isScalarOrPseudo(string $name): bool
    {
        return in_array(strtolower($name), [
            'string', 'int', 'float', 'bool', 'array', 'object', 'mixed',
            'iterable', 'callable', 'void', 'never', 'null', 'true', 'false', 'parent', 'static', 'self',
        ], true);
    }

    private function shortName(string $fqcn): string
    {
        $fqcn = ltrim($fqcn, '\\');
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

}
