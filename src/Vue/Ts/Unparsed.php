<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts;

/**
 * Thrown inside {@see Parser} when a construct isn't modelled by the grammar (a conditional/mapped
 * type, `keyof`/`infer`, a malformed fragment). It's always caught locally and turned into a
 * {@see Node\VerbatimType} — the "can't fail" floor: an unmodelled region is preserved verbatim,
 * never truncated. Never escapes the parser.
 */
final class Unparsed extends \RuntimeException {}
