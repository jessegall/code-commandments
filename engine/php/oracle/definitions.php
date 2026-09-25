<?php

/**
 * Writes what every backend skill, sin and detector states about itself as JSON, unpublished ones included,
 * so the Go rules are held to the PHP rules they port: php definitions.php > definitions.json
 */

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Discovery;
use JesseGall\CodeCommandments\Skills\Reference;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;
use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Sins\Scaffold;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Unpublished;

require __DIR__ . '/../../../vendor/autoload.php';

$src = dirname(__DIR__, 3) . '/src';

/** @return list<class-string> */
function declared(string $dir, string $namespace, string $parent): array
{
    return array_values(array_filter(
        Discovery::classes($dir, $namespace),
        static fn (string $class): bool => is_subclass_of($class, $parent) && ! (new \ReflectionClass($class))->isAbstract(),
    ));
}

/** @return list<string> the interfaces a detector declares beyond being one, by short name */
function capabilities(string $class): array
{
    $names = array_map(
        static fn (string $interface): string => substr($interface, strrpos($interface, '\\') + 1),
        array_keys(class_implements($class)),
    );
    $names = array_values(array_diff(array_unique($names), ['Detector', 'Unpublished']));
    sort($names);

    return $names;
}

function short(string $class, string $namespace): string
{
    return substr($class, strlen($namespace) + 1);
}

$skills = array_map(static function (string $class): array {
    $skill = new $class();

    return [
        'class' => short($class, 'JesseGall\\CodeCommandments\\Skills\\Backend'),
        'slug' => $skill->slug,
        'tier' => $skill->tier === Tier::Mandatory ? 'mandatory' : 'keep-in-mind',
        'order' => $skill->order,
        'title' => $skill->title(),
        'trigger' => $skill->trigger(),
        'intro' => $skill->intro(),
        'summary' => $skill->summary(),
        'principle' => $skill->principle(),
        'examplesKeepDocblocks' => $skill->examplesKeepDocblocks(),
        'languages' => array_map(static fn ($language): string => $language->value, $skill->languages()),
        'related' => array_map(
            static fn (string $related, string $reason): array => ['skill' => (new $related())->slug, 'reason' => $reason],
            array_keys($skill->related()),
            array_values($skill->related()),
        ),
        'references' => array_map(
            static fn (Reference $reference): array => ['name' => $reference->name, 'title' => $reference->title, 'body' => $reference->body],
            $skill->references(),
        ),
        'unpublished' => $skill instanceof Unpublished,
    ];
}, declared("{$src}/Skills/Backend", 'JesseGall\\CodeCommandments\\Skills\\Backend', Skill::class));

$sins = array_map(static function (string $class): array {
    $sin = new $class();

    return [
        'class' => short($class, 'JesseGall\\CodeCommandments\\Sins\\Backend'),
        'name' => $sin->name(),
        'skill' => $sin->slug(),
        'description' => $sin->description(),
        'rule' => $sin->rule(),
        'suggestion' => $sin->suggestion(),
        'scaffolds' => array_map(
            static fn (Scaffold $scaffold): array => ['path' => $scaffold->path, 'stub' => $scaffold->stub, 'target' => $scaffold->target->value],
            $sin->scaffolds(),
        ),
        'requires' => $sin instanceof RequiresPackage ? ['name' => $sin->requiredPackage(), 'ecosystem' => $sin->ecosystem()->value] : null,
        'unpublished' => $sin instanceof Unpublished,
    ];
}, declared("{$src}/Sins/Backend", 'JesseGall\\CodeCommandments\\Sins\\Backend', Sin::class));

$detectors = array_map(static function (string $class): array {
    $detector = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

    return [
        'class' => short($class, 'JesseGall\\CodeCommandments\\Detectors\\Backend'),
        'sin' => $detector->sin()->name(),
        'capabilities' => capabilities($class),
        'unpublished' => $detector instanceof Unpublished,
    ];
}, declared("{$src}/Detectors/Backend", 'JesseGall\\CodeCommandments\\Detectors\\Backend', \JesseGall\CodeCommandments\Backend\Detector::class));

echo json_encode(
    ['skills' => $skills, 'sins' => $sins, 'detectors' => $detectors],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . "\n";
