<?php

namespace Shop\Http\Pages\RepeatedCall;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedNamedCall;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * Repeated-call site 1 — `copyWith(metadata: <Data>::from([...])->toArray())`, with a tone chosen by match.
 * Same shape as CardHydrator/PortHydrator/PanelHydrator, so all resolve to the trait's `copyWith` and group.
 */
final class CardHydrator
{
    #[Sinful(RepeatedNamedCall::class)]
    public function hydrate(UiNode $node, string $title, string $state): UiNode
    {
        return $node->copyWith(metadata: CardMeta::from(['title' => $title, 'tone' => $this->tone($state)])->toArray());
    }

    private function tone(string $state): string
    {
        return match ($state) {
            'live' => 'accent',
            'error' => 'danger',
            default => 'muted',
        };
    }
}
