# One way to the server: generated routes and one client — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### hard-coded-server-url — in TypeScript

A string written out as the path of a route the server declares by name

```ts
----------[ Bad ]----------

// Asks the server to print a return's shipping label and reads back where the label was stored.
export async function printReturnLabel(returnNumber: number, copies: number): Promise<string> {
    const response = await fetch(`/returns/${returnNumber}/label`, {
        method: 'POST',
        body: JSON.stringify({ copies }),
    })

    return (await response.json()).location
}
```

### hard-coded-server-url — in Vue

A string written out as the path of a route the server declares by name

```vue
----------[ Bad ]----------

function openBoard(): void {
    window.location.assign('/plans')
}

----------[ Good ]----------

const planAddress = route('plans.show', { plan: props.card.number })
```

### raw-fetch-in-component — in Vue

A Vue component calls the global `fetch` itself instead of the one client module

```vue
----------[ Bad ]----------

async function subscribe(): Promise<void> {
    rejected.value = false
    await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: address.value }) })
        .catch(() => { rejected.value = true })
}

----------[ Good ]----------

async function refresh(): Promise<void> {
    const levels = await stockLevels(props.sku)
    left.value = levels.reduce((total, level) => total + level, 0)
}
```
