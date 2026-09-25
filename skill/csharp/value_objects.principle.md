### A record is a type

When the keys of a dictionary are known in advance — the same handful, read by name — the dictionary
is a record, and a record is a type:

```csharp
public sealed record Line(string Sku, int Quantity, long UnitPriceCents)
{
    public long Total => Quantity * UnitPriceCents;
}
```

The members are declared once, the compiler sees every read, a missing value fails where the object
is built, and a `record` cannot be changed behind your back — `with` makes the changed copy.
Behaviour that reads only those members — a total, a label — becomes a member of it.

### Build it at the edge

Loose data arrives as dictionaries and JSON: a request body, a form, a row. Turn it into the type
**where it enters** — `JsonSerializer.Deserialize<Line>(json)`, or one `Line.From(row)` factory — and
pass the type from there on. The rest of the program never sees the dictionary, so it never has to
wonder which keys are there, or cast what they hold.

### Values that always travel together are one value

Three parameters that every caller passes side by side — `street, city, postcode` — are an `Address`
waiting to be named. Give them one type and pass that; a small one is a `readonly record struct`.
A tuple returned and taken apart by position is the same thing unnamed.

### What is NOT this sin

- A dictionary used as a **mapping**: keys that are data (a SKU → stock level), iterated or looked up
  by a value you did not write in the source.
- The dictionary a serializer or a framework hands you right before you convert it, and options
  forwarded unchanged.