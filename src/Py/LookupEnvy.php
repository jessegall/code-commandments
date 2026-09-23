<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;

/**
 * Indirect feature envy — a method that uses an object's identity as a key to fetch a fact about it through
 * one of its own collaborators, `self.registry.get(node.key).reserved`: the object is being treated as a key
 * into its own data, so the fact belongs on it. The Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Ast\Support\LookupEnvy}.
 */
final readonly class LookupEnvy
{
    /**
     * The types a fact comes back as — a plain answer, not an object to go on working with.
     */
    private const array FACT_TYPES = ['bool', 'int', 'float', 'str', 'list', 'dict', 'set', 'tuple', 'frozenset'];

    public function __construct(private Codebase $codebase) {}

    /**
     * Does $method, written in $module, fetch a fact about its one owned parameter by that parameter's key?
     */
    public function isEnvious(FunctionDef $method, ModuleFile $module): bool
    {
        $class = $module->boundClassOf($method);

        if ($class->isNone() || ! in_array($method->returnedTypeName(), self::FACT_TYPES, true)) {
            return false;
        }

        $self = $method->params[0]->name;
        $owned = $this->codebase->ownedParameters($method, $class->unwrap());

        if (count($owned) !== 1 || ! $this->usedOnlyThroughAttributes($method, $module, $owned[0])) {
            return false;
        }

        return array_any($method->returnedValues(), fn (Expr $returned): bool => array_any(
            $returned->flatten(),
            fn (Expr $read) => $this->readsAFetchKeyedBy($read, $self, $owned[0], $module),
        ));
    }

    /**
     * Is every mention of $name in $method an attribute read off it? Handed on whole — an argument, a
     * return — it is a subject the method works with, not a key it looks things up by.
     */
    private function usedOnlyThroughAttributes(FunctionDef $method, ModuleFile $module, string $name): bool
    {
        $mentions = array_filter($module->expressionsIn($method), static fn (Expr $read): bool => $read->is(ExprKind::Name) && $read->get('name') === $name);

        return $mentions !== [] && array_all($mentions, static fn (Expr $read): bool => $module->wrapperOf($read)->isSomeAnd(
            static fn (Expr $around): bool => $around->is(ExprKind::Attribute) && $around->get('object') === $read,
        ));
    }

    /**
     * Is $read an attribute of a fetch made on one of $self's collaborators — `self.registry.get(…)` or
     * `self.specs[…]`, never the class's own method — keyed by an attribute of $name that is no enum? Keyed
     * by an enum field, the fetch picks a strategy for a whole family of objects, which is dispatch.
     */
    private function readsAFetchKeyedBy(Expr $read, string $self, string $name, ModuleFile $module): bool
    {
        if (! $read->is(ExprKind::Attribute)) {
            return false;
        }

        $fetch = $read->get('object');
        [$store, $keys] = match ($fetch->kind) {
            ExprKind::Call => [$fetch->get('callee')->is(ExprKind::Attribute) ? $fetch->get('callee')->get('object') : null, $fetch->get('arguments')],
            ExprKind::Subscript => [$fetch->get('object'), [$fetch->get('index')]],
            default => [null, []],
        };

        if ($store === null || ! $store->is(ExprKind::Attribute) || $store->rootName() !== $self) {
            return false;
        }

        $reads = array_filter(array_merge([], ...array_map(static fn (Expr $key) => $key->flatten(), $keys)), static fn (Expr $part): bool => $part->is(ExprKind::Attribute)
            && $part->get('object')->is(ExprKind::Name)
            && $part->get('object')->get('name') === $name);

        return $reads !== [] && ! array_all($reads, fn (Expr $key) => $this->isEnumField($key, $module));
    }

    /**
     * Does mypy type $key as a member of an enum the codebase declares?
     */
    private function isEnumField(Expr $key, ModuleFile $module): bool
    {
        return $this->codebase->types()->at($module->file, $key->start, $key->end)
            ->andThen(static fn (Type $type) => $type->className())
            ->isSomeAnd(fn (string $class): bool => $this->codebase->enums()->isEnum(array_last(explode('.', $class))));
    }
}
