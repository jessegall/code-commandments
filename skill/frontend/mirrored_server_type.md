> When a backend `Data` class and a frontend type describe the same shape, only the server's is written by hand.
> The frontend type is generated from it.
> Read this before you write a TypeScript `type` or `interface` for data that comes from the server, and when a
> mirrored-server-type finding points here.
> An arrow in an example (`←`) marks the line the lesson is about.

## The principle

**The server owns the shape. Generate the frontend type; never keep a copy by hand.**

A hand-written copy of a server contract is right on the day it is written. After that, a field is added or
renamed on the server, the copy stays as it was, and nothing complains until a page shows `undefined`.

`spatie/laravel-typescript-transformer` removes the copy: mark the `Data` class `#[TypeScript]`, run the generator
(`php artisan typescript:transform`), and import the generated type. From then on a change to the class is a change
to the type, and the compiler finds every place that must follow.

How to tell a copy from a coincidence: the name and the set of fields line up with a server class (spelling aside:
`first_name` on one side and `firstName` on the other is the same field). A view-model that exists only in the
frontend, with no server class behind it, is not this sin: it has one source, itself.

## The sins

### mirrored-server-type

A hand-written TypeScript type that mirrors a backend `Data` class field for field.

```ts
// Bad: resources/js/types/order.ts
export interface OrderData {      // ← a copy of App\Data\OrderData, kept by hand
  number: string
  total: number
  status: 'pending' | 'paid' | 'shipped'
}

// Good: the type is generated, and imported
import type { OrderData } from '@/types/generated'
```

```php
// The one place the shape is written
#[TypeScript]
final class OrderData extends Data
{
    public function __construct(
        public readonly string $number,
        public readonly int $total,
        public readonly OrderStatus $status,
    ) {}
}
```
