<?php

/**
 * Writes what the PHP C# engine answers about tests/Fixtures/csharp, one JSON file per analysis under golden/, for
 * the Go port to match. A node is named by its path under the fixture, its byte offset and its kind; a type by its
 * symbol.
 *
 * Run from the repository root: php engine/csharp/testdata/golden.php
 */

declare(strict_types=1);

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\ModuleFile;
use JesseGall\CodeCommandments\Cs\NamespaceGraph;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Cs\StateFlow;

require __DIR__ . '/../../../vendor/autoload.php';

$root = (string) realpath(__DIR__ . '/../../../tests/Fixtures/csharp');
$codebase = Codebase::scan($root);

/** Where $node is in $module: its path under the fixture, its byte offset and its kind. */
$at = static fn (ModuleFile $module, Node $node): string => substr($module->file, strlen($root) + 1) . '@' . $node->start . ':' . $node->kind;

$write = static function (string $analysis, array $answers): void {
    ksort($answers);
    file_put_contents(__DIR__ . "/golden/{$analysis}.json", json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
};

// Resolution: the type every node stands for, and the method every call and creation reaches.
$types = [];
$targets = [];

foreach ($codebase->modules() as $module) {
    foreach ([...$module->nodes(), ...$module->expressions()] as $node) {
        if ($node->type !== null) {
            $types[$at($module, $node)] = ['name' => $node->type->name, 'nullable' => $node->type->nullable, 'value' => $node->type->isValueType, 'inner' => $node->type->inner];
        }
        if ($node->target !== null) {
            $targets[$at($module, $node)] = ['type' => $node->target->type, 'name' => $node->target->name, 'parameters' => $node->target->parameters];
        }
    }
}

$write('types', $types);
$write('targets', $targets);

// State flow: what each type holds, which of it each member reads, what may be null, and every value a field is given.
$flows = [];

foreach ($codebase->whereType()->get() as $type) {
    $flow = new StateFlow($type->node);
    $assigned = [];

    foreach ($type->node->stateNames() as $field) {
        $assigned[$field] = array_map(static fn (Node $value): string => $at($type->module, $value), $flow->assignedValues($field));
        $reads[$field] = count($flow->reads($field));
    }

    $flows[$at($type->module, $type->node)] = [
        'state' => (object) array_map(static fn ($held): ?string => $held?->name, $type->node->stateTypes()),
        'readsByMember' => (object) $flow->readsByMember(),
        'nullable' => $flow->nullableFields(),
        'assigned' => (object) $assigned,
        'reads' => (object) ($reads ?? []),
        'coupled' => $type->holdsCoupledFields($codebase),
    ];
    $reads = [];
}

$write('flows', $flows);

// The namespace graph: every arrow, where it is and between which namespaces.
$graph = new NamespaceGraph($codebase);
$arrows = [];

foreach ($graph->arrows()->all as $arrow) {
    $arrows[] = "{$at($arrow->at->module, $arrow->at->node)} {$arrow->from} -> {$arrow->to}";
}

sort($arrows);
$write('namespaces', ['arrows' => $arrows]);

// The program read whole: callers, delegates, enums and the classes compared as cases, by what each is about.
$program = [];

foreach ($codebase->whereMethodDeclaration()->get() as $method) {
    $callers = array_map(static fn (NodeMatch $call): string => $at($call->module, $call->node), $codebase->callersOf($method->node));
    sort($callers);
    $program['methods'][(string) $method->node->symbol] = [
        'callers' => $callers,
        'handedOut' => $codebase->isHandedOut((string) $method->node->name),
        'envied' => $method->enviedParameter($codebase)->unwrapOr(''),
        'unpacks' => $method->unpacksTargetFromContainerParam($codebase),
    ];
}

foreach ($codebase->whereType()->get() as $type) {
    $symbol = (string) $type->node->symbol;
    $program['types'][$symbol] = [
        'declared' => $codebase->declaresType($symbol),
        'record' => $codebase->declaresRecord($symbol),
        'enum' => $codebase->enumMembers($symbol),
        'cased' => $codebase->comparesAsACase($symbol),
    ];
}

$write('program', $program);
