<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\PageObject;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Ast\Support\DataClassShape;
use PhpParser\Node;

/** The Spatie decorator's class-level answers, and DataClassShape's and PageObject's about every class. */
final class SpatieClasses implements Question
{
    public function name(): string
    {
        return 'spatieclasses';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        $shape = DataClassShape::forCodebase($codebase);
        $pages = PageObject::forCodebase($codebase);
        foreach (nodes($file->ast) as $node) {
            $data = $codebase->wrap($node, $file, SpatieDataNode::class);
            if ($node instanceof Node\Stmt\ClassLike) {
                $class = $node->namespacedName?->toString();
                yield [$node, 'class', [
                    'isDataClass' => $data->isDataClass(),
                    'inDataScope' => $data->inDataScope(),
                    'isTypeScriptData' => $data->isTypeScriptData(),
                    'isPageObject' => $data->isPageObject(),
                    'hasUnhiddenInjectedService' => $data->hasUnhiddenInjectedService(),
                    'optionalPublicFieldNames' => $data->optionalPublicFieldNames(),
                    'everyConstructorParamOptional' => $data->everyConstructorParamOptional(),
                    'pageObjectMissingTypeScript' => $data->pageObjectMissingTypeScript(),
                    'remapsInputNames' => $shape->remapsInputNames($class),
                    'isRich' => $shape->isRich($class, $codebase),
                    'composesMultipleData' => $shape->composesMultipleData($class, $codebase),
                    'pageObject' => $pages->isPageObject($class),
                ]];
            }
            if ($node instanceof Node\Param || $node instanceof Node\Stmt\Property) {
                yield [$node, 'field', [
                    'nestedWireTypeMissingTypeScript' => $data->nestedWireTypeMissingTypeScript(),
                    'nestedWireTypeFqcn' => $data->nestedWireTypeFqcn(),
                    'propertyTypedAsDataCollection' => $data->propertyTypedAsDataCollection(),
                    'nullableWireObject' => $data->nullableWireObject(),
                ]];
            }
            if ($node instanceof Node\PropertyHook) {
                yield [$node, 'hook', $data->hookMissingComputed()];
            }
            if ($node instanceof Node\Expr\New_ || $node instanceof Node\Expr\StaticCall) {
                yield [$node, 'construction', [
                    'isNewData' => $data->isNewData(),
                    'onDataClass' => $data->onDataClass(),
                    'isRichData' => $data->isRichData(),
                ]];
            }
        }
    }
}
