<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\ClassField;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Bridge\BlanknessQuestion;
use JesseGall\CodeCommandments\Bridge\ConsumesContracts;
use JesseGall\CodeCommandments\Bridge\Contracts;
use JesseGall\CodeCommandments\Sins\Backend\BlankStringOnTheWire;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\ClassName;

/**
 * A public field typed as a TOTAL `string` whose reader on the FAR SIDE asks it `=== ''`. It is the
 * sin {@see BlankStringDefaultDetector} names — a blank standing in for absence — proven across the
 * wire, where no PHP-side question could ever be found because the question is written in
 * TypeScript. The pairing is only made where the frontend NAMES the shape it is holding
 * (`dial(source: EventSource)` asking `source.poll === ''`), so it rests on the type the reader
 * declared rather than on two languages happening to use one word.
 */
final class BlankStringOnTheWireDetector implements Detector, ConsumesContracts
{
    private Contracts $contracts;

    public function __construct()
    {
        $this->contracts = new Contracts();
    }

    public function withContracts(Contracts $contracts): void
    {
        $this->contracts = $contracts;
    }

    public function sin(): Sin
    {
        return new BlankStringOnTheWire();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereField()
            ->where(static fn (AstNode $node): bool => $node->asField()->isSomeAnd(static fn (ClassField $field): bool => $field->isPublic))
            ->where(static fn (AstNode $node): bool => $node->asField()->isSomeAnd(static fn (ClassField $field): bool => TypeName::render($field->type) === 'string'))
            ->where($this->readBackAsMissing(...))
            ->get();
    }

    /**
     * Does a reader holding this very type read this field's blank back as "missing"?
     */
    private function readBackAsMissing(AstNode $node): bool
    {
        $class = $node->enclosingClassName();

        if ($class === null) {
            return false;
        }

        return $node->asField()->isSomeAnd(fn (ClassField $field): bool => array_any(
            $this->contracts->ofType(BlanknessQuestion::class),
            static fn (BlanknessQuestion $question): bool => $question->askedOf(ClassName::short($class), $field->name),
        ));
    }
}
