> A part of a template that repeats, reaches deep into data, nests too deep or does a job of its own is a component
> that has not been extracted yet.
> Read this before you copy a block of template, reach `a.b.c` in a large component, write a whole view inside one
> case of a dispatch, pass a prop on to a child untouched, or write to a prop, and when a vue-components finding
> points here.
> An arrow in an example (`←`) marks the line the lesson is about.

## The principle

**Lift one coherent unit out of the template, give it a name, and pass it only what it shows.**

A template earns a new component when a piece of it:

- **repeats**, with the same data or with different data in the same skeleton;
- **reaches deep**: an element that reads `order.customer.address.city` knows the shape of an object two steps
  away. A child that takes `order.customer` as a prop depends only on what it renders;
- **nests too deep**: lift the subtree at its natural boundary (the `<li>` of a list, the top of a panel), not a
  random node in the middle;
- **does a job per case**: a dispatch whose cases each render a whole view inline;
- **is a compound primitive** (a dialog, a card, a sheet, tabs) assembled inline with a real body.

Name the component for what it is: `OrderLineList` and `OrderLineListItem` for a list, `OrderSummarySection` for a
panel, `RefundOrderDialog` for a dialog.

Data flows one way. A component reads its props and never writes them; a prop is passed to a child that uses it,
not through three components that only hand it on.

How many elements one component may render is the project's choice, declared in `.commandments/config.json`.
Nothing is judged until it is declared:

```json
"configure": {
    "vue/ComponentBudgetDetector": [
        {"elements": [50]}
    ]
}
```

## The sins

### duplicate-element

The same markup (three or more elements) written out two or more times, in one template or across components.
Extract one component.

```vue
<!-- Bad -->
<div class="stat"><span class="label">Orders</span><strong>{{ orders }}</strong></div>
<div class="stat"><span class="label">Orders</span><strong>{{ orders }}</strong></div>   <!-- ← the same block again -->

<!-- Good -->
<OrdersStat :count="orders" />
```

### near-duplicate-element

Markup with one skeleton repeated with different data: the same tags, attributes and nesting. What differs is the
props of the component it should be.

```vue
<!-- Bad -->
<div class="stat"><span class="label">Orders</span><strong>{{ orders }}</strong></div>
<div class="stat"><span class="label">Refunds</span><strong>{{ refunds }}</strong></div>   <!-- ← same skeleton, other data -->

<!-- Good -->
<StatTile label="Orders" :value="orders" />
<StatTile label="Refunds" :value="refunds" />
```

### deep-data-reach

Several elements in a large template that all reach deep into the same nested object. Extract a component that
takes that object as a prop.

```vue
<!-- Bad -->
<p>{{ order.customer.address.street }}</p>        <!-- ← three steps into order… -->
<p>{{ order.customer.address.postcode }}</p>      <!-- ← …again… -->
<p>{{ order.customer.address.city }}</p>          <!-- ← …and again -->

<!-- Good -->
<AddressBlock :address="order.customer.address" />
```

### deep-nested

Markup nested far too deep to read. Lift the subtree out at its natural boundary.

```vue
<!-- Bad -->
<section>
  <div class="panel">
    <ul>
      <li>
        <div class="row">
          <div class="cell">
            <span><strong>{{ line.name }}</strong></span>   <!-- ← seven levels down -->
          </div>
        </div>
      </li>
    </ul>
  </div>
</section>

<!-- Good -->
<section>
  <div class="panel">
    <OrderLineList :lines="order.lines" />
  </div>
</section>
```

### inline-case-views

A dispatch (`<SwitchCase>`, or a `v-if` chain on one subject) whose cases each render a whole view inline. The
component does one job per case. Give each case its own component; the dispatch only picks one. A case that is one
line stays inline.

```vue
<!-- Bad -->
<SwitchCase :value="shipment.stage">
  <template #packing>
    <h2>Packing</h2>                                  <!-- ← a whole view, written inside the case -->
    <ul><li v-for="parcel in shipment.parcels" :key="parcel.id">{{ parcel.label }}</li></ul>
    <p>Packed by {{ shipment.packer.name }}</p>
  </template>
  <template #transit> … another whole view … </template>
</SwitchCase>

<!-- Good -->
<SwitchCase :value="shipment.stage">
  <template #packing><ShipmentPacking :parcels="shipment.parcels" :packer="shipment.packer" /></template>
  <template #transit><ShipmentTransit :route="shipment.route" /></template>
</SwitchCase>
```

### compound-inline-component

A compound primitive (`Dialog`, `Card`, `Sheet`, `Tabs` …) assembled inline with a substantial body. Extract it into
a named component and pass `v-model` and props.

```vue
<!-- Bad -->
<Dialog v-model:open="refunding">                      <!-- ← a whole dialog, built inside the page -->
  <DialogHeader><DialogTitle>Refund order</DialogTitle></DialogHeader>
  <form @submit.prevent="refund"> … twenty lines of fields … </form>
  <DialogFooter><Button type="submit">Refund</Button></DialogFooter>
</Dialog>

<!-- Good -->
<RefundOrderDialog v-model:open="refunding" :order="order" />
```

### oversized-component

A component whose template renders more elements than the budget the project declared. It is doing several jobs.
Split it into children that each do one, and let the parent only compose them.

```vue
<!-- Bad: OrderPage.vue renders the header, the lines, the totals, the history and the actions itself -->

<!-- Good -->
<template>
  <OrderHeader :order="order" />
  <OrderLineList :lines="order.lines" />
  <OrderTotals :totals="order.totals" />
  <OrderHistory :events="order.events" />
</template>
```

### prop-drilling

A prop handed down through two or more components that do not read it. Each of them now depends on something it
does not use. Provide and inject it, or give the child that needs it the data directly.

```vue
<!-- Bad -->
<OrderPage :currency="currency" />
  <!-- OrderPage: -->   <OrderLineList :currency="currency" />     <!-- ← only passes it on -->
  <!-- OrderLineList: --> <OrderLine :currency="currency" />       <!-- ← only passes it on -->
  <!-- OrderLine reads it -->

<!-- Good -->
<script setup lang="ts">
// OrderPage.vue
provide(currencyKey, currency)

// OrderLine.vue
const currency = inject(currencyKey)
</script>
```

### prop-mutation

A prop that is written to: `v-model` bound to it, or `@event="prop = …"`. Props are read-only: it is a build error
or it silently does nothing. For two-way state use `defineModel`; otherwise emit an `update:` event and let the
parent own the value.

```vue
<!-- Bad -->
<script setup lang="ts">
const props = defineProps<{ quantity: number }>()
</script>

<template>
  <input v-model="props.quantity" type="number" />   <!-- ← writes to a prop -->
</template>

<!-- Good -->
<script setup lang="ts">
const quantity = defineModel<number>('quantity', { required: true })
</script>

<template>
  <input v-model="quantity" type="number" />
</template>
```
