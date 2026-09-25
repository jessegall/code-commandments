### The repeated copy or call

`order with { Status = OrderStatus.Shipped }` — a `with` expression is flexible, and at one site that
flexibility is the point. Written the same way at site after site, it is an operation the type should name:

```csharp
public sealed record Order(OrderStatus Status)
{
    public Order Shipped() => this with { Status = OrderStatus.Shipped };
}
```

Now every site says `order.Shipped()`. The same goes for a call that keeps passing the same named argument
(`Format(total, currency: "EUR")`): give that call a name. The general form stays for the real one-off.

### The repeated guard

The same compound condition — `if (order.IsPaid && !order.IsCancelled && order.Lines.Count > 0)` —
written in two places is a question with no name. Written with different spacing or in another order, it is
still the same question. Name it where the data lives:

```csharp
public bool IsShippable => IsPaid && !IsCancelled && Lines.Count > 0;
```

and every site asks `if (order.IsShippable)`. When the rule changes, it changes once.

### The repeated type check

`node is InvocationExpression call && call.Target is MemberAccess` copied from method to method checks a
shape that has no name. Give it one — a method or a property on the type — so the shape is declared once
and every site asks for it by name.

### When it is NOT this sin

- **A one-off.** A call or a condition written once is exactly what the general tool is for.
- **Really different arguments.** `order with { Status = … }` here and `order with { Note = … }` there are
  two operations, not one.
- **A simple check.** A single `if (x is null)` or one `is` test is not a compound condition; repeating it
  names nothing new.