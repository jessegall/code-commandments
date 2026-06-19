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
        $this->channelFor($severity)->send($recipient, $this->renderBody($severity, $vars));

        // escalationMinutes() is total now — no compensation.
        return $severity->escalationMinutes();
    }

    /**
     * Total: the channel for a severity must be registered, so resolve-or-throw.
     */
    private function channelFor(Severity $severity): Channel
    {
        return $this->channels->get($severity->channelKey());
    }

    /**
     * The per-severity template is required — use the throwing require(), not
     * the optional lookup(). No getOr(null), no `?? ''` laundering.
     *
     * @param array<string, string> $vars
     */
    private function renderBody(Severity $severity, array $vars): string
    {
        return $this->templates->require($severity->value)->render($vars);
    }
}
