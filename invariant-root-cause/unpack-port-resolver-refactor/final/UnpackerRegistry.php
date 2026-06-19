<?php

namespace JesseGall\Workflows\Workflow\Nodes;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use JesseGall\PhpTypes\T_Array;
use JesseGall\Workflows\Pipeline\Execution\AttributeResolver;
use JesseGall\Workflows\Pipeline\Execution\OutputSocket;
use JesseGall\Workflows\Pipeline\Execution\SocketReflector;
use JesseGall\Workflows\Pipeline\Execution\SocketSource;
use JesseGall\Workflows\Pipeline\Execution\WireType;
use JesseGall\Workflows\Support\Option;
use JesseGall\Workflows\Workflow\Attributes\ExcludeFromUnpack;
use JesseGall\Workflows\Workflow\Nodes\Resources\ModelFieldTypes;
use JesseGall\Workflows\Workflow\Unpacks\EloquentModelUnpacker;
use JesseGall\Workflows\Workflow\Unpacks\ModelRelationReflector;
use JesseGall\Workflows\Workflow\Unpacks\Unpacker;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Registry of unpackers: registers the unpacker bound to each domain class, and
 * answers what workflow-visible field ports a class exposes / reads their values.
 *
 * Renamed from `UnpackPortResolver` — it registers (`register`/`registerMany`/`all`)
 * and answers the canonical registry trio (`portsFor` find → `isUnpackable` has →
 * `requirePortsFor` get-or-throw), and the consuming code already referred to it as
 * `$unpackRegistry`. See the README "Naming / identity" note.
 *
 * Absence policy (invariant architecture):
 *   - portsFor()/extract() return Option — "maybe unpackable" is a GENUINE question
 *     (an opaque/scalar type legitimately has no field ports). Tolerant queries.
 *   - isUnpackable() is the has()-style companion.
 *   - requirePortsFor()/requireExtract() are the INVARIANT companions: for callers
 *     that have already established the type/value MUST be unpackable (the compiler
 *     after validation, the runtime when a field port is wired). They fail loud
 *     instead of silently yielding nothing.
 */
#[Singleton]
final class UnpackerRegistry
{

    /** @var array<class-string, class-string<Unpacker>> */
    private array $unpackers = T_Array::EMPTY;

    /** @var array<class-string, list<OutputSocket>> */
    private array $portsByClass = T_Array::EMPTY;

    public function __construct(
        private readonly SocketReflector $reflector,
        private readonly AttributeResolver $attributes = new AttributeResolver,
    ) {}

    /**
     * Register every unpacker class in the iterable.
     *
     * @param  iterable<class-string<Unpacker>>  $unpackerClasses
     */
    public function registerMany(iterable $unpackerClasses): void
    {
        foreach ($unpackerClasses as $class)
        {
            $this->register($class);
        }
    }

    /**
     * Register a single unpacker.
     *
     * @param  class-string<Unpacker>  $unpackerClass
     */
    public function register(string $unpackerClass): void
    {
        if (! is_subclass_of($unpackerClass, Unpacker::class))
        {
            throw InvalidUnpackerException::notAnUnpacker($unpackerClass);
        }

        $targetClass = $unpackerClass::forClass();

        if (array_key_exists($targetClass, $this->unpackers))
        {
            throw InvalidUnpackerException::collision($targetClass, $this->unpackers[$targetClass]);
        }

        $this->unpackers[$targetClass] = $unpackerClass;

        [, $outputs] = $this->reflector->reflectContextSockets($unpackerClass);
        $this->portsByClass[$targetClass] = $this->filterExcluded($unpackerClass, $outputs);
    }

    /**
     * Strip ports whose backing property carries {@see ExcludeFromUnpack}.
     *
     * @param  class-string<Unpacker>  $unpackerClass
     * @param  list<OutputSocket>  $ports
     * @return list<OutputSocket>
     */
    private function filterExcluded(string $unpackerClass, array $ports): array
    {
        $excluded = T_Array::empty();

        foreach (new ReflectionClass($unpackerClass)->getProperties() as $property)
        {
            if ($this->attributes->has($property, ExcludeFromUnpack::class))
            {
                $excluded[$property->getName()] = true;
            }
        }

        if (empty($excluded))
        {
            return $ports;
        }

        return collect($ports)
            ->reject(static fn (OutputSocket $port): bool => isset($excluded[$port->name]))
            ->values()
            ->all();
    }

    /**
     * Field ports for a domain class, or none when the class is not unpackable.
     *
     * GENUINE absence: opaque/scalar types are a valid thing to ask about and
     * legitimately expose no field ports. Tolerant query — callers that have
     * already proven the class is unpackable should use {@see requirePortsFor()}.
     *
     * @param  class-string  $class
     * @return Option<list<OutputSocket>>
     */
    public function portsFor(string $class): Option
    {
        $registered = $this->portsByClass[$class] ?? null;

        if ($registered !== null)
        {
            return Option::some($registered);
        }

        if (is_subclass_of($class, Model::class))
        {
            return Option::some($this->portsByClass[$class] = $this->modelSockets($class));
        }

        $reflected = $this->reflectedSockets($class);

        if (T_Array::isEmpty($reflected))
        {
            return Option::none();
        }

        return Option::some($this->portsByClass[$class] = $reflected);
    }

    /**
     * Whether a class exposes any unpack field ports — the has() companion to
     * portsFor()/requirePortsFor(). Use this to ASK; use requirePortsFor() when
     * the answer must be yes.
     *
     * @param  class-string  $class
     */
    public function isUnpackable(string $class): bool
    {
        return $this->portsFor($class)->hasValue();
    }

    /**
     * INVARIANT variant of portsFor(): the class MUST be unpackable here (e.g.
     * the wired type was already validated). A miss is an engine/registration
     * bug, so fail loud rather than hand back an empty port set.
     *
     * @param  class-string  $class
     * @return list<OutputSocket>
     */
    public function requirePortsFor(string $class): array
    {
        return $this->portsFor($class)
            ->getOrThrow(fn (): NotUnpackableException => NotUnpackableException::forClass($class));
    }

    /**
     * Reflect a plain object's public, non-static, non-excluded properties into unpack ports.
     *
     * @param  class-string  $class
     * @return list<OutputSocket>
     */
    private function reflectedSockets(string $class): array
    {
        $ports = T_Array::empty();

        foreach (new ReflectionClass($class)->getProperties(ReflectionProperty::IS_PUBLIC) as $property)
        {
            if ($property->isStatic() || $this->attributes->has($property, ExcludeFromUnpack::class))
            {
                continue;
            }

            $ports[] = OutputSocket::make(
                name: $property->getName(),
                // GENUINE default: an untyped/union property has no single wire
                // type, so MIXED is the right fallback — NOT an invariant.
                type: $this->reflectedSocketType($property)->getOr(WireType::MIXED),
                nullable: $this->reflectedSocketNullable($property),
                source: SocketSource::Context,
            );
        }

        return $ports;
    }

    /**
     * Map a property's declared type to a wire type, or none when it has none.
     *
     * @return Option<string>
     */
    private function reflectedSocketType(ReflectionProperty $property): Option
    {
        $type = $property->getType();

        if (! $type instanceof ReflectionNamedType)
        {
            return Option::none();
        }

        return match ($type->getName())
        {
            'int' => Option::some('int'),
            'bool' => Option::some('bool'),
            'float' => Option::some('float'),
            'string' => Option::some('string'),
            'array' => Option::some('array'),
            default => class_exists($type->getName()) ? Option::some($type->getName()) : Option::none(),
        };
    }

    /**
     * Whether a reflected property accepts null.
     */
    private function reflectedSocketNullable(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        return $type === null || $type->allowsNull();
    }

    /**
     * Reflect an Eloquent model's key + fillable fields into unpack ports.
     *
     * @param  class-string<Model>  $class
     * @return list<OutputSocket>
     */
    private function modelSockets(string $class): array
    {
        $instance = new $class;
        $casts = $instance->getCasts();

        $ports = [OutputSocket::from([
            'name' => $instance->getKeyName(),
            'type' => WireType::STRING,
            'nullable' => false,
            'source' => SocketSource::Context,
        ])];

        /** @var list<OutputSocket> $fillablePorts */
        $fillablePorts = OutputSocket::collect(array_map(
            static fn (string $field): array => [
                'name' => $field,
                'type' => ModelFieldTypes::portTypeForCast($casts[$field] ?? null),
                'nullable' => true,
                'source' => SocketSource::Context,
            ],
            $instance->getFillable(),
        ));

        $ports = [...$ports, ...$fillablePorts];

        foreach (ModelRelationReflector::relations($class) as $relation)
        {
            $ports[] = SocketFactory::outputForModelRelation($relation);
        }

        return $ports;
    }

    /**
     * Extract every registered field off an instance as a name-keyed map, or none
     * when the instance is not unpackable.
     *
     * GENUINE absence: an opaque object is a valid thing to hand in. Tolerant
     * query — runtime callers that have a wired field port and therefore REQUIRE
     * the value to be unpackable should use {@see requireExtract()}.
     *
     * @return Option<array<string, mixed>>
     */
    public function extract(object $instance): Option
    {
        $instanceClass = $instance::class;
        $unpackerClass = Arr::get($this->unpackers, $instanceClass);

        if (is_string($unpackerClass) && is_subclass_of($unpackerClass, Unpacker::class))
        {
            return Option::some($unpackerClass::unpack($instance));
        }

        if ($instance instanceof Model)
        {
            return Option::some(EloquentModelUnpacker::unpack($instance));
        }

        $values = $this->reflectValues($instance);

        return T_Array::isEmpty($values) ? Option::none() : Option::some($values);
    }

    /**
     * INVARIANT variant of extract(): the instance MUST be unpackable here (a
     * field port is wired downstream). Fail loud instead of silently producing
     * no fields — the failure mode that hides bad data in a loop/bridge.
     *
     * @return array<string, mixed>
     */
    public function requireExtract(object $instance): array
    {
        return $this->extract($instance)
            ->getOrThrow(fn (): NotUnpackableException => NotUnpackableException::forClass($instance::class));
    }

    /**
     * Read every public, non-static, non-excluded property off an instance as a name-keyed map.
     *
     * @return array<string, mixed>
     */
    private function reflectValues(object $instance): array
    {
        $values = T_Array::empty();

        foreach (new ReflectionClass($instance)->getProperties(ReflectionProperty::IS_PUBLIC) as $property)
        {
            if ($property->isStatic() || $this->attributes->has($property, ExcludeFromUnpack::class))
            {
                continue;
            }

            $values[$property->getName()] = $property->getValue($instance);
        }

        return $values;
    }

    /**
     * How many field ports a class exposes to Unpack — zero when not unpackable.
     *
     * LEGITIMATE Option + default: 0 is a valid answer to "how many ports?",
     * so getOr(EMPTY) is correct here — do NOT "fix" this into a throw.
     *
     * @param  class-string  $class
     */
    public function portCountFor(string $class): int
    {
        return count($this->portsFor($class)->getOr(T_Array::EMPTY));
    }

    /**
     * Every registered class mapped to its port list.
     *
     * @return array<class-string, list<OutputSocket>>
     */
    public function all(): array
    {
        return $this->portsByClass;
    }

}
