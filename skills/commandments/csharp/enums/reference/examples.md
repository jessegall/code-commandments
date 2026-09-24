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

### csharp-enum-case-or-chain

`s == Status.Paid || s == Status.Refunded` (or `s is Status.Paid or Status.Refunded`) — a group of enum cases tested by hand at the call site

```cs
----------[ Bad ]----------

public static string Note(DeliveryStage stage)
{
    if (stage == DeliveryStage.Shipped || stage == DeliveryStage.Delivered)
    {
        return "on its way";
    }

    return "still here";
}

----------[ Good ]----------

// in Fulfilment.cs
public static class DeliveryStageRules
{
    public static bool HasLeftTheWarehouse(this DeliveryStage stage) => stage switch
    {
        DeliveryStage.Shipped or DeliveryStage.Delivered => true,
        _ => false,
    };
}

// in Fulfilment.cs
public static string Noted(DeliveryStage stage) => stage.HasLeftTheWarehouse() ? "on its way" : "still here";
```

### csharp-in-array-mirrors-enum

`new[] { "paid", "refunded" }.Contains(status)` or `status is "paid" or "refunded"` — a list of strings that repeats the members of an enum the code already has

```cs
----------[ Bad ]----------

public static bool Settles(PaymentCallback callback) => new[] { "paid", "shipped" }.Contains(callback.Status);

----------[ Good ]----------

// in PaymentWebhooks.cs
public static class OrderStatusRules
{
    public static bool IsSettled(this OrderStatus status) => status switch
    {
        OrderStatus.Paid or OrderStatus.Shipped => true,
        _ => false,
    };
}

// in PaymentWebhooks.cs
public static bool Settled(PaymentCallback callback) => Enum.TryParse(callback.Status, ignoreCase: true, out OrderStatus status) && status.IsSettled();
```

### csharp-match-default-returns-null

a `switch` that names every member of an enum, then answers `null`, `default` or `false` in its `_` arm — the one value that arm can see is a bug, and it is answered as if it were fine

```cs
----------[ Bad ]----------

public static string? LogoFor(Courier courier) => courier switch
{
    Courier.Postal => "postal.svg",
    Courier.Express => "express.svg",
    Courier.Freight => "freight.svg",
    _ => null,
};

----------[ Good ]----------

public static string Logo(Courier courier) => courier switch
{
    Courier.Postal => "postal.svg",
    Courier.Express => "express.svg",
    Courier.Freight => "freight.svg",
    _ => throw new ArgumentOutOfRangeException(nameof(courier), courier, null),
};
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

### csharp-unnamed-vocabulary-literal

a raw string handed to a parameter the codebase elsewhere fills from a named constant — `Expect("{")` beside `Expect(Token.Colon)`, where `Token.BraceOpen` already names it

```cs
----------[ Bad ]----------

public int EndOfMonth() => runner.Run("monthly");

----------[ Good ]----------

public int Close() => runner.Run(ReportKind.Monthly);
```
