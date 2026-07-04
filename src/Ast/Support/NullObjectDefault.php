<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * Resolves the constant-expression default that names a value type's **Null Object** — the
 * inert/identity instance an optional field should default to instead of `null`, so the DTO
 * stops lying (`?T = null`) and consumers stop null-checking.
 *
 * The identity is READ from the type's own declaration, never invented: today, a type that is
 * default-constructible (`new T()` — no constructor, or every parameter defaulted) yields its
 * own resting state. A type with no discoverable identity yields null, and the caller leaves
 * the field alone — the fix there is a design decision (declare the Null Object), not a rewrite.
 *
 * PHP caps what a default may be: `new T(...)` with constant-expression arguments and enum
 * cases are legal; a `T::factory()` static call is NOT. So the identity must be expressible as
 * such an expression — which is exactly why a bare `new T()` is the first thing we can name.
 */
final class NullObjectDefault
{
    public function __construct(private readonly Codebase $codebase) {}

    /**
     * The default expression that names $fqcn's Null Object, written to reference the type as
     * $asWritten (the name already in scope at the field — its own type token), or null when the
     * type has no identity we can express as a constant-expression default.
     */
    public function forType(?string $fqcn, string $asWritten): ?string
    {
        if ($this->codebase->classNamed($fqcn)->constructorRequiresNoArguments()) {
            return "new {$asWritten}()";
        }

        return null;
    }
}
