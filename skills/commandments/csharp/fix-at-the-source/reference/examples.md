# C# fix at the source — fix a value where it is made, not where it breaks — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-constructor-side-effect

a constructor that calls a method on something it was handed and ignores the result — just creating the object changes something outside it

```cs
----------[ Bad ]----------

public NewsletterPreference(string address, MailingList list)
{
    this.address = address;

    list.Join(address);
}

----------[ Good ]----------

public sealed class NewsletterSignup(string address, MailingList list)
{
    public void Confirm() => list.Join(address);
}
```

### csharp-mutable-static-state

a `static` field that methods write to — state no instance owns, changed by whichever code ran last

```cs
----------[ Bad ]----------

// in Shop/Orders/OrderCounter.cs
namespace Shop.Orders;

// Numbers each order placed at the till.
public sealed class OrderNumbering
{
    private static int placed;

    public int Next()
    {
        placed++;

        return placed;
    }
}

----------[ Good ]----------

// in Shop/Orders/TillCounter.cs
namespace Shop.Orders;

// Numbers each order placed at one till: the count is the instance's own, so two tills never share it.
public sealed class TillCounter
{
    private int placed;

    public int Next() => ++placed;
}
```
