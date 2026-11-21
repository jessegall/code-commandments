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
