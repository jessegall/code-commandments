<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;

/**
 * The central applicator for an {@see Exemptable} detector: filters out findings a package has exempted,
 * per the tag→scope map the detector declares. One place resolves the subject for each {@see ExemptBy} scope
 * and calls {@see Exemptions::has}, so every detector's `find()` ends with `return $this->exempt(…)` instead
 * of a hand-written reject per tag.
 */
trait AppliesExemptions
{
    /**
     * @param  list<NodeMatch>  $findings
     * @return list<NodeMatch>
     */
    protected function exempt(array $findings, Codebase $codebase): array
    {
        return array_values(array_filter(
            $findings,
            fn (NodeMatch $finding): bool => ! $this->isExempt($finding, $codebase),
        ));
    }

    private function isExempt(NodeMatch $finding, Codebase $codebase): bool
    {
        /**
         * @var Exemptable $this
         */
        foreach ($this->exemptions() as $tag => $scopes) {
            foreach ($scopes as $scope) {
                if (self::matchesExemption($tag, $scope, $finding, $codebase)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  class-string<Exemption>  $tag
     */
    private static function matchesExemption(string $tag, ExemptBy $scope, NodeMatch $finding, Codebase $codebase): bool
    {
        return match ($scope) {
            ExemptBy::EnclosingClass => Exemptions::has($tag, $codebase, $finding->enclosingClassName()),
            ExemptBy::EnclosingMethod => Exemptions::has($tag, $codebase, $finding->enclosingClassName(), $finding->enclosingFunctionName()),
        };
    }
}
