<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

/**
 * A detector whose findings CAN BE EXEMPTED — it reads one or more tags via {@see Exemptions::has}
 * and skips a match a package has exempted. Implementing this DECLARES which tags it honours, so the
 * `exemptions` command can show, per detector, exactly what a package can register to quiet it —
 * and so the declaration stays in sync with the reads.
 */
interface Exemptable
{
    /**
     * The exemption tags this detector honours.
     *
     * @return list<class-string<Exemption>>
     */
    public function exemptions(): array;
}
