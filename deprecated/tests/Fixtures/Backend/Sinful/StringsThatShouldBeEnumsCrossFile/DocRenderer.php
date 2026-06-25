<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

/**
 * #207: `$label` receives a closed set ('Inputs'/'Outputs') across its call
 * sites, but it is purely PRESENTATION — interpolated into the markdown output,
 * never branched on. A one-off section label, not a closed-set domain value, so
 * the prophet must NOT flag it as an enum in disguise.
 */
class DocRenderer
{
    public function render(array $node): string
    {
        return $this->portList('Inputs', $node['inputs'] ?? [])
            . $this->portList('Outputs', $node['outputs'] ?? []);
    }

    private function portList(string $label, array $bullets): string
    {
        $out = "### {$label}\n";

        foreach ($bullets as $bullet) {
            $out .= "- {$bullet}\n";
        }

        return $out;
    }
}
