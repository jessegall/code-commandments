<?php

/**
 * Writes what the PHP Python engine answers about tests/Fixtures/python, one JSON file per analysis under golden/,
 * for the Go ports to match. A def is named by its symbol id, a place by its path under the fixture and its line.
 *
 * Run from the repository root: php python/testdata/golden.php
 */

declare(strict_types=1);

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\ModuleFile;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\Node;

require __DIR__ . '/../../vendor/autoload.php';

$root = (string) realpath(__DIR__ . '/../../tests/Fixtures/python');
$codebase = Codebase::scan($root);

/** The place $offset is in $module: its path under the fixture, and its line. */
$place = static fn (ModuleFile $module, int $offset): string => substr($module->file, strlen($root) + 1) . ':' . $module->lineAt($offset);

/** The symbol id of a def or class: its module's dotted name, then every def and class it sits in. */
$symbol = static function (Node $node, ModuleFile $module) use ($codebase): string {
    $names = [$node->name];

    foreach ($module->ancestorsOf($node) as $around) {
        if ($around instanceof FunctionDef || $around instanceof ClassDef) {
            array_unshift($names, $around->name);
        }
    }

    return implode('.', [$codebase->fullNameOf($module), ...$names]);
};

/** Every def of the codebase, with its module. */
$defs = [];

foreach ($codebase->modules() as $module) {
    foreach ($module->nodes() as $node) {
        if ($node instanceof FunctionDef) {
            $defs[] = [$node, $module];
        }
    }
}

$write = static function (string $analysis, array $answers): void {
    ksort($answers);
    file_put_contents(__DIR__ . "/golden/{$analysis}.json", json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
};

// The call index: who calls each def, and whether a method overrides, is overridden, or extends outside.
$index = $codebase->index();
$calls = [];

foreach ($defs as [$def, $module]) {
    $callers = array_map(static fn (ExprMatch $call): string => $place($call->module, $call->expr->start), $index->callersOf($def));
    sort($callers);
    $calls[$symbol($def, $module)] = [
        'callers' => array_values(array_unique([...($calls[$symbol($def, $module)]['callers'] ?? []), ...$callers])),
        'override' => $index->isOverride($def, $module),
        'overridden' => $index->isOverridden($def, $module),
        'extendsOutside' => $index->extendsOutside($def, $module),
    ];
}

$write('calls', $calls);

$imports = [];

foreach ($codebase->modules() as $module) {
    $reached = array_map(static fn (array $import): string => substr($import[1]->file, strlen($root) + 1), $index->importsOf($module));
    sort($reached);
    $imports[substr($module->file, strlen($root) + 1)] = array_values(array_unique($reached));
}

$write('imports', $imports);

// Each resolved call: the def it reaches, what each parameter receives there, and whether it hands a key a
// literal. A call is named by its path under the fixture and its byte span, so two on a line stay apart.
$sites = [];

foreach ($codebase->whereCall()->get() as $call) {
    $target = $index->targetOf($call->expr);

    if ($target->isNone()) {
        continue;
    }

    $arguments = $index->argumentsAt($call->expr)->map(static fn (array $bound): array => array_map(
        static fn ($argument): string => substr($call->module->source, $argument->start, $argument->end - $argument->start),
        $bound,
    ));
    $sites[substr($call->module->file, strlen($root) + 1) . '@' . $call->expr->start . '-' . $call->expr->end] = [
        'target' => $symbol($target->unwrap(), $index->moduleOf($target->unwrap())),
        'arguments' => $arguments->isSome() ? (object) $arguments->unwrap() : null,
        'literalKey' => $index->passesLiteralKey($call->expr),
    ];
}

$write('call-sites', $sites);

// Each class's fields, and how the code reads each one: reads that assume it is there, reads that guard it.
$flow = $codebase->attributeFlow();
$fields = [];

foreach ($codebase->modules() as $module) {
    foreach ($module->nodes() as $node) {
        if (! $node instanceof ClassDef) {
            continue;
        }

        $verdicts = [];

        foreach ($node->fieldNames() as $field) {
            $verdict = $flow->verdict($node, $field);
            $verdicts[$field] = [$verdict->assume, $verdict->guard];
        }

        $fields[$symbol($node, $module) . '@' . $place($module, $node->start)] = (object) $verdicts;
    }
}

$write('attribute-flow', $fields);

// Every import between two packages, and the imports worth cutting in each pair of packages that import each other.
$relative = static fn (string $path): string => substr($path, strlen($root) + 1) ?: '.';
$graph = $codebase->packageGraph();
$arrows = (new ReflectionProperty($graph, 'arrows'))->getValue($graph);
$write('packages', [
    'arrows' => array_map(static fn ($arrow): string => $place($arrow->at->module, $arrow->at->node->start) . ' ' . $relative($arrow->from) . ' -> ' . $relative($arrow->to), $arrows->all),
    'closing' => array_map(static fn ($at): string => $place($at->module, $at->node->start), $graph->arrowsClosingAMutualPair()),
]);

// What every def reaches: its outside calls and the classes it builds or names, keyed where it is declared.
$population = $codebase->resourceReach()->functions();
$reach = [];

foreach ($defs as [$def, $module]) {
    $resources = array_keys($population->of($index->declarationOf($def)));
    sort($resources);
    $reach[$place($module, $def->start)] = $resources;
}

$write('reach', $reach);

// What each class is by name — an enum with its member keys, a TypedDict, a dataclass — and, for each of its
// methods, the parameters annotated with a class the codebase owns.
$declared = [];

foreach ($codebase->modules() as $module) {
    foreach ($module->nodes() as $node) {
        if (! $node instanceof ClassDef) {
            continue;
        }

        $owned = [];

        foreach ($node->body->body as $member) {
            if ($member instanceof FunctionDef) {
                $owned[$member->name] = $codebase->ownedParameters($member, $node);
            }
        }

        $declared[$symbol($node, $module) . '@' . $place($module, $node->start)] = [
            'enum' => $codebase->enums()->isEnum($node->name) ? $node->memberValueKeys() : null,
            'typedDict' => $codebase->typedDicts()->isTypedDict($node->name),
            'dataclass' => $codebase->dataclasses()->named($node->name)->isSome(),
            'owned' => (object) $owned,
        ];
    }
}

$write('declarations', $declared);
