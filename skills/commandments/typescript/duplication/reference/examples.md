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

// in stock-lookup.ts
export async function loadStockLevels(sku: string): Promise<number[]> {
    const response = await fetch(`/api/stock/${sku}`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const levels: StockLevel[] = await response.json()
    return levels.map((level) => level.available)
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

// in NewsletterSignup.vue
async function subscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}

// in NewsletterSignup.vue
async function resubscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}

// in RestockNotice.vue
const fetchAvailability = async (sku: string): Promise<number[]> => {
    const response = await fetch(`/api/stock/${sku}`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const levels: StockLevel[] = await response.json()
    return levels.map((level) => level.available)
}

----------[ Good ]----------

function isSoldOut(levels: number[]): boolean {
    return levels.length === 0
}
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

// in carrier-rates.ts
quote(parcel: Parcel): number {
    let cents = 695 + Math.ceil(parcel.kilos) * 120
    if (parcel.oversized) {
        cents += 450
    }
    if (parcel.kilos > 20) {
        cents = Math.round(cents * 1.15)
    }
    return cents
}

// in carrier-rates.ts
quote(parcel: Parcel): number {
    let price = 950 + Math.ceil(parcel.kilos) * 85
    if (parcel.oversized) {
        price += 700
    }
    if (parcel.kilos > 30) {
        price = Math.round(price * 1.1)
    }
    return price
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

// in PickListActions.vue
async function release(): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/release`, { method: 'POST' })
    if (response.ok) {
        emit('close')
    }
}

// in PickListActions.vue
async function pause(): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/pause`, { method: 'POST' })
    if (response.ok) {
        emit('close')
    }
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
