### Seal the set

A value that can only ever be one of a handful — `"paid"`, `"pending"`, `"refunded"` — is an `enum`
(serialised by name with `JsonStringEnumConverter` where it must still read as its string on the
wire):

```csharp
public enum Status { Pending, Paid, Refunded }

public static class StatusRules
{
    public static bool IsSettled(this Status status) => status switch
    {
        Status.Pending => false,
        Status.Paid or Status.Refunded => true,
        _ => throw new ArgumentOutOfRangeException(nameof(status)),
    };
}
```

A typo is now a compile error where it is written, the compiler checks every `switch` covers every
case, and there is one place to read the whole set.

### Put the knowledge on the case

The per-case answers — a label, a colour, "is this final?" — live in one place beside the enum: an
extension method with one exhaustive `switch` expression, written with every case in view. A
comparison against string literals at a call site is that method, homeless: the next call site writes
it again, a little differently. When the cases differ in behaviour *and* data, the set is a sealed
class hierarchy, each case a type that answers for itself.

### Parse once, at the edge

A string arriving from JSON, a form or a database becomes the enum where it enters — the JSON
converter, or `Enum.Parse<Status>(raw)` — and fails there if it is not one of the cases. From then on
the code passes the enum, never the string.