<?php

declare(strict_types=1);

namespace Notifications;

final class AlertDispatcher
{
    public function __construct(
        private ChannelRegistry $channels,
        private TemplateStore $templates,
    ) {}

    public function dispatch(Severity $severity, string $recipient, array $vars): int
    {
        // de-nulls the registry result — proof the nullable contract is unearned
        $channel = $this->channelFor($severity)
            ?? throw new \RuntimeException("No channel for severity {$severity->value}");

        $channel->send($recipient, $this->renderBody($severity, $vars));

        // downstream symptom: compensates for the enum's nullable escalation
        return $severity->escalationMinutes() ?? 0;
    }

    /**
     * SMELL: private `?Channel` helper; every caller de-nulls it.
     */
    private function channelFor(Severity $severity): ?Channel
    {
        return $this->channels->find($severity->channelKey());
    }

    /**
     * SMELL: the template for a known severity MUST exist, yet the Option is
     * collapsed back to null with `unwrapOr(null)` and then laundered with `?? ''`.
     *
     * @param array<string, string> $vars
     */
    private function renderBody(Severity $severity, array $vars): string
    {
        return $this->templates->lookup($severity->value)->unwrapOr(null)?->render($vars) ?? '';
    }
}
