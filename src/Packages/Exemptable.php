<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

/**
 * A detector whose findings CAN BE EXEMPTED. It DECLARES, per tag, WHAT to match the tag against (a
 * finding's enclosing class, its enclosing method, …) — and {@see AppliesExemptions::exempt} applies the
 * reject centrally, so no detector hand-writes `Exemptions::has(...)`. A tag mapped to an EMPTY list is
 * honoured by the detector itself (a bespoke subject the enum can't express) and only declared here so the
 * `exemptions` command still shows it.
 */
interface Exemptable
{
    /**
     * The exemption tags this detector honours, each mapped to the scopes it is matched at.
     *
     * @return array<class-string<Exemption>, list<ExemptBy>>
     */
    public function exemptions(): array;
}
