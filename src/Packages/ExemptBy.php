<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

/**
 * WHERE a detector matches an exemption tag against a finding — the subject an {@see Exemptable} resolves and
 * checks against a package's registered {@see Clause}. Declared per tag in {@see Exemptable::exemptions}, so
 * the engine applies the reject centrally and no detector hand-writes `Exemptions::has(...)`.
 */
enum ExemptBy
{
    /**
     * The finding's enclosing class — the common case (a `NoContainer` cast, a `Boundary` request).
     */
    case EnclosingClass;

    /**
     * The finding's enclosing class AND method — for a per-method contract (`rules`, `casts`, `schema`).
     */
    case EnclosingMethod;
}
