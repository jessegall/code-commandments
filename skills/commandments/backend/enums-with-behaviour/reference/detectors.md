# Enums with behaviour — seal the set, put the logic on the type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`const-class-enum`** — A class of 2+ scalar `const`s and nothing else — a closed set hand-rolled as constants instead of a native enum — `ConstClassEnumDetector`
- **`enum-case-or-chain`** — `$x === Enum::A || $x === Enum::B` — a hand-rolled case-group test — `EnumCaseOrChainDetector`
- **`enum-value-match`** — `match`/`switch` over an enum's `->value` at a call site (homeless method) — `EnumValueMatchDetector`
- **`in-array-mirrors-enum`** — `in_array($x, [literals])` whose literals mirror an existing enum's cases — `InArrayMirrorsEnumDetector`
- **`match-default-returns-null`** — `match` `default` that returns `null`/`false`/`[]` (or has no body) instead of throwing — `MatchDefaultReturnsNullDetector`
- **`string-match-mirrors-enum`** — `match`/`switch` over string/int literals that mirror an existing backed enum's case values — `StringMatchMirrorsEnumDetector`
- **`unnamed-vocabulary-literal`** — A raw string in an argument the codebase elsewhere fills from a named vocabulary — `expect('{')` beside `expect(Token::COLON)`, where `Token::BRACE_OPEN` already names it — `UnnamedVocabularyLiteralDetector`
