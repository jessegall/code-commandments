<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Ast\Spatie\TransformerOutput;
use PhpParser\Node;

/** The Spatie decorator's answers about field assignments, Optional, transformers and flattened value objects. */
final class SpatieAssignments implements Question
{
    public function name(): string
    {
        return 'spatieassignments';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            $data = $codebase->wrap($node, $file, SpatieDataNode::class);
            if ($node instanceof Node\Expr\Assign) {
                yield [$node, 'assign', [
                    'assignedPropertyIsPublicSlot' => $data->assignedPropertyIsPublicSlot(),
                    'assignmentRhsIsDeferred' => $data->assignmentRhsIsDeferred(),
                    'assignedSlotTypeIsDeferred' => $data->assignedSlotTypeIsDeferred(),
                    'assignmentReadsScopedState' => $data->assignmentReadsScopedState(),
                    'assignedSlotIsEager' => $data->assignedSlotIsEager(),
                    'propertyAssignedMoreThanOnce' => $data->propertyAssignedMoreThanOnce(),
                ]];
            }
            if ($node instanceof Node\Expr\New_ || $node instanceof Node\Expr\StaticCall) {
                yield [$node, 'optional', [
                    'isOptionalAbsentMarker' => $data->isOptionalAbsentMarker(),
                    'isOptionalNullFallback' => $data->isOptionalNullFallback(),
                    'isSharedOptionalFactory' => $data->isSharedOptionalFactory(),
                    'isReplaceableNewOptional' => $data->isReplaceableNewOptional(),
                ]];
            }
            if ($node instanceof Node\Attribute) {
                yield [$node, 'attribute', $data->transformerLacksTsType()];
            }
            if ($node instanceof Node\Expr\Assign || $node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_ || $node instanceof Node\PropertyHook) {
                yield [$node, 'flattens', $data->flattensValueObjectToArray()];
            }
            if ($node instanceof Node\Stmt\Namespace_ || ($node === ($file->ast[0] ?? null) && ! $node instanceof Node\Stmt\Namespace_)) {
                yield [$node, 'transformerOutput', TransformerOutput::locationIn($codebase)];
            }
        }
    }
}
