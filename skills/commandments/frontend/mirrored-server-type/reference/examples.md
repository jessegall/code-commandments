# One source of truth for a server contract — generate the type, don't hand-copy it — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### mirrored-server-type — in TypeScript

A hand-written TypeScript type mirrors a backend `Data` class one-to-one — two sources of truth for one contract that drift the moment the server shape changes

```ts
----------[ Bad ]----------

export interface OrderData {
  id: string
  total: number
  placedAt: string
  status: string
}

----------[ Good ]----------

export type { OrderData } from '@/types/generated'
```

### mirrored-server-type — in Vue

A hand-written TypeScript type mirrors a backend `Data` class one-to-one — two sources of truth for one contract that drift the moment the server shape changes

```vue
----------[ Bad ]----------

interface CustomerData {
  first_name: string
  last_name: string
  email_address: string
  phone_number: string
}

----------[ Good ]----------

interface TableColumn {
  key: string
  label: string
  sortable: boolean
  width: number
}
```
