<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Every enum case must carry a DESCRIPTIVE doc comment above it explaining what
 * the case represents — when it applies, what it means, the rule it encodes.
 *
 *     enum OrderStatus: string
 *     {
 *         /** Payment captured; the order is awaiting fulfilment. *\/
 *         case Paid = 'paid';
 *
 *         /** Handed to the carrier — a tracking number now exists. *\/
 *         case Shipped = 'shipped';
 *     }
 *
 * An enum is a closed set of domain meanings; a bare `case Shipped = 'shipped';`
 * leaks that meaning into tribal knowledge. The case name alone rarely says when
 * the state is entered, what invariants hold in it, or how it differs from its
 * siblings — exactly what a reader (or the next author adding a case) needs. This
 * is a SIN: undocumented enum cases are a defect, not a style preference.
 *
 * Detected by AST: an `EnumCase` node with no leading comment whose text is
 * descriptive (contains a real word). Any leading comment style counts by default
 * (`/** … *\/`, `/* … *\/`, `// …`); set `style => 'docblock'` to require a true
 * `/** … *\/` docblock. A separator like `// ---` or an empty `/** *\/` does not
 * count — it carries no explanation.
 */
#[IntroducedIn('2.35.0')]
class EnumCaseMustBeDocumentedProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Every enum case must have a descriptive doc comment above it explaining what the case is for';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
An enum is a CLOSED SET OF DOMAIN MEANINGS. The whole point of modelling a value
as an enum is that each case carries meaning — a state, a kind, a mode. A bare,
undocumented case hides that meaning in the author's head: the name says WHAT it
is called, not WHEN it applies, what invariants hold while a value is in it, or
how it differs from the case next to it. The next person to add a case has no
anchor for whether their new case overlaps an existing one.

So every enum case must carry a DESCRIPTIVE comment directly above it.

Bad — meaning lives in tribal knowledge:
    enum OrderStatus: string
    {
        case Paid = 'paid';
        case Shipped = 'shipped';
        case Cancelled = 'cancelled';
    }

Good — each case explains itself:
    enum OrderStatus: string
    {
        /** Payment captured; the order is awaiting fulfilment. */
        case Paid = 'paid';

        /** Handed to the carrier — a tracking number now exists; no further edits. */
        case Shipped = 'shipped';

        /** Voided before shipment; stock has been released and the buyer refunded. */
        case Cancelled = 'cancelled';
    }

WHAT FIRES — an enum case with NO leading comment, or whose only leading comment
is non-descriptive (a separator like `// ---------`, an empty `/** */`). This is a
SIN: it blocks the commit until the case is documented.

WHAT COUNTS AS DOCUMENTED — any leading comment whose text contains a real word
(a run of 3+ letters). By default every comment style qualifies (`/** … */`,
`/* … */`, `// …`); set `style => 'docblock'` to require a true `/** … */`
docblock and reject line comments.

Configure via:

    Backend\EnumCaseMustBeDocumentedProphet::class => [
        'style' => 'any',   // or 'docblock' to require /** … */
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $docblockOnly = strtolower((string) $this->config('style', 'any')) === 'docblock';
        $sins = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Enum_::class) as $enum) {
            $enumName = $enum->name?->toString() ?? 'enum';

            foreach ($enum->stmts as $stmt) {
                if (! $stmt instanceof Node\Stmt\EnumCase) {
                    continue;
                }

                if ($this->isDocumented($stmt, $docblockOnly)) {
                    continue;
                }

                $caseName = $stmt->name->toString();

                $sins[] = $this->sinAt(
                    $stmt->getStartLine(),
                    sprintf(
                        'Enum case `%s::%s` has no descriptive documentation. Every enum case must carry a doc comment above it explaining what the case represents — when it applies, what it means, how it differs from its siblings. The name alone is not enough.',
                        $enumName,
                        $caseName,
                    ),
                    $this->lineAt($content, $stmt->getStartLine()),
                    null,
                    "enum-case-doc:{$enumName}::{$caseName}",
                    false,
                );
            }
        }

        return $sins === [] ? $this->righteous() : $this->fallen($sins);
    }

    /**
     * Whether the case carries a descriptive leading comment. In `docblock` mode
     * only a `/** … *\/` doc comment qualifies; otherwise any comment style does,
     * as long as its text contains a real word.
     */
    private function isDocumented(Node\Stmt\EnumCase $case, bool $docblockOnly): bool
    {
        if ($docblockOnly) {
            $doc = $case->getDocComment();

            return $doc !== null && $this->isDescriptive($doc->getText());
        }

        foreach ($case->getComments() as $comment) {
            if ($this->isDescriptive($comment->getText())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the comment text carries an actual explanation: it must contain a
     * run of 3+ letters (a real word). Comment markers (`/`, `*`, `#`, `-`) hold
     * no letters, so an empty `/** *\/` or a `// ----` separator never qualifies
     * — no need to strip markers first.
     */
    private function isDescriptive(string $raw): bool
    {
        return preg_match('/[A-Za-z]{3,}/', $raw) === 1;
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
