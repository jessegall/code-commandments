<?php

namespace Shop\Http\Pages;

/**
 * Tiny custom output transformers — each reshapes a value object into a different wire type, so the
 * generated TypeScript must be told the new shape with a `#[TypeScriptType]`. The generator cannot infer
 * a custom transformer's output; only the built-ins (`DateTimeInterfaceTransformer`, …) are known to it.
 */
final class MoneyTransformer {}

final class GeoPointTransformer {}

final class DateRangeTransformer {}
