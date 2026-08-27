---
name: commandments-frontend-vue-components
description: "Extract a component when template markup REPEATS identically, or when an element in a large template reaches DEEP into nested data (data.user.firstName). Repeated markup is one component waiting to be born; a deep reach is a child that knows too much about the data shape and wants the mid-object as a prop. Read this BEFORE copy-pasting a block of template or reaching `a.b.c` in a sizeable component."
---

# Vue components — extract repetition and deep reaches

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Two forms of one rule: **a chunk of template that repeats, or that reaches deep into
> data, is a component trying to get out.** Pull it into its own file, pass it what it
> needs as props, and use it by name.

## The principle

A template earns a new component the moment a chunk of it **repeats**, **reaches deep into nested data**, or
**nests far past readable** — each is the same signal that one coherent thing is trapped inside a bigger one
and wants to be lifted out, named, and given props.

When an element binds or interpolates a chain three-or-more levels deep (`order.customer.fullName`), that is
the Law of Demeter showing up in the markup: the element knows the shape of an object two hops away. Lift it
into a component that takes the **mid-object** as a prop, so it reaches one level, not three —
`<OrderCustomer :customer="order.customer" />` depends only on the slice it renders, not on `order`'s whole
shape.

When the DOM nests past readability with a whole sub-tree still below, don't extract a random node
mid-chain: look back **up** to the natural boundary — the top of the wrapper stack, or the `<li>` of a list
— and lift THAT. Name it for what it is: `{Item}List` / `{Item}ListItem` for a list, `{Object}Section` for a
panel, the compound's purpose (`PairReaderDialog`) for an inline primitive. The point is always one coherent
unit out, props in.

## Rules

- [ ] Lift a compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`) assembled inline into its own named component.
      _Extract to a `<{Object}{Action}Dialog>` component; pass `v-model` + props._
- [ ] Pass the mid-object as a prop; don't reach deep into nested data from the template.
- [ ] Extract a far-too-deeply-nested subtree into its own component.
- [ ] Extract repeated identical markup into one component.
- [ ] Don't thread a prop through a component that doesn't use it; provide/inject it, or give the child the data directly.
- [ ] Never write a prop. For two-way state use `defineModel`; otherwise emit an `update:` event and let the parent own the value.

## Worked example

### compound-inline-component

A compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`…) assembled INLINE with a substantial body — extract it into its own named component

```vue
----------[ Bad ]----------

<Dialog v-model:open="open">
  <DialogContent class="sm:max-w-md">
    <DialogHeader>
      <DialogTitle>Pair Reader</DialogTitle>
      <DialogDescription>Enter the device name and reader model to pair.</DialogDescription>
    </DialogHeader>
    <form class="space-y-4" @submit.prevent="submit">
      <div class="field">
        <Label>Device name</Label>
        <Input v-model="form.name" type="text" placeholder="Front counter" />
      </div>
      <div class="select-row">
        <Label>Reader model</Label>
        <select v-model="form.model" class="select">
          <option value="s1">SumUp Solo</option>
          <option value="s2">SumUp Air</option>
        </select>
      </div>
      <DialogFooter>
        <Button variant="outline" @click="open = false">Cancel</Button>
        <Button type="submit">Pair reader</Button>
      </DialogFooter>
    </form>
  </DialogContent>
</Dialog>

----------[ Good ]----------

<!-- in ReaderPairingPanel.vue -->
<ReaderPairingDialog v-model:open="open" :form="form" @submit="submit" />

<!-- in ReaderPairingDialog.vue -->
<Dialog :open="open" @update:open="$emit('update:open', $event)">
  <DialogContent class="sm:max-w-md">
    <DialogHeader>
      <DialogTitle>Pair Reader</DialogTitle>
      <DialogDescription>Enter the device name and reader model to pair.</DialogDescription>
    </DialogHeader>
    <form class="space-y-4" @submit.prevent="$emit('submit')">
      <div class="field">
        <Label>Device name</Label>
        <Input v-model="form.name" type="text" placeholder="Front counter" />
      </div>
      <div class="select-row">
        <Label>Reader model</Label>
        <select v-model="form.model" class="select">
          <option value="s1">SumUp Solo</option>
          <option value="s2">SumUp Air</option>
        </select>
      </div>
      <DialogFooter>
        <Button variant="outline" @click="$emit('update:open', false)">Cancel</Button>
        <Button type="submit">Pair reader</Button>
      </DialogFooter>
    </form>
  </DialogContent>
</Dialog>
```

The other 5 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=frontend/vue-components` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `compound-inline-component`, `deep-data-reach`, `deep-nested`, `duplicate-element`, `prop-drilling`, `prop-mutation`.
- `vendor/bin/commandments repent --sin=<sin>` — auto-fix, for `compound-inline-component`, `deep-data-reach`, `deep-nested`, `duplicate-element`. Review it with `--dry-run` first.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 6 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`frontend/vue-control-flow`](../vue-control-flow/SKILL.md) — the other half of an honest template — dispatch with `<SwitchCase>`, don't re-test a subject with `v-if` chains.
