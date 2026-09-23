<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\IfStmt;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Py\Node\Raise;
use JesseGall\CodeCommandments\Py\Node\Return_;

/**
 * A function that unpacks its target from a container it was handed — `node = workflow.graph.node(node_id)`
 * — when the container is only packaging: used for nothing but that lookup. The Python twin of the
 * backend's {@see \JesseGall\CodeCommandments\Ast\Support\ParamResolution}.
 */
final readonly class ParamResolution
{
    /**
     * The scalar types that read as a lookup key — an identity, not a collaborator.
     */
    private const array KEY_TYPES = ['str', 'int'];

    public function __construct(private Codebase $codebase) {}

    /**
     * Does $function, written in $module, resolve a key parameter against a container parameter, keep the
     * result, use the container for nothing else, and do more with the result than hand it back?
     */
    public function unpacksTargetFromContainerParam(FunctionDef $function, ModuleFile $module): bool
    {
        $containers = $function->parameterNamesWhere($this->isContainer(...));
        $keys = $function->parameterNamesWhere(static fn (Param $param): bool => in_array($param->annotation?->dottedName(), self::KEY_TYPES, true));

        if ($containers === [] || $keys === []) {
            return false;
        }

        foreach ($function->soleAssignments() as $local => $lookup) {
            $container = $this->resolvedAgainst($lookup, $keys);

            if (! in_array($container, $containers, true) || $this->onlyResolves($function, $local)) {
                continue;
            }

            if ($this->isOnlyPackaging($function, $module, $container, $lookup) && $this->isOnlyAKey($function, $module, $lookup)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A parameter annotated with a class the codebase declares, not an enum — an enum carries behaviour,
     * and `rate.cents(grams)` is asking it, not digging in it.
     */
    private function isContainer(Param $param): bool
    {
        $type = $param->annotation?->dottedName() ?? '';
        $parts = explode('.', $type);

        return $type !== '' && $this->codebase->declaresClass($type) && ! $this->codebase->enums()->isEnum(end($parts));
    }

    /**
     * The name $lookup resolves one of $keys against — `workflow` in `workflow.graph.node(node_id)` and
     * `workflow.nodes[node_id]` — or empty when it is no single-key lookup. A call taking the key beside
     * other arguments is a query, not a resolution.
     *
     * @param  list<string>  $keys
     */
    private function resolvedAgainst(Expr $lookup, array $keys): string
    {
        [$from, $key] = match ($lookup->kind) {
            ExprKind::Call => $lookup->get('callee')->is(ExprKind::Attribute) && count($lookup->get('arguments')) === 1 ? [$lookup->get('callee')->get('object'), $lookup->get('arguments')[0]] : [null, null],
            ExprKind::Subscript => [$lookup->get('object'), $lookup->get('index')],
            default => [null, null],
        };

        if ($from === null || ! $key->is(ExprKind::Name) || ! in_array($key->get('name'), $keys, true)) {
            return '';
        }

        return $from->rootName();
    }

    /**
     * Is $function the resolver itself — the lookup, a guard raising the not-found failure, and $local handed
     * back? That is where the rule sends the resolution; a caller holding it passes the object because this
     * exists.
     */
    private function onlyResolves(FunctionDef $function, string $local): bool
    {
        $statements = $function->body->statementsBeyondText();
        $last = end($statements);

        if (count($statements) < 2 || ! $last instanceof Return_ || $last->value?->dottedName() !== $local) {
            return false;
        }

        return array_all(array_slice($statements, 1, -1), static fn (Node $between): bool => $between instanceof IfStmt
            && $between->else === null
            && array_all($between->body->body, static fn (Node $statement): bool => $statement instanceof Raise));
    }

    /**
     * Is the key $lookup resolves used for nothing else? A function that also reads the id itself — quotes
     * it in a message, stores it, hands it on — needs the id, and taking the resolved object would not do.
     */
    private function isOnlyAKey(FunctionDef $function, ModuleFile $module, Expr $lookup): bool
    {
        $key = $lookup->isCall() ? $lookup->get('arguments')[0] : $lookup->get('index');

        return array_all($module->expressionsIn($function), static fn (Expr $read): bool => $read === $key
            || ! $read->is(ExprKind::Name)
            || $read->get('name') !== $key->get('name'));
    }

    /**
     * Is $container used for nothing but $lookup — every other mention a plain attribute read? Handed on
     * whole, compared, or asked to do something, it is a co-subject the function needs, not packaging.
     */
    private function isOnlyPackaging(FunctionDef $function, ModuleFile $module, string $container, Expr $lookup): bool
    {
        $inLookup = array_map(spl_object_id(...), $lookup->flatten());
        $mentions = array_filter($module->expressionsIn($function), static fn (Expr $read): bool => $read->is(ExprKind::Name)
            && $read->get('name') === $container
            && ! in_array(spl_object_id($read), $inLookup, true));

        return array_all($mentions, static fn (Expr $read): bool => $module->wrapperOf($read)->isSomeAnd(
            static fn (Expr $around): bool => $around->is(ExprKind::Attribute)
                && $module->wrapperOf($around)->isNoneOr(static fn (Expr $outer): bool => ! ($outer->isCall() && $outer->get('callee') === $around)),
        ));
    }
}
