<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use ReflectionClass;
use ReflectionClassConstant;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * The declarations the scan names but does not hold, reflected through the scanned project's autoloader. Closed: every
 * ancestor, trait and member type of a listed declaration is listed too.
 */
final class OutsideSymbols
{
    /** @var array<string, array> */
    private array $symbols = [];

    /**
     * @param  array<string, string>  $declared  class-likes the scan declares, never reflected
     * @param  array<string, string>  $referenced  classes the scan names
     */
    public function __construct(?string $autoload, private readonly array $declared, array $referenced)
    {
        if ($autoload === null) {
            return;
        }
        require_once $autoload;
        foreach ($referenced as $class) {
            $this->add($class);
        }
    }

    /** @return list<array> */
    public function all(): array
    {
        return array_values($this->symbols);
    }

    private function add(string $class): void
    {
        $key = strtolower(ltrim($class, '\\'));
        if (isset($this->symbols[$key]) || isset($this->declared[$key]) || ! $this->exists($class)) {
            return;
        }
        $reflection = new ReflectionClass($class);
        $symbol = ['symbol' => $reflection->getName(), 'kind' => $this->kind($reflection), 'name' => $reflection->getShortName()];
        $this->symbols[$key] = &$symbol;
        $parent = $reflection->getParentClass();
        $ancestry = [
            'extends' => $reflection->isInterface() ? $reflection->getInterfaceNames() : ($parent === false ? [] : [$parent->getName()]),
            'implements' => $reflection->isInterface() ? [] : $reflection->getInterfaceNames(),
            'uses' => $reflection->getTraitNames(),
        ];
        foreach (array_filter($ancestry) as $relation => $classes) {
            $symbol[$relation] = $classes;
            array_map($this->add(...), $classes);
        }
        $modifiers = array_keys(array_filter([
            'final' => $reflection->isFinal(),
            'abstract' => $reflection->isAbstract() && ! $reflection->isInterface() && ! $reflection->isTrait(),
            'readonly' => $reflection->isReadOnly(),
        ]));
        if ($modifiers !== []) {
            $symbol['modifiers'] = $modifiers;
        }
        $members = [...$this->constants($reflection), ...$this->properties($reflection), ...$this->methods($reflection)];
        if ($members !== []) {
            $symbol['members'] = $members;
        }
        unset($symbol);
    }

    private function exists(string $class): bool
    {
        try {
            return class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array> */
    private function methods(ReflectionClass $reflection): array
    {
        $members = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $member = ['symbol' => "{$reflection->getName()}::{$method->getName()}()", 'name' => $method->getName(), 'kind' => 'method'];
            $modifiers = $this->modifiers($method);
            if ($modifiers !== []) {
                $member['modifiers'] = $modifiers;
            }
            if ($method->hasReturnType()) {
                $member['returns'] = $this->type($method->getReturnType());
            }
            $parameters = [];
            foreach ($method->getParameters() as $parameter) {
                $entry = ['name' => $parameter->getName()];
                if ($parameter->hasType()) {
                    $entry['declared'] = $this->type($parameter->getType());
                }
                $flags = array_keys(array_filter(['variadic' => $parameter->isVariadic(), 'by-ref' => $parameter->isPassedByReference(), 'promoted' => $parameter->isPromoted()]));
                if ($flags !== []) {
                    $entry['flags'] = $flags;
                }
                $parameters[] = $entry;
            }
            if ($parameters !== []) {
                $member['parameters'] = $parameters;
            }
            $members[] = $member;
        }

        return $members;
    }

    /** @return list<array> */
    private function properties(ReflectionClass $reflection): array
    {
        $members = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $member = ['symbol' => "{$reflection->getName()}::\${$property->getName()}", 'name' => $property->getName(), 'kind' => 'property'];
            $modifiers = $this->modifiers($property);
            if ($modifiers !== []) {
                $member['modifiers'] = $modifiers;
            }
            if ($property->hasType()) {
                $member['declared'] = $this->type($property->getType());
            }
            $members[] = $member;
        }

        return $members;
    }

    /** @return list<array> */
    private function constants(ReflectionClass $reflection): array
    {
        $members = [];
        foreach ($reflection->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $member = ['symbol' => "{$reflection->getName()}::{$constant->getName()}", 'name' => $constant->getName(), 'kind' => 'constant'];
            $modifiers = $this->modifiers($constant);
            if ($modifiers !== []) {
                $member['modifiers'] = $modifiers;
            }
            if ($constant->hasType()) {
                $member['declared'] = $this->type($constant->getType());
            }
            $members[] = $member;
        }

        return $members;
    }

    /** @return list<string> */
    private function modifiers(ReflectionMethod|ReflectionProperty|ReflectionClassConstant $member): array
    {
        return array_keys(array_filter([
            'public' => $member->isPublic(),
            'protected' => $member->isProtected(),
            'private' => $member->isPrivate(),
            'static' => ! $member instanceof ReflectionClassConstant && $member->isStatic(),
            'abstract' => $member instanceof ReflectionMethod && $member->isAbstract(),
            'final' => ! $member instanceof ReflectionProperty && $member->isFinal(),
            'readonly' => $member instanceof ReflectionProperty && $member->isReadOnly(),
        ]));
    }

    private function kind(ReflectionClass $reflection): string
    {
        return match (true) {
            $reflection->isInterface() => 'interface',
            $reflection->isTrait() => 'trait',
            $reflection->isEnum() => 'enum',
            default => 'class',
        };
    }

    private function type(ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            $named = ! $type->isBuiltin() && ! in_array(strtolower($name), ['self', 'static', 'parent'], true);
            if ($named) {
                $this->add($name);
            }
            $nullable = $type->allowsNull() && ! in_array($name, ['null', 'mixed'], true);
            $out = ['text' => ($nullable ? '?' : '') . ($named ? '\\' : '') . $name, 'kind' => $named ? 'named' : 'keyword', 'name' => $name];
            if ($type->allowsNull()) {
                $out['nullable'] = true;
            }

            return $out + ['origin' => 'written'];
        }
        $members = array_map($this->type(...), $type->getTypes());
        $out = [
            'text' => implode($type instanceof ReflectionUnionType ? '|' : '&', array_column($members, 'text')),
            'kind' => $type instanceof ReflectionIntersectionType ? 'intersection' : 'union',
            'members' => $members,
        ];
        if ($type->allowsNull()) {
            $out['nullable'] = true;
        }

        return $out + ['origin' => 'written'];
    }
}
