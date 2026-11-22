<?php

/**
 * Writes the PHP fixture's vendor: signature-only stubs of the framework classes it names, reflected from a real
 * project's vendor, so the bridge resolves the fixture's framework calls the way it does in a real project.
 *
 * php scripts/php-fixture-vendor.php <a real project's vendor/autoload.php> <roots file> <out folder>
 */

declare(strict_types=1);

[, $autoload, $rootsFile, $out] = $argv;
require $autoload;

$roots = array_values(array_filter(array_map('trim', file($rootsFile)), static fn (string $line): bool => $line !== ''));
$wanted = [];
$waiting = [];
foreach ($roots as $root) {
    $waiting[] = [$root, 1];
}
while ($waiting !== []) {
    [$name, $depth] = array_shift($waiting);
    $name = ltrim($name, '\\');
    if (isset($wanted[strtolower($name)]) || ! (class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name))) {
        continue;
    }
    $reflection = new ReflectionClass($name);
    if ($reflection->isInternal()) {
        continue;
    }
    $wanted[strtolower($name)] = $reflection;
    $related = array_merge($reflection->getInterfaceNames(), $reflection->getTraitNames());
    if ($reflection->getParentClass() !== false) {
        $related[] = $reflection->getParentClass()->getName();
    }
    foreach ($related as $each) {
        $waiting[] = [$each, $depth];
    }
    if ($depth > 0) {
        foreach ($reflection->getMethods() as $method) {
            foreach ([$method->getReturnType(), ...array_map(static fn (ReflectionParameter $p) => $p->getType(), $method->getParameters())] as $type) {
                foreach (namedTypes($type) as $typeName) {
                    $waiting[] = [$typeName, $depth - 1];
                }
            }
        }
    }
}

foreach ($wanted as $reflection) {
    $path = $out.'/src/'.str_replace('\\', '/', $reflection->getName()).'.php';
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0o755, true);
    }
    file_put_contents($path, stub($reflection));
}
file_put_contents($out.'/autoload.php', <<<'PHP'
<?php

spl_autoload_register(static function (string $class): void {
    $path = __DIR__.'/src/'.str_replace('\\', '/', $class).'.php';
    if (is_file($path)) {
        require $path;
    }
});

PHP);
fwrite(STDOUT, count($wanted)." classes written\n");

/** @return list<string> */
function namedTypes(?ReflectionType $type): array
{
    if ($type instanceof ReflectionNamedType) {
        return $type->isBuiltin() || in_array(strtolower($type->getName()), ['self', 'static', 'parent'], true) ? [] : [$type->getName()];
    }
    if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
        return array_merge(...array_map(namedTypes(...), $type->getTypes()));
    }

    return [];
}

function typeText(?ReflectionType $type): string
{
    if ($type instanceof ReflectionNamedType) {
        $name = $type->getName();
        $written = $type->isBuiltin() || in_array(strtolower($name), ['self', 'static', 'parent'], true) ? $name : '\\'.$name;

        return $type->allowsNull() && ! in_array($name, ['mixed', 'null'], true) ? '?'.$written : $written;
    }
    if ($type instanceof ReflectionUnionType) {
        return implode('|', array_map(static fn (ReflectionType $each): string => $each instanceof ReflectionIntersectionType ? '('.typeText($each).')' : typeText($each), $type->getTypes()));
    }
    if ($type instanceof ReflectionIntersectionType) {
        return implode('&', array_map(typeText(...), $type->getTypes()));
    }

    return '';
}

function valueText(mixed $value): ?string
{
    return is_scalar($value) || $value === null || (is_array($value) && array_is_list($value) && array_filter($value, 'is_scalar') === $value)
        ? var_export($value, true)
        : null;
}

function parameter(ReflectionParameter $parameter): string
{
    $text = trim(typeText($parameter->getType()).' '.($parameter->isPassedByReference() ? '&' : '').($parameter->isVariadic() ? '...' : '').'$'.$parameter->getName());
    if ($parameter->isDefaultValueAvailable()) {
        $default = $parameter->isDefaultValueConstant() ? constantText((string) $parameter->getDefaultValueConstantName()) : valueText($parameter->getDefaultValue());
        $text .= ' = '.($default ?? 'null');
    } elseif ($parameter->isOptional() && ! $parameter->isVariadic()) {
        $text .= ' = null';
    }

    return $text;
}

function constantText(string $constant): string
{
    $constant = ltrim($constant, '\\');

    return preg_match('/^(self|static|parent)::/i', $constant) === 1 ? $constant : '\\'.$constant;
}

function fromTrait(ReflectionClass $class, string $member, bool $isMethod): bool
{
    foreach ($class->getTraits() as $trait) {
        if ($isMethod ? $trait->hasMethod($member) : $trait->hasProperty($member)) {
            return true;
        }
    }

    return false;
}

function stub(ReflectionClass $class): string
{
    $name = $class->getShortName();
    $lines = ['<?php', '', 'namespace '.$class->getNamespaceName().';', ''];
    if ($class->getAttributes(Attribute::class) !== []) {
        $lines[] = '#[\\Attribute('.($class->getAttributes(Attribute::class)[0]->getArguments()[0] ?? Attribute::TARGET_ALL).')]';
    }
    $parent = $class->getParentClass();
    $interfaces = array_values(array_diff($class->getInterfaceNames(), $parent !== false ? $parent->getInterfaceNames() : [], ['UnitEnum', 'BackedEnum']));
    $implements = $interfaces === [] ? '' : ($class->isInterface() ? ' extends ' : ' implements ').implode(', ', array_map(static fn (string $each): string => '\\'.$each, $interfaces));
    $header = match (true) {
        $class->isInterface() => "interface {$name}{$implements}",
        $class->isTrait() => "trait {$name}",
        $class->isEnum() => "enum {$name}".((new ReflectionEnum($class->getName()))->isBacked() ? ': '.(new ReflectionEnum($class->getName()))->getBackingType() : '').$implements,
        default => ($class->isAbstract() ? 'abstract ' : '').($class->isFinal() ? 'final ' : '').($class->isReadOnly() ? 'readonly ' : '')."class {$name}".($parent !== false ? ' extends \\'.$parent->getName() : '').$implements,
    };
    $lines[] = $header;
    $lines[] = '{';
    if ($class->getTraitNames() !== []) {
        $lines[] = '    use '.implode(', ', array_map(static fn (string $each): string => '\\'.$each, $class->getTraitNames())).';';
    }
    if ($class->isEnum()) {
        foreach ((new ReflectionEnum($class->getName()))->getCases() as $case) {
            $lines[] = '    case '.$case->getName().($case instanceof ReflectionEnumBackedCase ? ' = '.var_export($case->getBackingValue(), true) : '').';';
        }
    }
    foreach ($class->getReflectionConstants() as $constant) {
        if ($constant->getDeclaringClass()->getName() !== $class->getName() || $constant->isEnumCase()) {
            continue;
        }
        $visibility = $constant->isPrivate() ? 'private' : ($constant->isProtected() ? 'protected' : 'public');
        $lines[] = "    {$visibility} const {$constant->getName()} = ".(valueText($constant->getValue()) ?? 'null').';';
    }
    foreach ($class->getProperties() as $property) {
        if ($property->getDeclaringClass()->getName() !== $class->getName() || fromTrait($class, $property->getName(), false) || $class->isEnum()) {
            continue;
        }
        $visibility = $property->isPrivate() ? 'private' : ($property->isProtected() ? 'protected' : 'public');
        $default = $property->hasDefaultValue() && ! $property->isReadOnly() ? valueText($property->getDefaultValue()) : null;
        $lines[] = '    '.trim($visibility.($property->isStatic() ? ' static' : '').($property->isReadOnly() && ! $class->isReadOnly() ? ' readonly' : '').' '.typeText($property->getType())).' $'.$property->getName().($default !== null ? ' = '.$default : '').';';
    }
    foreach ($class->getMethods() as $method) {
        if ($method->getDeclaringClass()->getName() !== $class->getName() || (! $class->isTrait() && fromTrait($class, $method->getName(), true) && $method->getFileName() !== $class->getFileName())) {
            continue;
        }
        if ($class->isEnum() && in_array(strtolower($method->getName()), ['cases', 'from', 'tryfrom'], true)) {
            continue;
        }
        $visibility = $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public');
        $abstract = $method->isAbstract() && ! $class->isInterface();
        $modifiers = trim(($abstract ? 'abstract ' : '').($method->isFinal() ? 'final ' : '').$visibility.($method->isStatic() ? ' static' : ''));
        $returns = typeText($method->getReturnType() ?? $method->getTentativeReturnType());
        $signature = "    {$modifiers} function ".($method->returnsReference() ? '&' : '').$method->getName().'('.implode(', ', array_map(parameter(...), $method->getParameters())).')'.($returns !== '' ? ': '.$returns : '');
        $lines[] = $signature.($method->isAbstract() ? ';' : ' {}');
    }
    $lines[] = '}';

    return implode("\n", $lines)."\n";
}
