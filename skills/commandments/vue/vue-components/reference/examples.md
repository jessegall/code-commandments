# Vue components — extract repetition, deep reaches and dispatched views — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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
