<?php

namespace Shop\Http\Pages\FlatCluster;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\FlatFieldCluster;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * RealtimeCard flattens PART of the Broadcast value object — broadcast{Url,Enabled} — beside its own title.
 * A partial flatten is still a flatten: nest `broadcast: Broadcast` and drop the prefix.
 */
#[TypeScript]
#[Sinful(FlatFieldCluster::class)]
final class RealtimeCard extends Data
{
    public function __construct(
        public readonly string $broadcastUrl,
        public readonly bool $broadcastEnabled,
        public readonly string $title,
    ) {}

    public function scheme(): string
    {
        return explode('://', $this->broadcastUrl)[0];
    }

    public function status(): string
    {
        return $this->broadcastEnabled ? 'live' : 'paused';
    }

    public function reconnectDelay(int $attempt): int
    {
        return min(30, 2 ** $attempt);
    }

    public function banner(): string
    {
        $badge = $this->broadcastEnabled ? '● ' : '○ ';

        return $badge . ($this->title === '' ? 'Untitled stream' : $this->title);
    }
}
