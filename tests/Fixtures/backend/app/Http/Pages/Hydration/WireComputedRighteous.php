<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullableWireObject;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Righteous twin for NullableWireObject — the nullable nested object is a COMPUTED (get-hook)
 * property, not a stored hydration slot. `Optional` means "key absent from the payload", which
 * cannot apply to a value the hook derives (and spatie force-evaluates an Optional-typed hook
 * via isset() during ::from(), before non-promoted state exists). `T | null` is the honest
 * shape for a computed wire field whose null the frontend branches on (#394, #395). Must NOT flag.
 */
#[Righteous(NullableWireObject::class)]
#[TypeScript]
final class WireStage extends Data
{
    #[Computed]
    public StagePhase|null $phase {
        get => $this->resolvePhase();
    }

    public function __construct(
        public readonly string $id,
        public readonly bool $ready = false,
    ) {}

    private function resolvePhase(): StagePhase|null
    {
        return $this->ready ? null : StagePhase::Setup;
    }
}

enum StagePhase: string
{
    case Setup = 'setup';
    case Review = 'review';
}
