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

When a template **dispatches** — a `<SwitchCase>` on a value, or a `v-if` chain re-testing one subject — and
two or more of its cases each render a whole view inline, the component is doing one job per case. Give each
such case its own component, named for the case, and let the dispatch only pick one:
`<template #packing><ShipmentPacking :parcels="shipment.parcels" :packer="shipment.packer" /></template>`.
A case that renders a line or a single element stays inline; only a case that is a view of its own leaves.

How many jobs one component may hold is a call the project makes, not one the tree can: a card's header, body
and footer and a message bubble's quote, files, reactions and actions all look alike to a parser. So the project
**declares its budget** — how many elements a component's template may render, `<template>` wrappers aside — and
a component past it is split into single-purpose children the parent only composes. Nothing is judged until the
budget is declared:

```json
"configure": {
    "frontend/ComponentBudgetDetector": [
        {"elements": [50]}
    ]
}
```
