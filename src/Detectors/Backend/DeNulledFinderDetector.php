<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\DeNulledFinder;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\AppliesExemptions;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\ExemptBy;
use JesseGall\CodeCommandments\Packages\Tags\ContractMethod;

/**
 * Detects a nullable finder whose result is de-nulled at every call site (≥2 sites);
 * absence should be decided at the source, not re-checked everywhere. A method whose nullable
 * return a framework CONTRACT declares is exempt — that signature is not the class's to narrow.
 */
final class DeNulledFinderDetector implements Detector, Exemptable
{
    use AppliesExemptions;

    private const int TRAVELS = 2;

    public function sin(): Sin
    {
        return new DeNulledFinder();
    }

    public function exemptions(): array
    {
        return [ContractMethod::class => [ExemptBy::EnclosingMethod]];
    }

    public function find(Codebase $codebase): array
    {
        return $this->exempt($codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $node->returnsNullableObject())
            ->where(static fn (AstNode $node) => self::deNulledByEveryCallerAndTravels($codebase, $node))
            ->get(), $codebase);
    }

    /**
     * The blast-radius check: the finder's result is de-nulled at every resolved
     * call site, and it reaches at least two of them (it travels).
     */
    private static function deNulledByEveryCallerAndTravels(Codebase $codebase, AstNode $finder): bool
    {
        $class = $finder->enclosingClassName();
        $method = $finder->enclosingFunctionName();

        if ($class === null || $method === null) {
            return false;
        }

        $callers = $codebase->index()->callersOf($class, $method);
        $deNulled = array_filter($callers, static fn (NodeMatch $caller): bool => $caller->resultIsDeNulled());

        return count($deNulled) >= self::TRAVELS && count($deNulled) === count($callers);
    }
}
