> The server already declares its routes. The frontend reaches them through the generated route helpers and one
> client module, never through a path typed by hand or a `fetch` in a component.
> Read this before you call `fetch(` in a component, write a server path such as `'/plans/' + id`, or set request
> headers by hand, and when a server-calls finding points here.
> An arrow in an example (`←`) marks the line the lesson is about.

## The principle

**Every server address comes from the generated routes, and one module owns how the frontend talks to the server.**

- **Addresses.** `route('plans.show', { plan: card.number })`, or the generated action's `.url()`. The helper
  knows the path and the parameter it takes. A string written by hand knows neither, which is how one view builds
  a link from `card.id` while another uses `card.number`, and how a moved route breaks links with no error.
- **Calls.** One client module owns the base path, the headers (CSRF, `Accept`), the error handling and the
  parsing. A component calls that module, or the router (`router.post`, `useForm().submit`), and stays about what
  it shows.

Not this sin: an external URL (`https://…`), a `mailto:` link, an asset path the bundler resolves, a path the
server does not declare as a route, and the client module itself, which is where `fetch` belongs.

## The sins

### hard-coded-server-url

A string written out as the path of a route the server declares by name. Build it with the generated route helper.

```vue
<script setup lang="ts">
// Bad
const url = '/plans/' + card.id                             // ← the path of plans.show, typed by hand

// Good
const url = route('plans.show', { plan: card.number })
</script>
```

### raw-fetch-in-component

A component that calls the global `fetch` itself. It now carries the headers, the error handling and the parsing,
and the next component will carry a slightly different copy. Call the client module.

```vue
<script setup lang="ts">
// Bad
async function archive() {
  const response = await fetch(`/plans/${plan.id}/archive`, {   // ← the component talks to the server itself
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
  })
  plan.value = await response.json()
}

// Good
async function archive() {
  plan.value = await client.post(route('plans.archive', { plan: plan.number }))
}
</script>
```
