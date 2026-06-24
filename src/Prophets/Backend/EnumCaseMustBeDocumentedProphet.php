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
 * …OR documented all in one place in the enum's CLASS docblock, as a
 * `{@see Enum::Case}: description` bullet per case:
 *
 *     /**
 *      * The lifecycle of an order.
 *      *
 *      * - {@see OrderStatus::Paid}: payment captured; awaiting fulfilment.
 *      * - {@see OrderStatus::Shipped}: handed to the carrier; a tracking number exists.
 *      *\/
 *     enum OrderStatus: string { case Paid = 'paid'; case Shipped = 'shipped'; }
 *
 * An enum is a closed set of domain meanings; a bare `case Shipped = 'shipped';`
 * leaks that meaning into tribal knowledge. The case name alone rarely says when
 * the state is entered, what invariants hold in it, or how it differs from its
 * siblings — exactly what a reader (or the next author adding a case) needs. This
 * is a SIN: undocumented enum cases are a defect, not a style preference.
 *
 * Detected by AST: an `EnumCase` with neither a descriptive leading comment nor a
 * `{@see Enum::Case}: …` bullet (with a real description after it) in the enum's
 * class docblock. Any leading comment style counts by default (`/** … *\/`,
 * `/* … *\/`, `// …`); set `style => 'docblock'` to require a true `/** … *\/`
 * docblock for the inline form. A separator like `// ---`, an empty `/** *\/`, or
 * a bare `{@see Enum::Case}` cross-reference with no description does not count.
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static style(string $value)
 * @method-generated-end
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

Good — each case explains itself (inline, directly above the case):
    enum OrderStatus: string
    {
        /** Payment captured; the order is awaiting fulfilment. */
        case Paid = 'paid';

        /** Handed to the carrier — a tracking number now exists; no further edits. */
        case Shipped = 'shipped';

        /** Voided before shipment; stock has been released and the buyer refunded. */
        case Cancelled = 'cancelled';
    }

Also good — documented all in ONE place in the enum's class docblock, as a
`{@see Enum::Case}: description` bullet per case (keeps the closed set's meanings
together, reads as a table):
    /**
     * The lifecycle of an order.
     *
     * - {@see OrderStatus::Paid}: payment captured; awaiting fulfilment.
     * - {@see OrderStatus::Shipped}: handed to the carrier; a tracking number exists.
     * - {@see OrderStatus::Cancelled}: voided before shipment; stock released, buyer refunded.
     */
    enum OrderStatus: string
    {
        case Paid = 'paid';
        case Shipped = 'shipped';
        case Cancelled = 'cancelled';
    }

WHAT FIRES — an enum case with NEITHER a descriptive leading comment NOR a
`{@see Enum::Case}: …` bullet (with real text after it) in the class docblock.
A separator (`// ---------`), an empty `/** */`, or a bare `{@see Enum::Case}`
cross-reference with no description do not count. This is a SIN: it blocks the
commit until the case is documented.

WHAT COUNTS AS DOCUMENTED — (a) any leading comment whose text contains a real
word (a run of 3+ letters); or (b) a `{@see Enum::Case}:` bullet in the class
docblock followed by a real description. By default every inline comment style
qualifies (`/** … */`, `/* … */`, `// …`); set `style => 'docblock'` to require a
true `/** … */` docblock for the inline form (the class-docblock form always
qualifies).

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

            // A case may be documented inline (a per-case comment) OR in the
            // enum's CLASS docblock as a `{@see Enum::Case}: description` bullet —
            // the class-level style that keeps every case's meaning in one place.
            $enumDoc = $enum->getDocComment()?->getText() ?? '';

            foreach ($enum->stmts as $stmt) {
                if (! $stmt instanceof Node\Stmt\EnumCase) {
                    continue;
                }

                if ($this->isDocumented($stmt, $docblockOnly)
                    || $this->describedInEnumDoc($enumDoc, $stmt->name->toString())) {
                    continue;
                }

                $caseName = $stmt->name->toString();

                $sins[] = $this->sinAt(
                    $stmt->getStartLine(),
                    sprintf(
                        'Enum case `%1$s::%2$s` has no descriptive documentation. Document it either with a doc comment directly above the case, OR with a `{@see %1$s::%2$s}: …` bullet in the enum\'s class docblock — explaining when it applies, what it means, and how it differs from its siblings. The name alone is not enough.',
                        $enumName,
                        $caseName,
                    ),
                    $this->lineSnippet($content, $stmt->getStartLine()),
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

    /**
     * Whether the enum's class docblock documents $caseName as a
     * `{@see [Enum|self]::CaseName}: description` bullet — the class-level style
     * that gathers every case's meaning in one place. The reference must be a
     * label (immediately followed by `:`) and then a real description (text up to
     * the next `{@see}` / end of the docblock, containing a 3+ letter word). A
     * bare `{@see Enum::Case}` cross-reference in prose is NOT documentation.
     */
    private function describedInEnumDoc(string $enumDoc, string $caseName): bool
    {
        if ($enumDoc === '') {
            return false;
        }

        // `{@see …::Case}:` — the trailing colon marks it as a definition, not a
        // mid-sentence cross-reference.
        $pattern = '/\{@see\s+[^}]*::' . preg_quote($caseName, '/') . '\}\s*:/';

        if (preg_match($pattern, $enumDoc, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return false;
        }

        $after = substr($enumDoc, $m[0][1] + strlen($m[0][0]));
        $nextRef = strpos($after, '{@see');
        $trailing = $nextRef === false ? $after : substr($after, 0, $nextRef);

        // Strip leading docblock furniture (`*`, whitespace) before the word check.
        return $this->isDescriptive(preg_replace('/^[\s*]+/', '', $trailing) ?? $trailing);
    }

}
