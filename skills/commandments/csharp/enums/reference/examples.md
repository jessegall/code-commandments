# C# enums — a closed set is a type, with its knowledge on it — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-const-class-enum

a class that holds nothing but `const` strings or numbers — a closed set of values written as constants instead of an `enum`

```cs
----------[ Bad ]----------

public static class PaymentState
{
    public const string Pending = "pending";
    public const string Captured = "captured";
    public const string Refunded = "refunded";
}

----------[ Good ]----------

// in PaymentStates.cs
public enum PaymentStatus
{
    Pending,
    Captured,
    Refunded,
}

// in PaymentStates.cs
public static class PaymentStatusRules
{
    public static bool IsSettled(this PaymentStatus status) => status switch
    {
        PaymentStatus.Pending => false,
        PaymentStatus.Captured => true,
        PaymentStatus.Refunded => true,
    };
}
```

### csharp-string-mirrors-enum

A `switch` or an `if` ladder dispatching on strings that are the names of an enum the codebase already declares — the enum, written out again as text

```cs
----------[ Bad ]----------

public void Handle(string reference, string status)
{
    switch (status)
    {
        case "shipped":
            notify($"{reference} is on its way");
            break;
        case "delivered":
            notify($"{reference} has arrived");
            break;
    }
}

----------[ Good ]----------

public void HandleParsed(string reference, string status)
{
    switch (Enum.Parse<OrderStatus>(status, ignoreCase: true))
    {
        case OrderStatus.Shipped:
            notify($"{reference} is on its way");
            break;
        case OrderStatus.Delivered:
            notify($"{reference} has arrived");
            break;
    }
}
```
