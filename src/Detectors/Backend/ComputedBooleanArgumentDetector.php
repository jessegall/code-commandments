<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\ComputedBooleanArgument;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A chooser taking only bool(s) that every caller computes off the SAME object —
 * `CornerInset::of($editor->inZenMode() || $editor->hasPanelOpen())`. The rule then lives at N call
 * sites instead of in the one class that owns the decision, and they drift apart. Take the subject
 * and ask it: `CornerInset::for($editor)`. The boolean-flavoured sibling of pass-the-object's
 * id + container — there the caller pre-resolves, here it pre-decides. Points at pass-the-object.
 */
final class ComputedBooleanArgumentDetector implements Detector
{
    /**
     * The decision must be re-derived at this many call sites before they can drift apart.
     */
    private const int MIN_CALLERS = 2;

    public function sin(): Sin
    {
        return new ComputedBooleanArgument();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $node->decidesOnBoolsAlone())
            ->where(fn (NodeMatch $node) => $this->callersAllAskOneObject($node))
            ->get();
    }

    /**
     * Do at least {@see MIN_CALLERS} call sites derive EVERY flag from one object, and all from the
     * same type? A lone caller cannot drift from itself, and callers holding different types are
     * not re-deriving one shared rule — it is their AGREEMENT that says the decision belongs on the
     * subject rather than at each of them.
     */
    private function callersAllAskOneObject(NodeMatch $declaration): bool
    {
        $class = $declaration->enclosingClassName();
        $name = $declaration->enclosingFunctionName();

        if ($class === null || $name === null) {
            return false;
        }

        $callers = $declaration->codebase->index()->callersOf($class, $name);
        $subjects = [];
        $reachedFromOutside = false;

        foreach ($callers as $call) {
            $subject = $call->argumentSubjectType();

            if ($subject === null) {
                return false;
            }

            $reachedFromOutside = $reachedFromOutside || $call->enclosingClassName() !== $class;
            $subjects[$subject] = true;
        }

        // A helper only its OWN class calls is the rule already living in one place — the shared tail
        // two named constructors delegate to, which is what fixing this sin LOOKS like. The sin is the
        // predicate spread across callers instead of inside the class that owns the decision, so
        // without a caller outside there is nothing spread and nothing to drift.
        return $reachedFromOutside && count($callers) >= self::MIN_CALLERS && count($subjects) === 1;
    }
}
