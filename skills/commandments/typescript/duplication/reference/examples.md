# TypeScript duplication — one behaviour, one home — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### duplicate-typescript-function — in TypeScript

Copy-pasted code — two+ TypeScript functions (a `function`, a method, a `const` arrow; in a `.ts` module or a component's script) with an identical body, formatting and comments aside

```ts
----------[ Bad ]----------

// in packing-slip.ts
rows(lines: Line[]): string[] {
    const rows: string[] = []
    for (const line of lines) {
        if (line.quantity <= 0) {
            continue
        }
        rows.push(`${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
    }
    return rows
}

// in packing-slip.ts
lineItems(lines: Line[]): string[] {
    const rows: string[] = []
    for (const line of lines) {
        if (line.quantity <= 0) {
            continue
        }
        rows.push(`${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
    }
    return rows
}

----------[ Good ]----------

// in order-lines.ts
export function describeLines(lines: Line[]): string[] {
    return lines
        .filter((line) => line.quantity > 0)
        .map((line) => `${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
}

// in order-lines.ts
rows(lines: Line[]): string[] {
    return describeLines(lines)
}

// in order-lines.ts
lineItems(lines: Line[]): string[] {
    return describeLines(lines)
}
```

### duplicate-typescript-function — in Vue

Copy-pasted code — two+ TypeScript functions (a `function`, a method, a `const` arrow; in a `.ts` module or a component's script) with an identical body, formatting and comments aside

```vue
----------[ Bad ]----------

<!-- in components/NewsletterSignup.vue -->
<script setup lang="ts">
// Two handlers in one component with one body: subscribing and re-subscribing post the same
// form and recover from the same failure, so the second is a copy of the first.
import { ref } from 'vue'

const email = ref('')
const failed = ref(false)

async function subscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}

async function resubscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}
</script>

<template>
    <form @submit.prevent="subscribe">
        <input v-model="email" type="email" />
        <button type="button" @click="resubscribe">Subscribe again</button>
    </form>
</template>

----------[ Good ]----------

<!-- in components/NewsletterForm.vue -->
<script setup lang="ts">
// The FIX: subscribing again is the same request, so there is one function and both buttons call it.
import { ref } from 'vue'

const address = ref('')
const rejected = ref(false)

async function subscribe(): Promise<void> {
    rejected.value = false
    await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: address.value }) })
        .catch(() => { rejected.value = true })
}
</script>

<template>
    <form @submit.prevent="subscribe">
        <input v-model="address" type="email" />
        <button type="button" @click="subscribe">Subscribe again</button>
    </form>
</template>
```

### near-duplicate-typescript-function — in TypeScript

A near-copy — two+ TypeScript functions with one control-flow skeleton that differ only in their local names or the literals they use (an endpoint, a key, a label)

```ts
----------[ Bad ]----------

// in review-feed.ts
export async function loadReviews(productId: number): Promise<number[]> {
    const response = await fetch(`/api/products/${productId}/reviews`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const reviews: Entry[] = await response.json()
    const shown = reviews.filter((review) => review.published)
    return shown.map((review) => review.id)
}

// in QuestionFeed.vue
const loadQuestions = async (productId: number): Promise<number[]> => {
    const result = await fetch(`/api/products/${productId}/questions`)
    if (!result.ok) {
        throw new Error(result.statusText)
    }
    const questions: Entry[] = await result.json()
    const answered = questions.filter((question) => question.published)
    return answered.map((question) => question.id)
}

----------[ Good ]----------

// in parcel-pricing.ts
export function priceParcel(parcel: Parcel, tariff: Tariff): number {
    const weighed = tariff.base + Math.ceil(parcel.kilos) * tariff.perKilo
    const sized = parcel.oversized ? weighed + tariff.oversizeFee : weighed
    return parcel.kilos > tariff.heavyFrom ? Math.round(sized * tariff.heavyFactor) : sized
}

// in parcel-pricing.ts
quote(parcel: Parcel): number {
    return priceParcel(parcel, { base: 695, perKilo: 120, oversizeFee: 450, heavyFrom: 20, heavyFactor: 1.15 })
}

// in parcel-pricing.ts
quote(parcel: Parcel): number {
    return priceParcel(parcel, { base: 950, perKilo: 85, oversizeFee: 700, heavyFrom: 30, heavyFactor: 1.1 })
}
```

### near-duplicate-typescript-function — in Vue

A near-copy — two+ TypeScript functions with one control-flow skeleton that differ only in their local names or the literals they use (an endpoint, a key, a label)

```vue
----------[ Bad ]----------

// in review-feed.ts
export async function loadReviews(productId: number): Promise<number[]> {
    const response = await fetch(`/api/products/${productId}/reviews`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const reviews: Entry[] = await response.json()
    const shown = reviews.filter((review) => review.published)
    return shown.map((review) => review.id)
}

// in QuestionFeed.vue
const loadQuestions = async (productId: number): Promise<number[]> => {
    const result = await fetch(`/api/products/${productId}/questions`)
    if (!result.ok) {
        throw new Error(result.statusText)
    }
    const questions: Entry[] = await result.json()
    const answered = questions.filter((question) => question.published)
    return answered.map((question) => question.id)
}

----------[ Good ]----------

async function post(action: string): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/${action}`, { method: 'POST' })
    if (response.ok) {
        emit('done', action)
    }
}
```
