# Vue control flow — dispatch on a value, don't chain conditionals — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`control-flow-on-element`** — `v-if`/`v-for`/`v-else`/`v-else-if` on an HTML/component tag instead of a `<template>` — `ControlFlowOnElementDetector`
- **`index-as-key`** — `:key` bound to the `v-for` index — a positional key corrupts state when the list reorders or an item is inserted — `IndexAsKeyDetector`
- **`loop-with-condition`** — `v-for` and `v-if`/`v-else-if` on the SAME element — the condition is re-evaluated every iteration — `LoopWithConditionDetector`
- **`switch-case`** — A `v-if`/`v-else-if` chain re-testing the same subject (should be `<SwitchCase :value>`) — `SwitchCaseDetector`
