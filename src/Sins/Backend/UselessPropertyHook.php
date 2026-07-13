<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TypeHonesty;

final class UselessPropertyHook extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'useless-property-hook',
            skill: TypeHonesty::class,
            description: 'A `get` hook that reads nothing from `$this` — a stored property wearing computed syntax',
            rule: "A property hook must EARN its hook: a `get` body that references no `\$this` (and no `parent::`) computes nothing from the object — it yields the same value however the instance is configured, so it is a plain property in disguise. This usually happens when an interface declares `{ get; }` and the implementer mimics the syntax; a plain property satisfies a hooked interface property just as well.",
            suggestion: "Make it a stored property: a constant body becomes a property default (`public ?Transition \$t = null;`); a constructed value (`get => Transition::make(...)`) is assigned ONCE in the constructor. Keep the hook only when the body genuinely derives from `\$this` state.",
        );
    }
}
