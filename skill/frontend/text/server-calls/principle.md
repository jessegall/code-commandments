### The rule

- **Every server address comes from the generated routes.** `route('plans.show', { plan: card.number })` or
  the generated action's `.url()`, never `'/plans/' + card.id`. The generated helper knows the path and the
  parameter it takes; a hand-written string knows neither, which is how one view builds a link from `card.id`
  while another uses `card.number`.
- **Components never call `fetch`.** One client module owns how the frontend talks to the server: the base
  path, the headers (CSRF, `Accept`, an `Idempotency-Key`), the error handling, the JSON parsing. A component
  calls that module, or the router (`router.post`, `useForm().submit`), and stays about what it shows.

### What is NOT this sin

- An external URL (`https://…`), a `mailto:` link, an asset path the bundler resolves.
- The one client module itself, which is where `fetch` belongs.
- A path the server does not declare as a route.

### The tell

You are typing a `/` at the start of a string in a component, or the word `fetch(`. Stop and import the
generated route or the client instead.
