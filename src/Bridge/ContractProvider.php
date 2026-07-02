<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

/**
 * The root of every contract provider — the PUBLISH side of the {@see Bridge}. An
 * engine implements its own sub-interface ({@see Backend\ContractProvider} over the
 * PHP AST, {@see Frontend\ContractProvider} over the Vue codebase), derives
 * {@see Contract}s from ITS OWN codebase, and knows nothing of who consumes them.
 * The split mirrors {@see \JesseGall\CodeCommandments\Detector}; providers auto-enrol
 * through {@see Catalog}, exactly as detectors do through their own catalog.
 */
interface ContractProvider {}
