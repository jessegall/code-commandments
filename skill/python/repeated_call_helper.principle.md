### The repeated keyword call

`dataclasses.replace(order, status="shipped")`, `settings.model_copy(update={"debug": True})`,
`point._replace(x=0)` — a generic "copy with changes" API is flexible, and at ONE site that flexibility is
the point. Written the same way at site after site, it is an operation the type should name:

```python
@dataclass(frozen=True)
class Order:
    status: str

    def shipped(self) -> "Order":
        return replace(self, status="shipped")
```

Now every site says `order.shipped()`. The generic `replace` stays for the genuinely one-off change.

### The repeated guard

The same compound condition — `if order.paid and not order.cancelled and order.lines:` — written in two
places is a question with no name. Spelled differently (a local here, an inline attribute there) or in
another order, it is still the same question. Name it where the data lives:

```python
@property
def is_shippable(self) -> bool:
    return self.paid and not self.cancelled and bool(self.lines)
```

and every site asks `if order.is_shippable:`. When the rule changes, it changes once.

### The repeated type guard

`isinstance(node, Call) and isinstance(node.func, Attribute)` copied from function to function is a check
on a shape that has no name. Give it one — a method, a property, or a `TypeGuard` function — so the shape is
declared once and every site asks for it by name.

### When it is NOT this sin

- **A one-off.** A call or a condition written once is exactly what the general tool is for.
- **Genuinely different arguments.** `replace(order, status=…)` here and `replace(order, note=…)` there are
  two operations, not one.
- **A simple check.** A single `if x is None:` or one `isinstance` is not a compound condition; repeating
  it names nothing new.