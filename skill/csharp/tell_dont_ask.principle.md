### Working it out from outside

```csharp
public static decimal Total(Order order) => order.Lines.Sum(line => line.Price * line.Quantity);
```

This method knows how an order is built — it has lines, a line has a price and a quantity — and works the
total out from outside. Every caller that needs it either calls this helper or writes the loop again. The
knowledge belongs on the order:

```csharp
public sealed class Order
{
    public decimal Total() => Lines.Sum(line => line.Subtotal);
}
```

Now `order.Total()` is asked, not computed at the caller, and a change to how an order is built changes one
class.

### The type switch

```csharp
var area = shape switch
{
    Circle c => Math.PI * c.Radius * c.Radius,
    Square s => s.Side * s.Side,
    _ => throw new UnknownShape(shape),
};
```

asks each value what it IS so the caller can decide what to do. Every new shape means finding every switch.
Tell instead: give the base type an abstract member — `shape.Area()` — and let each type answer for itself.

### What is NOT this sin

- **A policy over flat fields.** A pricing strategy that reads a customer's tier and a basket's weight is a
  rule *about* the data, not the data's own behaviour; keeping it separate is a design choice.
- **Reading one property.** `order.Id` read to log it is not this; the sin is working out something the
  object could answer.
- **Parsing at the edge.** A type check that turns loose input (`JsonElement`, `object`) into your own types
  is where those types are made, not a switch over them.
- **A closed set you don't own.** Switching over types from a library you cannot change is the only place
  the per-type behaviour can live.