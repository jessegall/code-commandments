<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\MirroredServerType as MirroredServerTypeSkill;

/**
 * A FRONTEND sin gated on a COMPOSER package: it only makes sense — and can only be
 * fixed — in a project running `spatie/laravel-typescript-transformer`, the tool that
 * generates the type the frontend should import instead of hand-copying.
 */
final class MirroredServerType extends Sin implements RequiresComposerPackage
{
    public function __construct()
    {
        parent::__construct(
            name: 'mirrored-server-type',
            skill: MirroredServerTypeSkill::class,
            description: "A hand-written TypeScript type mirrors a backend `Data` class one-to-one — two sources of truth for one contract that drift the moment the server shape changes",
            rule: "Let the server own the shape: mark the `Data` class `#[TypeScript]`, generate the type, and import the generated one. Never hand-maintain a copy of a server contract."
        );
    }

    public function requiredComposerPackage(): string
    {
        return 'spatie/laravel-typescript-transformer';
    }
}
