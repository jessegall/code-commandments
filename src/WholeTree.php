<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * A detector whose verdict needs the WHOLE tree — it asks the call graph who calls a method, or the
 * value-flow graph where a field's null was born, and those questions have no honest answer inside one
 * file. Shown a single file it does not merely find less; it can find the WRONG thing, because "nobody
 * calls this" and "nobody in this file calls this" are different facts.
 *
 * A marker, not a flag, like {@see Unpublished} and {@see RequiresBestDesign}: the requirement lives on
 * the class that has it. A full `judge` run parses everything and so runs every rule regardless — this
 * only excuses the rules that a per-file check ({@see Detectors\Catalog::singleFile}) must not ask.
 */
interface WholeTree {}
