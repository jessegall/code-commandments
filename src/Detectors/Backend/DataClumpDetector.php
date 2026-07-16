<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\DataClump;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Backend\Config\DataClumpConfig;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * The same three-or-more value parameters (`string $shopId, string $userId,
 * string $channelId`) threaded through two-or-more signatures in different
 * classes. A clump that travels together is one concept wearing no name — hoist
 * it into a value object and pass that. Recurrence across classes is required so
 * an isolated wide signature isn't mistaken for a clump. A constructor or named
 * constructor (`make()`/`from…()` minting `new self(...)`) is exempt — those params
 * are the object's own fields being born, not a clump. Points at value-objects.
 */
final class DataClumpDetector implements Detector
{
    use DataClumpConfig;

    public function sin(): Sin
    {
        return new DataClump();
    }

    public function find(Codebase $codebase): array
    {
        $byClump = [];

        foreach ($codebase->whereMethodDeclaration()->get() as $match) {
            $signature = $match->valueParamSignature();

            // A constructor — or a named constructor minting `new self(...)` — accepting the
            // fields is how the value object is BUILT; those params ARE its own fields being
            // born. The smell is threading the loose clump through ordinary collaborator methods.
            if ($signature === [] || $match->isConstructorDeclaration() || $match->isNamedConstructor()) {
                continue;
            }

            $byClump[implode(', ', $signature)][] = $match;
        }

        $findings = [];

        foreach ($byClump as $matches) {
            if ($this->distinctClasses($matches) >= $this->minClasses) {
                array_push($findings, ...$matches);
            }
        }

        return $findings;
    }

    /**
     * @param  list<NodeMatch>  $matches
     */
    private function distinctClasses(array $matches): int
    {
        $classes = [];

        foreach ($matches as $match) {
            $classes[$match->enclosingClassName() ?? $match->file->path] = true;
        }

        return count($classes);
    }
}
