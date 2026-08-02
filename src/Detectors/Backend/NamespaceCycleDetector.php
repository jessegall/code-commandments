<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\NamespaceGraph;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Tags\Association;
use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Two namespaces that reference each other — `App\Billing` reaching into `App\Orders` while
 * `App\Orders` reaches back — so the pair is one unit wearing two names. A cycle is wrong under any
 * stack, so unlike {@see NamespaceDependencyDetector} this fires with no declaration. The findings
 * are the THINNER direction's arrows ({@see NamespaceGraph::arrowsClosingAMutualPair}), the edits
 * that make the pair point one way again. A reference tagged `Association` draws no arrow at all —
 * both ends of a relation must name each other, so there is nothing to invert. Points at
 * dependency-direction.
 */
final class NamespaceCycleDetector implements Detector, Exemptable
{
    public function sin(): Sin
    {
        return new NamespaceCycle();
    }

    /**
     * The subject is the REFERENCE — which call it is an argument to — not the class or method it
     * sits in, so {@see NamespaceGraph} applies it as it builds the graph rather than the central
     * `ExemptBy` scopes. Declared here so `commandments exemptions` still lists what quiets the rule.
     */
    public function exemptions(): array
    {
        return [Association::class => []];
    }

    public function find(Codebase $codebase): array
    {
        $findings = [];

        foreach (NamespaceGraph::forCodebase($codebase)->arrowsClosingAMutualPair() as $arrow) {
            // An import only lets the file spell the name short; the arrow is drawn where the name
            // is USED. One finding per referrer and target — the arrow, not each spelling of it.
            if ($arrow->isImportedName()) {
                continue;
            }

            $referrer = $arrow->enclosingClassName() ?? $arrow->file->path;

            $findings[$referrer . "\0" . $arrow->referencedClassName()] ??= $arrow;
        }

        return array_values($findings);
    }
}
