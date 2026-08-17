<?php

namespace Shop\Support\Text;

use JesseGall\CodeCommandments\Sins\Backend\ErasedNullObject;
use JesseGall\CodeCommandments\Testing\Righteous;
use Stringable;

/**
 * A note stamped onto a picking slip. The Null Object survives here because every slot that holds it is
 * typed to carry an OBJECT, so nothing coerces it and a caller can still ask the note what it is.
 */
#[Righteous(ErasedNullObject::class)]
final readonly class StampedNote
{
    public function __construct(
        public Stringable $body = new BlankText,
    ) {}

    public function or(Stringable $fallback): Stringable
    {
        if ($this->body instanceof BlankText) {
            return $fallback;
        }

        return $this->body;
    }

    public function blank(): Stringable
    {
        return new BlankText;
    }
}
