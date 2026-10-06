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
 * ancestor, trait and member type of a listed declaration is listed too. A project with no autoloader still has PHP's
 * own classes (`Stringable`, `Countable`), so those are listed without one; nothing else is, so the bridge's own
 * php-parser never stands in for a class the project would load.
 */
final class OutsideSymbols
{
    /**
     * @var array<string, array>
     */
    private array $symbols = [];

    /**
     * @var array<string, UnreadableSymbol>
     */
    private array $unreadable = [];

    private readonly bool $internalOnly;

    /**
     * @param  array<string, string>  $declared  class-likes the scan declares, never reflected
     * @param  array<string, string>  $referenced  classes the scan names
     */
    public function __construct(?string $autoload, private readonly array $declared, array $referenced)
    {
        $this->internalOnly = $autoload === null;
        if ($autoload !== null) {
            require_once $autoload;
        }
        foreach ($referenced as $class) {
            $this->add($class);
        }
    }

    /**
     * @return list<array>
     */
    public function all(): array
    {
        return array_values($this->symbols);
    }

    /**
     * @return list<UnreadableSymbol>
     */
    public function unreadable(): array
    {
        return array_values($this->unreadable);
    }

    private function add(string $class): void
    {
        $key = strtolower(ltrim($class, '\\'));
        if (isset($this->symbols[$key]) || isset($this->declared[$key]) || ! $this->exists($class)) {
            return;
        }
        $reflection = new ReflectionClass($class);
        if ($this->internalOnly && ! $reflection->isInternal()) {
            return;
        }
        $symbol = ['symbol' => $reflection->getName(), 'kind' => $this->kind($reflection), 'name' => $reflection->getShortName()];
        $this->symbols[$key] = &$symbol;
        $parent = $reflection->getParentClass();
        $ancestry = [
            'extends' => match (true) {
                $reflection->isInterface() => $reflection->getInterfaceNames(),
                $parent === false => [],
                default => [$parent->getName()],
            },
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
        $members = [...$members, ...$this->documentedMethods($reflection, array_column($members, 'name'))];
        if ($members !== []) {
            $symbol['members'] = $members;
        }
        unset($symbol);
    }

    private function exists(string $class): bool
    {
        try {
            return class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
        } catch (\Throwable $failure) {
            $this->unreadable[strtolower(ltrim($class, '\\'))] = new UnreadableSymbol(ltrim($class, '\\'), $failure->getMessage());

            return false;
        }
    }

    /**
     * @return list<array>
     */
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

    /**
     * @return list<array>
     */
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

    /**
     * @return list<array>
     */
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

    /**
     * @return list<string>
     */
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

    /**
     * The methods the class's docblock declares with `@method` and the class does not, a facade's calls among them,
     * each with the type the tag documents it to return.
     *
     * @param  list<string>  $declared
     * @return list<array>
     */
    private function documentedMethods(ReflectionClass $reflection, array $declared): array
    {
        $doc = $reflection->getDocComment();
        if ($doc === false || ! preg_match_all('/@method\s+(static\s+)?([^\s(]+)\s+(\w+)\s*\(/', $doc, $tags, PREG_SET_ORDER)) {
            return [];
        }
        $members = [];
        foreach ($tags as [, $static, $returns, $name]) {
            if (in_array($name, $declared, true)) {
                continue;
            }
            $declared[] = $name;
            $members[] = [
                'symbol' => "{$reflection->getName()}::{$name}()",
                'name' => $name,
                'kind' => 'method',
                'modifiers' => trim($static) === '' ? ['public'] : ['public', 'static'],
                'documented' => $this->documentedType($returns, $reflection->getName()),
            ];
        }

        return $members;
    }

    /**
     * The type a docblock writes, as the class it is written in reads it: a name, a builtin, a union of them, or, for a
     * shape no reflected type has (`array<int, Order>`), its text alone.
     */
    private function documentedType(string $written, string $self): OutsideType
    {
        $parts = explode('|', $written);
        if (count($parts) > 1) {
            return new OutsideType($written, 'union', 'documented', members: array_map(fn (string $part) => $this->documentedType($part, $self), $parts));
        }
        $nullable = str_starts_with($written, '?');
        $name = ltrim($written, '?\\');
        if (in_array(strtolower($name), ['static', 'self', '$this'], true)) {
            $name = $self;
        }
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $name)) {
            return new OutsideType($written, 'opaque', 'documented', nullable: $nullable);
        }
        if (in_array(strtolower($name), OutsideType::BUILTINS, true)) {
            return new OutsideType($written, 'keyword', 'documented', strtolower($name), nullable: $nullable);
        }
        $this->add($name);

        return new OutsideType(($nullable ? '?' : '') . '\\' . $name, 'named', 'documented', $name, nullable: $nullable);
    }

    private function type(ReflectionType $type): OutsideType
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            $named = ! $type->isBuiltin() && ! in_array(strtolower($name), ['self', 'static', 'parent'], true);
            if ($named) {
                $this->add($name);
            }
            $nullable = $type->allowsNull() && ! in_array($name, ['null', 'mixed'], true);

            return new OutsideType(($nullable ? '?' : '') . ($named ? '\\' : '') . $name, $named ? 'named' : 'keyword', 'written', $name, nullable: $type->allowsNull());
        }
        $members = array_map($this->type(...), $type->getTypes());
        $joined = implode($type instanceof ReflectionUnionType ? '|' : '&', array_map(fn (OutsideType $member) => $member->text, $members));

        return new OutsideType($joined, $type instanceof ReflectionIntersectionType ? 'intersection' : 'union', 'written', members: $members, nullable: $type->allowsNull());
    }
}
