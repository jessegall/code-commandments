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

<!-- in ReaderPairingPanel.vue -->
<ReaderPairingDialog v-model:open="open" :form="form" @submit="submit" />

<!-- in ReaderPairingDialog.vue -->
<Dialog :open="open" @update:open="$emit('update:open', $event)">
  <DialogContent class="pairing-dialog">
    <DialogHeader>
      <DialogTitle>Pair Reader</DialogTitle>
    </DialogHeader>

    <form class="pairing-form" @submit.prevent="$emit('submit')">
      <Label class="pairing-form__label" for="device">Device name</Label>
      <Input id="device" v-model="form.name" placeholder="Front counter" />

      <Label class="pairing-form__label" for="model">Reader model</Label>
      <select id="model" v-model="form.model" class="pairing-form__select">
        <option value="s1">SumUp Solo</option>
        <option value="s2">SumUp Air</option>
      </select>

      <DialogFooter>
        <Button type="submit">Pair reader</Button>
      </DialogFooter>
    </form>
  </DialogContent>
</Dialog>
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

<!-- in OrderDetailPanel.vue -->
<OrderCustomer :customer="order.customer" />

<!-- in OrderCustomer.vue -->
<section class="order-customer">
  <h2 class="section-title">Customer</h2>
  <p class="customer-name">{{ customer.fullName }}</p>
  <p class="customer-email">{{ customer.email }}</p>
  <p class="customer-phone">{{ customer.phone }}</p>
</section>
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

<!-- in SettingsAccordionCard.vue -->
<SettingsCardBody :settings="settings" />

<!-- in SettingsCardBody.vue -->
<div class="settings-card__body">
  <div class="field-group">
    <label class="field__label">{{ settings.profile.displayName }}</label>
    <input class="field__input" :value="settings.profile.handle" />
  </div>
</div>
```

### duplicate-element

Identical markup (3+ elements) repeated 2+ times — within a template or across components — extract one component

```vue
----------[ Bad ]----------

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
----------[ Bad ]----------

<SwitchCase :value="shipment.stage">
  <template #packing>
    <section class="stage">
      <h3>Packing</h3>
      <ul class="parcels">
        <template v-for="parcel in shipment.parcels" :key="parcel.id">
          <li>
            <span class="label">{{ parcel.label }}</span>
            <span class="kilos">{{ parcel.kilos }} kg</span>
          </li>
        </template>
      </ul>
      <p class="note">Packed by {{ shipment.packer }}</p>
    </section>
  </template>
  <template #in_transit>
    <article class="transit">
      <header>
        <strong>{{ shipment.carrier }}</strong>
        <time>{{ shipment.eta }}</time>
      </header>
      <ol class="stops">
        <template v-for="stop in shipment.stops" :key="stop">
          <li><em>{{ stop }}</em></li>
        </template>
      </ol>
    </article>
  </template>
  <template #default>
    <p>Delivered</p>
  </template>
</SwitchCase>

----------[ Good ]----------

<!-- in ShipmentTracker.vue -->
<SwitchCase :value="shipment.stage">
  <template #packing><ShipmentPacking :parcels="shipment.parcels" :packer="shipment.packer" /></template>
  <template #in_transit><ShipmentInTransit :carrier="shipment.carrier" :eta="shipment.eta" :stops="shipment.stops" /></template>
  <template #default><p>Delivered</p></template>
</SwitchCase>

<!-- in ShipmentPacking.vue -->
<section class="packing">
  <h3>Packing</h3>
  <template v-for="parcel in parcels" :key="parcel.id">
    <p class="parcel"><span>{{ parcel.label }}</span> <span>{{ parcel.kilos }} kg</span></p>
  </template>
  <footer>Packed by {{ packer }}</footer>
</section>

<!-- in ShipmentInTransit.vue -->
<article class="in-transit">
  <h4>{{ carrier }}, arriving <time>{{ eta }}</time></h4>
  <template v-for="stop in stops" :key="stop">
    <span class="stop">{{ stop }}</span>
  </template>
</article>
```

### near-duplicate-element

Markup with one skeleton repeated 2+ times — the same tags, attributes and nesting binding different data — within a template, across components, or as two components' whole templates

```vue
----------[ Bad ]----------

<!-- in ShippingAddressPage.vue -->
<section class="address-page">
    <header class="address-head">
        <h2>Shipping address</h2>
        <p>Where we deliver your order.</p>
    </header>
    <form class="address-form">
        <label class="address-field">Street<input v-model="shipping.street" name="shipping-street" /></label>
        <label class="address-field">City<input v-model="shipping.city" name="shipping-city" /></label>
    </form>
</section>

<!-- in BillingAddressPage.vue -->
<section class="address-page billing">
    <header class="address-head">
        <h2>Billing address</h2>
        <p>Where we send your invoice.</p>
    </header>
    <form class="address-form">
        <label class="address-field">Street<input v-model="billing.street" name="billing-street" /></label>
        <label class="address-field">Town<input v-model="billing.city" name="billing-town" /></label>
    </form>
</section>

----------[ Good ]----------

<!-- in DeliveryOptions.vue -->
<div class="slots">
    <DeliveryOptionCard :option="standard" />
    <DeliveryOptionCard :option="express" />
</div>

<!-- in DeliveryOptionCard.vue -->
<article class="slot">
    <h3>{{ option.title }}</h3>
    <strong class="slot-price">{{ option.price }}</strong>
    <ul class="slot-perks">
        <template v-for="perk in option.perks" :key="perk">
            <li>{{ perk }}</li>
        </template>
    </ul>
    <button type="button" @click="emit('choose', option.key)">Choose {{ option.title }}</button>
</article>
```

### oversized-component

A component whose template renders more elements than the project's declared budget — one component doing several jobs

```vue
----------[ Bad ]----------

<!-- @sin OversizedComponent --><section class="account">
  <header class="account__header">
    <h1 class="account__title">My account</h1>
    <span class="account__id">#{{ customer.id }}</span>
  </header>

  <div class="account__profile">
    <h2 class="account__section">Profile</h2>
    <!-- Righteous: a LONE deep reach (one field off customer.profile) is no cluster. -->
    <p class="account__name">{{ customer.profile.displayName }}</p>
    <p class="account__handle">{{ customer.handle }}</p>
  </div>

  <div class="account__contact">
    <h2 class="account__section">Contact</h2>
    <p class="account__email">{{ customer.email }}</p>
    <p class="account__phone">{{ customer.phone }}</p>
  </div>

  <!-- A cluster: customer.billing read in three fields → extract <AccountBilling :billing>. -->
  <!-- @sin DeepDataReach -->
  <div class="account__billing">
    <h2 class="account__section">Billing</h2>
    <p class="account__plan">{{ customer.billing.plan }}</p>
    <p class="account__amount">{{ customer.billing.amount }}</p>
    <p class="account__renews">{{ customer.billing.renewsAt }}</p>
  </div>

  <div class="account__preferences">
    <h2 class="account__section">Preferences</h2>
    <ul class="account__prefs">
      <!-- @sin ControlFlowOnElement -->
      <li v-for="pref in customer.preferences" :key="pref.id" class="account__pref">
        <span class="account__pref-name">{{ pref.label }}</span>
        <span class="account__pref-value">{{ pref.enabled }}</span>
      </li>
    </ul>
  </div>

  <div class="account__orders">
    <h2 class="account__section">Recent orders</h2>
    <ul class="account__order-list">
      <!-- @sin IndexAsKey -->
      <template v-for="(order, index) in orders" :key="index">
        <li class="account__order">{{ order.reference }}</li>
      </template>
    </ul>
  </div>

  <div class="account__addresses">
    <h2 class="account__section">Addresses</h2>
    <ul class="account__address-list">
      <!-- @fixed IndexAsKey -->
      <!-- @righteous IndexAsKey -->
      <template v-for="(address, index) in customer.addresses" :key="address.id">
        <li class="account__address">{{ address.line }}</li>
      </template>
    </ul>
  </div>

  <footer class="account__footer">
    <button class="account__save" type="button">Save changes</button>
    <button class="account__signout" type="button">Sign out</button>
  </footer>
</section>

----------[ Good ]----------

<!-- in AccountIdentity.vue -->
<dl class="identity">
  <dt>Name</dt>
  <dd>{{ customer.handle }}</dd>
  <dt>Email</dt>
  <dd><a :href="`mailto:${customer.email}`">{{ customer.email }}</a></dd>
</dl>

<!-- in AccountPlan.vue -->
<aside class="plan">
  <strong>{{ billing.plan }}</strong>
  <small>{{ billing.amount }}, renews {{ billing.renewsAt }}</small>
</aside>

<!-- in AccountHistory.vue -->
<ol class="history">
  <template v-for="order in orders" :key="order.reference">
    <li>{{ order.reference }}</li>
  </template>
</ol>

<!-- in AccountOverview.vue -->
<section class="account">
  <h1 class="account__title">My account</h1>
  <AccountIdentity :customer="customer" />
  <AccountPlan :billing="customer.billing" />
  <AccountHistory :orders="orders" />
</section>
```

### prop-drilling

A prop forwarded through a chain of 2+ components, none of which read it — passed down through components that only pass it further.

```vue
----------[ Bad ]----------

<NotificationBell :items="notifications" />

----------[ Good ]----------

<!-- in AccountMenu.vue -->
<UserAvatar :src="avatarUrl" />

<!-- in UserAvatar.vue -->
<img :src="src" alt="" />
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
