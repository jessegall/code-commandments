<?php

namespace Shop\Dispatch;

/**
 * A parcel the depot holds back, with the reason it is held while it is.
 */
final class HeldParcel
{
    public ?string $holdReason = null;
}
