<?php

namespace Shop\Http\Pages\RepeatedCall;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedNamedCall;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * Repeated-call site 3 — the same shape once more, reached through a fallback heading. Plus the righteous
 * twins: a ONE-OFF named argument (its shape occurs only here, so no repetition), and a repeated call whose
 * value is a bare pass-through (no construction boilerplate to hide).
 */
final class PanelHydrator
{
    private const int MAX_HEADING = 40;

    #[Sinful(RepeatedNamedCall::class)]
    public function hydrate(UiNode $node, ?string $heading): UiNode
    {
        $heading = $this->normalise($heading);

        return $node->copyWith(metadata: PanelMeta::from(['heading' => $heading])->toArray());
    }

    private function normalise(?string $heading): string
    {
        $heading = trim((string) $heading);

        if ($heading === '') {
            return 'Untitled';
        }

        return mb_strlen($heading) > self::MAX_HEADING ? mb_substr($heading, 0, self::MAX_HEADING) : $heading;
    }
}

/**
 * RIGHTEOUS: a one-off `copyWith(chrome: …)` (its shape appears only here) and repeated bare pass-throughs
 * (`copyWith(label: $x)` — no construction to promote) must NOT be flagged.
 */
final class ChromeHydrator
{
    #[Righteous(RepeatedNamedCall::class)]
    public function decorate(UiNode $node, string $title): UiNode
    {
        return $node->copyWith(chrome: CardMeta::from(['title' => $title, 'tone' => 'plain'])->toArray());
    }

    #[Righteous(RepeatedNamedCall::class)]
    public function relabel(UiNode $node, string $label): UiNode
    {
        return $node->copyWith(label: $label);
    }

    #[Righteous(RepeatedNamedCall::class)]
    public function rename(UiNode $node, string $label): UiNode
    {
        return $node->copyWith(label: $label);
    }
}
