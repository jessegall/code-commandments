<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

/**
 * A cross-cutting fact one engine PUBLISHES for another to CONSUME — the neutral
 * currency of the {@see Bridge}, the way {@see \JesseGall\CodeCommandments\Finding}
 * is the neutral currency of a report. An engine derives contracts from its OWN
 * codebase (a {@see Backend\ContractProvider} / {@see Frontend\ContractProvider})
 * and a detector requests them ({@see ConsumesContracts}) — neither side ever names
 * the other; the {@see Bridge} is the only meeting point.
 *
 * A marker: the value each kind carries is its own ({@see TypeContract} carries a
 * type name + fields). A consumer pulls the kind it cares about with
 * {@see Contracts::ofType} and reads that value.
 */
interface Contract {}
