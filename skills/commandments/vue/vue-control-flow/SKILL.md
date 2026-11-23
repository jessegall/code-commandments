---
name: commandments-vue-vue-control-flow
description: "Dispatch on a single value with the published <SwitchCase :value> component (a slot per case), never a v-if / v-else-if chain that re-tests the SAME subject against a different literal. A chain of `x === 'a'` / `x === 'b'` is one decision written as many conditions. Read this BEFORE writing a v-if/v-else-if chain in a Vue template."
---

# Vue control flow — dispatch on a value, don't chain conditionals

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> In a template, a choice among the values of one thing is a dispatch, and structure (`v-if`, `v-for`) is kept apart
> from the element it controls.
> Read this before you write a `v-if`/`v-else-if` chain or a `v-for` in a Vue template, and when a
> vue-control-flow finding points here.
> An arrow in an example (`←`) marks the line the lesson is about.

## The principle

**Dispatch on a value with `<SwitchCase>`, and put `v-if` and `v-for` on a `<template>`.**

Two habits keep a template readable:

1. **One decision, one construct.** A chain of `v-if="x === 'a'"`, `v-else-if="x === 'b'"` is one decision written
   as many conditions: the reader has to check that every branch tests the same thing. `<SwitchCase :value="x">`
   says it once and gives each value a slot. (A range test such as `stock > 10`, a compound condition, or a lone
   `v-if` is not a dispatch and stays a conditional.)
2. **Structure is not content.** `v-if` and `v-for` decide which nodes exist. They go on a `<template>` wrapper, so
   the element underneath reads as one thing: its content and its styling. (`v-show` stays on the element: it
   only toggles `display`.)

```vue
<!-- Bad -->
<span v-if="order.status === 'pending'" class="badge grey">Waiting</span>      <!-- ← the same subject… -->
<span v-else-if="order.status === 'paid'" class="badge green">Paid</span>     <!-- ← …tested again… -->
<span v-else-if="order.status === 'shipped'" class="badge blue">Sent</span>   <!-- ← …and again -->

<!-- Good -->
<SwitchCase :value="order.status">
  <template #pending><span class="badge grey">Waiting</span></template>
  <template #paid><span class="badge green">Paid</span></template>
  <template #shipped><span class="badge blue">Sent</span></template>
</SwitchCase>
```

## The sins

### switch-case

A `v-if`/`v-else-if` chain where two or more branches compare the same subject with a literal. Use
`<SwitchCase :value>` with a slot per case. `commandments scaffold --sin=switch-case` writes the component.

```vue
<!-- Bad -->
<template v-if="payment.method === 'card'"><CardForm /></template>
<template v-else-if="payment.method === 'ideal'"><BankPicker /></template>   <!-- ← payment.method, compared again -->

<!-- Good -->
<SwitchCase :value="payment.method">
  <template #card><CardForm /></template>
  <template #ideal><BankPicker /></template>
</SwitchCase>
```

### re-tested-switch-case

Inside a `<SwitchCase>` slot, a `v-if` that tests the value the `<SwitchCase>` already dispatched on. The slot
already knows which case it is; the second test is a copy that goes wrong the day a case is renamed. If it was
written to make TypeScript narrow the type, give the case its own component with a narrowed prop.

```vue
<!-- Bad -->
<SwitchCase :value="request.reason">
  <template #damaged>
    <template v-if="request.reason === 'damaged'">   <!-- ← the slot already says so -->
      <p>{{ request.photos }} photos attached</p>
    </template>
  </template>
</SwitchCase>

<!-- Good -->
<SwitchCase :value="request.reason">
  <template #damaged><ReturnDamagedNote :photos="request.photos" /></template>
</SwitchCase>
```

### control-flow-on-element

`v-if`, `v-else-if`, `v-else` or `v-for` written directly on an HTML or component tag. Wrap the element in a
`<template>` and put the directive there; a `v-for` takes its `:key` along.

```vue
<!-- Bad -->
<li v-for="line in order.lines" :key="line.id" class="line">{{ line.name }}</li>   <!-- ← structure on the element -->

<!-- Good -->
<template v-for="line in order.lines" :key="line.id">
  <li class="line">{{ line.name }}</li>
</template>
```

### loop-with-condition

`v-for` and `v-if` on the same element. The condition runs again for every item, and which of the two wins is easy
to get wrong. Filter in a computed, or put the `v-for` on a `<template>` and the `v-if` on the child.

```vue
<!-- Bad -->
<template v-for="line in order.lines" v-if="line.isVisible" :key="line.id">   <!-- ← loop and condition together -->
  <OrderLine :line="line" />
</template>

<!-- Good -->
<template v-for="line in visibleLines" :key="line.id">
  <OrderLine :line="line" />
</template>
```

### index-as-key

`:key` bound to the index of the `v-for`. A key by position belongs to the place, not the item: when the list is
reordered or an item is inserted, each component keeps the state of whatever stood there before. Key by a stable
identity. (Plain strings or numbers rendered as markup that keeps no state may use the index: the place is their
only identity.)

```vue
<!-- Bad -->
<template v-for="(line, index) in order.lines" :key="index">   <!-- ← the position, not the line -->
  <OrderLineEditor :line="line" />
</template>

<!-- Good -->
<template v-for="line in order.lines" :key="line.id">
  <OrderLineEditor :line="line" />
</template>
```

## Commands

- `{{binary}} judge --skill=vue/vue-control-flow` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `control-flow-on-element`, `index-as-key`, `loop-with-condition`, `re-tested-switch-case`, `switch-case`.
- `{{binary}} scaffold --sin=<sin>` — generate the helper the fix reaches for, for `switch-case`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`vue/vue-components`](../vue-components/SKILL.md) — a `<SwitchCase>` IS a component — the same extract-don't-inline instinct.
