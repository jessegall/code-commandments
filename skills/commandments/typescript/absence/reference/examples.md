# TypeScript absence — say what is missing, and mean it — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### defended-certain-field

An `?.` on a field the class declares as always present — a defence against a case the type says cannot happen, which reads as doubt the design does not have

```ts
----------[ Bad ]----------

return this.customer?.name

----------[ Good ]----------

private shipment?: Shipment
```

### falsely-optional-field

A field declared optional (`x?: T`, `T | null`) that is initialised where it is declared — it is never absent, and every `?.` and `??` downstream defends a case that cannot happen

```ts
----------[ Bad ]----------

private items?: Item[] = []

----------[ Good ]----------

private coupon?: Coupon
```
