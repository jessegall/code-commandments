A `T?` that is never actually `null` is a lie that every reader pays for. Each one has to prove
again that the value is there — `batch?.Permits(sku) ?? false`, `if (batch is null) return;` — and each of
those fallbacks answers for a state that cannot happen. Make the type say what the design guarantees.