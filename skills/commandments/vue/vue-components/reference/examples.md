# Vue components — extract repetition, deep reaches and dispatched views — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### compound-inline-component

A compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`…) assembled inline with a substantial body — extract it into its own named component.

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

<ReaderPairingDialog v-model:open="open" :form="form" @submit="submit" />
```

### deep-data-reach

A group of elements in a sizeable template that all reach deep into the same nested object (≥2 distinct fields) — extract the shared mid-object into a component that takes it as a prop.

```vue
----------[ Bad ]----------

<section class="order-detail__customer">
  <h2 class="section-title">Customer</h2>
  <p class="customer-name">{{ order.customer.fullName }}</p>
  <p class="customer-email">{{ order.customer.email }}</p>
  <p class="customer-phone">{{ order.customer.phone }}</p>
</section>

----------[ Good ]----------

<OrderCustomer :customer="order.customer" />
```

### deep-nested

Template markup nested far too deep — extract a subtree as its own component

```vue
----------[ Bad ]----------

<div class="settings-card__body">
  <div class="accordion">
    <div class="accordion__item">
      <div class="accordion__panel">
        <div class="field-group">
          <div class="field-grid">
            <div class="field-grid__row">
              <div class="field">
                <div class="field__control">
                  <div class="field__input-wrap">
                    <div class="field__inner">
                      <label class="field__label">{{ settings.profile.displayName }}</label>
                      <input class="field__input" :value="settings.profile.handle" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

----------[ Good ]----------

<SettingsCardBody :settings="settings" />
```

### duplicate-element

Identical markup (3+ elements) repeated 2+ times — within a template or across components — extract one component

```vue
----------[ Bad ]----------

<!-- in BasketPromoBanner.vue -->
<div class="promo-strip">
  <span class="promo-icon">%</span>
  <strong class="promo-headline">Free shipping this week</strong>
  <small class="promo-terms">On orders over 50.</small>
</div>

<!-- in CheckoutPromoBanner.vue -->
<div class="promo-strip">
  <span class="promo-icon">%</span>
  <strong class="promo-headline">Free shipping this week</strong>
  <small class="promo-terms">On orders over 50.</small>
</div>

<!-- in FilterSidebar.vue -->
<fieldset class="filter-group">
  <legend class="filter-legend">Brand</legend>
  <label class="filter-option"><input type="checkbox" /> Any brand</label>
</fieldset>

<!-- in FilterSidebar.vue -->
<fieldset class="filter-group">
  <legend class="filter-legend">Brand</legend>
  <label class="filter-option"><input type="checkbox" /> Any brand</label>
</fieldset>

<!-- in OrderLineItems.vue -->
<tr class="line-item">
  <td class="line-item__name">Sample product</td>
  <td class="line-item__qty">1</td>
</tr>

<!-- in OrderLineItems.vue -->
<tr class="line-item">
  <td class="line-item__name">Sample product</td>
  <td class="line-item__qty">1</td>
</tr>

<!-- in ProductReviewList.vue -->
<article class="review-card">
  <header class="review-head">
    <Avatar class="size-8" />
    <strong class="review-author">Verified buyer</strong>
  </header>
  <p class="review-body">Exactly as described, shipped fast.</p>
</article>

<!-- in ProductReviewList.vue -->
<article class="review-card">
  <header class="review-head">
    <Avatar class="size-8" />
    <strong class="review-author">Verified buyer</strong>
  </header>
  <p class="review-body">Exactly as described, shipped fast.</p>
</article>

----------[ Good ]----------

<template v-for="review in reviews" :key="review.id">
  <article class="review-card">
    <header class="review-head">
      <Avatar class="size-8" />
      <strong class="review-author">{{ review.author }}</strong>
    </header>
    <p class="review-body">{{ review.body }}</p>
  </article>
</template>
```

### inline-case-views

A dispatch whose cases each render a whole view inline — one component doing a job per case

```vue
----------[ Good ]----------

<SwitchCase :value="shipment.stage">
  <template #packing><ShipmentPacking :parcels="shipment.parcels" :packer="shipment.packer" /></template>
  <template #in_transit><ShipmentInTransit :carrier="shipment.carrier" :eta="shipment.eta" :stops="shipment.stops" /></template>
  <template #default><p>Delivered</p></template>
</SwitchCase>
```

### near-duplicate-element

Markup with one skeleton repeated 2+ times — the same tags, attributes and nesting binding different data — within a template, across components, or as two components' whole templates

```vue
----------[ Bad ]----------

<!-- in DeliverySlots.vue -->
<article class="slot">
    <h3>Standard</h3>
    <strong class="slot-price">{{ standard.price }}</strong>
    <ul class="slot-perks">
        <li>{{ standard.days }} working days</li>
        <li>Tracked parcel</li>
    </ul>
    <button type="button" @click="emit('choose', 'standard')">Choose standard</button>
</article>

<!-- in DeliverySlots.vue -->
<article class="slot slot-express">
    <h3>Express</h3>
    <strong class="slot-price">{{ express.price }}</strong>
    <ul class="slot-perks">
        <li>{{ express.days }} working day</li>
        <li>Signed for on delivery</li>
    </ul>
    <button type="button" @click="emit('choose', 'express')">Choose express</button>
</article>

<!-- in StockTables.vue -->
<tbody>
    <tr class="loading-row">
        <td colspan="4">
            <div role="status" class="loading">
                <span class="spinner" />
                <span class="sr-only">Loading stock</span>
                <small class="loading-hint">Counting the shelves</small>
            </div>
        </td>
    </tr>
</tbody>

<!-- in StockTables.vue -->
<tbody>
    <tr class="loading-row">
        <td colspan="6">
            <div role="status" class="loading">
                <span class="spinner" />
                <span class="sr-only">Loading orders</span>
                <small class="loading-hint">Fetching this week's orders</small>
            </div>
        </td>
    </tr>
</tbody>

----------[ Good ]----------

<aside class="slot-note">
    <h3>Collect in store</h3>
    <p>Free, ready in two hours.</p>
    <a href="/stores">Find a store</a>
</aside>
```

### prop-drilling

A prop forwarded through a chain of 2+ components, none of which read it — passed down through components that only pass it further.

```vue
----------[ Bad ]----------

<NotificationBell :items="notifications" />

----------[ Good ]----------

<UserAvatar :src="avatarUrl" />
```

### prop-mutation

A prop is written to — `v-model` bound to it, or `@event="prop = …"` — but props are read-only (a build error or a silent no-op).

```vue
----------[ Bad ]----------

<Collapsible v-model:open="expanded">
  <CollapsibleTrigger>Advanced</CollapsibleTrigger>
</Collapsible>

----------[ Good ]----------

<Collapsible v-model:open="panelOpen">
  <CollapsibleTrigger>Advanced</CollapsibleTrigger>
</Collapsible>
```
