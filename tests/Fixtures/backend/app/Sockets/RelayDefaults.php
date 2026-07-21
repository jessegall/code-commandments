<?php

namespace Shop\Sockets;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicatedConfigDefault;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The heartbeat window, restated. `config/relay.php` already says 30; this says it again, so the two
 * only agree until someone edits one of them.
 */
final class RelayDefaults
{
    private const int FALLBACK_SECONDS = 30;

    #[Sinful(DuplicatedConfigDefault::class)]
    public function heartbeatSeconds(): int
    {
        $configured = $this->setting('relay.heartbeat_seconds', self::FALLBACK_SECONDS);

        return max(1, $configured);
    }

    private function setting(string $key, int $fallback): int
    {
        return $fallback;
    }
}
