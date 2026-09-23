<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\BlankStringDefault;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `string` defaulted to `""` and then asked whether it is blank — absence spelled as a blank in a type
 * that says the value is always there. The C# twin of the Python and PHP blank-string-default rules.
 */
final class BlankStringDefaultDetector implements Detector
{
    public function sin(): Sin
    {
        return new BlankStringDefault();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node->isBlankStringDefault())
            ->where(static fn (NodeMatch $match): bool => $match->defaultedNameTestedForBlankness())
            ->get();
    }
}
