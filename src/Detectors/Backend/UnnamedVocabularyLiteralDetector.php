<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ConstantVocabulary;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\UnnamedVocabularyLiteral;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A raw string filling an argument the codebase ELSEWHERE fills from a named vocabulary — a
 * `$this->expect('{')` beside a `$this->expect(Token::COLON)`, when `Token::BRACE_OPEN` already
 * holds it. The evidence is the codebase disagreeing with itself ({@see ConstantVocabulary}), never
 * the literal alone, so only a slot already spelled by name can have a call site that failed to.
 */
final class UnnamedVocabularyLiteralDetector implements Detector
{
    public function sin(): Sin
    {
        return new UnnamedVocabularyLiteral();
    }

    public function find(Codebase $codebase): array
    {
        $vocabulary = ConstantVocabulary::forCodebase($codebase);

        return $codebase
            ->whereString()
            ->where(static fn (AstNode $node): bool => $vocabulary->nameFor($node) !== null)
            ->reject(static fn (AstNode $node): bool => $node->isParameterDefault())
            ->get();
    }
}
