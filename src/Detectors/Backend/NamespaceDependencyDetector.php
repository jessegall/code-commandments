<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\Config\NamespaceDependencyConfig;
use JesseGall\CodeCommandments\Sins\Backend\NamespaceDependency;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A reference OUT of a declared layer into a layer that layer may not use — the arrow pointing back
 * up the stack, or sideways. Every kind of reference counts equally (`extends`, a type hint,
 * `new X`, `X::method()`, an attribute): they are all the same dependency. Only the project knows
 * its own stack, so the layers come from `.commandments/config.php`
 * ({@see NamespaceDependencyConfig}) and nothing else is assumed — a namespace never declared
 * (framework, vendor, code outside the layering) is always allowed, both ways. Points at
 * dependency-direction.
 */
final class NamespaceDependencyDetector implements Detector
{
    use NamespaceDependencyConfig;

    public function sin(): Sin
    {
        return new NamespaceDependency();
    }

    public function find(Codebase $codebase): array
    {
        if ($this->layers === []) {
            return [];
        }

        $findings = [];

        foreach ($this->crossings($codebase) as $match) {
            // A layer breach is a fact about the PAIR of declarations, not about how many times the
            // referring one spells the name — one finding per referrer and target, at its first use
            // site. (The file stands in as the referrer for a reference outside any class.)
            $referrer = $match->enclosingClassName() ?? $match->file->path;

            $findings[$referrer . "\0" . $match->referencedClassName()] ??= $match;
        }

        return array_values($findings);
    }

    /**
     * Every class reference whose referring layer is declared and does not permit the target.
     *
     * @return list<NodeMatch>
     */
    private function crossings(Codebase $codebase): array
    {
        return $codebase
            ->whereClassReference()
            // An import only lets the file spell the name short; the arrow is drawn where the name
            // is USED, and that is where the fix lives.
            ->reject(static fn (AstNode $node): bool => $node->isImportedName())
            ->where(fn (AstNode $node): bool => $this->breaches($node))
            ->get();
    }

    /**
     * Does this reference cross out of its declared layer into something that layer may not use?
     * Code outside every declared layer never breaches — it has not opted into the layering — and
     * neither does a reference to an undeclared namespace.
     */
    private function breaches(AstNode $node): bool
    {
        $namespace = $node->namespaceName();
        $target = $node->referencedClassName();

        if ($namespace === null || $target === null || $this->layerOf($target) === null) {
            return false;
        }

        $from = $this->layerOf($namespace);

        if ($from === null) {
            return false;
        }

        return ! $this->mayReference($from, $target);
    }
}
