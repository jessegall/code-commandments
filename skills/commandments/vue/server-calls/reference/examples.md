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

<a :href="'/plans/' + card.id">{{ card.title }}</a>

----------[ Good ]----------

<a href="https://help.example.com/plans/archive">Help</a>
```

### raw-fetch-in-component — in Vue

A Vue component calls the global `fetch` itself instead of the one client module

```vue
----------[ Good ]----------

// The one place the shop's stock screens ask the server for stock: the path, the header and the parsing live here.
export async function stockLevels(sku: string): Promise<number[]> {
    const response = await fetch(`/api/stock/${sku}`, { headers: { Accept: 'application/json' } })

    return response.json()
}
```
