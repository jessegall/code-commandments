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