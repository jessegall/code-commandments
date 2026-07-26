<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\MemoisedPerCodebase;
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
 * Whole-codebase index of every `Data` subclass's `::from(...)` hydration sites, and whether a given
 * property is hand-built in the source at each. The cross-file signal for
 * {@see \JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualInputCast}. Memoised per codebase.
 */
final class DataConstructions
{
    use MemoisedPerCodebase;

    private const string DATA = 'Spatie\\LaravelData\\Data';

    /** @var array<string, list<NodeMatch>>  Data FQCN => every `::from(...)` hydration site */
    private array $sites = [];

    private function __construct(private readonly Codebase $codebase)
    {
        $this->index();
    }

    /**
     * Is property $prop (declared type $type) of Data class $fqcn hand-built in the source of EVERY one of
     * its `::from()` sites (and there is at least one)? A single site that feeds it cleanly spares it.
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
     * The Data class a `::from()` call constructs — resolving `self`/`static` to the enclosing class.
     */
    private function constructedClass(NodeMatch $call): ?string
    {
        $class = $call->staticCallClass();

        return in_array($class, ['self', 'static'], true) ? $call->enclosingClassName() : $class;
    }

    /**
     * The value feeding $prop in this `::from()` source — the `$prop` key of the source array literal
     * (resolving a local that holds it). Null when the source can't be attributed per-property.
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
     * The array literal an expression IS, or that a local variable was last assigned in $site's function.
     */
    private function arrayLiteral(?Node $expr, NodeMatch $site): ?Array_
    {
        $expr = $expr instanceof Variable ? $this->resolveLocal($expr, $site) : $expr;

        return $expr instanceof Array_ ? $expr : null;
    }

    /**
     * Is this value CONSTRUCTED to produce the value object $type — `new VO(...)`, a named constructor on
     * the VO, an external factory whose return type IS the VO, or an inline array flattened off one
     * receiver — rather than an existing value passed straight through? A local variable is followed one
     * hop to what it was assigned.
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
     * Does $class produce the value object $type — is it $type itself or a subclass?
     */
    private function producesType(string $class, string $type): bool
    {
        $class = ltrim($class, '\\');
        $type = ltrim($type, '\\');

        return $class === $type || $this->codebase->extends($class, $type);
    }

    /**
     * The declared return type of a static call, resolved through the receiver chain, or null.
     */
    private function returnedType(StaticCall $call, NodeMatch $site): ?string
    {
        $function = $site->enclosingFunction();

        return $function === null
            ? null
            : TypeResolver::forCodebase($this->codebase)->typeOf($call, $function, $site->enclosingClassName());
    }

    /**
     * Follow a local variable one hop to the expression it was last assigned in $site's function. A
     * variable with no local assignment is returned unchanged.
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
