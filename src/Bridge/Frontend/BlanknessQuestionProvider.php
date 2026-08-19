<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge\Frontend;

use JesseGall\CodeCommandments\Bridge\BlanknessQuestion;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\ExprMatch;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * Publishes every field the frontend asks `=== ''` about, named with the TYPE it was asked of — the
 * blankness questions this side puts to the data it was sent. A question whose subject the module
 * never types is not published: a bare `x === ''` is almost always about a local, and pairing it
 * with a server field of that name by name alone would be a coincidence, not evidence.
 */
final class BlanknessQuestionProvider implements ContractProvider
{
    public function contracts(Codebase $codebase): array
    {
        $asked = [];

        $questions = $codebase
            ->whereExpression(static fn (Expr $expr): bool => $expr->isBlankComparison())
            ->get();

        foreach ($questions as $question) {
            $about = $this->questionAsked($question);

            if ($about !== null) {
                $asked[$about->type . '::' . $about->field] ??= $about;
            }
        }

        return array_values($asked);
    }

    /**
     * The question a blankness test puts — `source.poll === ''` asked of an `EventSource` — or none
     * when the subject is not one typed object's field.
     */
    private function questionAsked(ExprMatch $question): ?BlanknessQuestion
    {
        $reach = $question->expr->comparisonSubject()->asChain();

        if ($reach === null || count($reach) !== 2) {
            return null;
        }

        [$holder, $field] = $reach;
        $type = $question->module->typeOfBinding($holder);

        return $type === null ? null : new BlanknessQuestion($type, $field);
    }
}
