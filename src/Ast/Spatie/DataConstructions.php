<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

/**
 * Where — across the WHOLE codebase — every `Data` subclass is HYDRATED with `::from(...)`, and whether a
 * given property is HAND-BUILT in the source at each of those sites. A `#[WithCast]` / `Castable` fires
 * during `::from()` normalisation (NOT plain `new`, which just assigns) — so `::from()` is the only site
 * a cast could own. A property whose `::from()` source always carries a hand-built value
 * (`D::from(['price' => new Money(...)])` everywhere) re-does the `simple → complex` mapping at every call
 * site; a cast owns it once. The cross-file signal for
 * {@see \JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualInputCast}.
 *
 * Memoised per codebase like {@see \JesseGall\CodeCommandments\Ast\Support\TypeResolver}.
 */
final class DataConstructions
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    /** @var array<string, list<NodeMatch>>  Data FQCN => every `::from(...)` hydration site */
    private array $sites = [];

    private static ?\WeakMap $memo = null;

    private function __construct(private readonly Codebase $codebase)
    {
        $this->index();
    }

    public static function forCodebase(Codebase $codebase): self
    {
        self::$memo ??= new \WeakMap();

        return self::$memo[$codebase] ??= new self($codebase);
    }

    /**
     * Is property $prop (declared type $type) of Data class $fqcn HAND-BUILT in the source of EVERY one of
     * its `::from()` sites (and there is at least one)? A single site that feeds it cleanly — an opaque
     * `::from($model)`, a passed-through value, a property absent from that source — spares it: the mapping
     * isn't always manual there, so a cast isn't forced.
     */
    public function alwaysHandBuilt(string $fqcn, string $prop, string $type): bool
    {
        $sites = $this->sites[ltrim($fqcn, '\\')] ?? [];

        if ($sites === []) {
            return false;
        }

        foreach ($sites as $site) {
            $value = $this->sourceValueFor($site, $prop);

            if ($value === null || ! $this->isHandBuilt($value, $site, $type)) {
                return false;
            }
        }

        return true;
    }

    private function index(): void
    {
        foreach ($this->codebase->whereStaticCall('from')->get() as $call) {
            $fqcn = $this->constructedClass($call);

            if ($fqcn !== null && $this->codebase->extends($fqcn, self::DATA)) {
                $this->sites[$fqcn][] = $call;
            }
        }
    }

    /**
     * The Data class a `::from()` call constructs — resolving `self::from()` / `static::from()` (a common
     * shape for a named constructor) to the class the call sits in, not the literal `self`/`static`.
     */
    private function constructedClass(NodeMatch $call): ?string
    {
        $class = $call->staticCallClass();

        return in_array($class, ['self', 'static'], true) ? $call->enclosingClassName() : $class;
    }

    /**
     * The value feeding $prop in this `::from()` source — the `$prop` key of the source array literal
     * (resolving a local that holds that array). Null when the source can't be attributed per-property:
     * an opaque `D::from($model)`, or a source array without that key — either way not hand-mapped here.
     */
    private function sourceValueFor(NodeMatch $site, string $prop): ?Node
    {
        $source = $site->arguments()[0]->value ?? null;
        $array = $this->arrayLiteral($source, $site);

        if ($array === null) {
            return null;
        }

        foreach ($array->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === $prop) {
                return $item->value;
            }
        }

        return null;
    }

    /**
     * The array literal an expression IS, or that a local variable was last assigned in $site's function —
     * so `$data = ['price' => …]; D::from($data)` reads the same as an inline `D::from(['price' => …])`.
     */
    private function arrayLiteral(?Node $expr, NodeMatch $site): ?Array_
    {
        $expr = $expr instanceof Variable ? $this->resolveLocal($expr, $site) : $expr;

        return $expr instanceof Array_ ? $expr : null;
    }

    /**
     * Is this value CONSTRUCTED to produce the value object $type — `new VO(...)`, a named constructor on
     * the VO (`ValueBag::coalesce(...)`), an external factory whose return type IS the VO
     * (`MoneyMapper::make(): Money`), or an inline array flattened off one receiver — rather than an
     * existing value passed straight through? A construction FORM (`new`/static call/inline flatten) is
     * what separates hand-building from a passthrough; the produced-type match keeps it precise (a
     * `$model->price` read or a scalar helper is not building the VO). A local variable is followed one
     * hop to what it was assigned, so `$m = new Money(...); D::from(['price' => $m])` still counts.
     */
    private function isHandBuilt(Node $expr, NodeMatch $site, string $type): bool
    {
        $expr = $this->resolveLocal($expr, $site);

        if ($expr instanceof New_) {
            return $expr->class instanceof Name && $this->producesType($expr->class->toString(), $type);
        }

        if ($expr instanceof StaticCall && $expr->class instanceof Name) {
            if ($this->producesType($expr->class->toString(), $type)) {
                return true;
            }

            $returned = $this->returnedType($expr, $site);

            return $returned !== null && $this->producesType($returned, $type);
        }

        return $expr instanceof Array_ && AstNode::sharedFetchReceiver($expr) !== null;
    }

    /**
     * Does $class produce the value object $type — is it $type itself or a subclass? A named constructor
     * on the value object's own class (`ValueBag::coalesce`), or a `new`/factory of it, builds the VO.
     */
    private function producesType(string $class, string $type): bool
    {
        $class = ltrim($class, '\\');
        $type = ltrim($type, '\\');

        return $class === $type || $this->codebase->extends($class, $type);
    }

    /**
     * The declared return type of a static call, resolved through the receiver chain (so a
     * `MoneyMapper::make(): Money` external factory is recognised as producing `Money`), or null.
     */
    private function returnedType(StaticCall $call, NodeMatch $site): ?string
    {
        $function = $site->enclosingFunction();

        return $function === null
            ? null
            : TypeResolver::forCodebase($this->codebase)->typeOf($call, $function, $site->enclosingClassName());
    }

    /**
     * Follow a local variable one hop to the expression it was last assigned in $site's function — so a
     * value built a statement earlier still reads as hand-built. A variable with no local assignment (a
     * parameter, a passed-through value) is returned unchanged, so it reads as clean.
     */
    private function resolveLocal(Node $expr, NodeMatch $site): Node
    {
        if (! $expr instanceof Variable || ! is_string($expr->name)) {
            return $expr;
        }

        $function = $site->enclosingFunction();
        $resolved = $expr;

        foreach ($function === null ? [] : new NodeFinder()->findInstanceOf($function, Assign::class) as $assign) {
            if ($assign->var instanceof Variable && $assign->var->name === $expr->name) {
                $resolved = $assign->expr;
            }
        }

        return $resolved;
    }
}
