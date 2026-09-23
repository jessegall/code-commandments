# C# absence — decide "missing" where the value is born — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-invented-default

`F(x ?? "")` — an empty string, `0` or `false` invented to fill an argument, or answered by a lookup helper on a miss, a stand-in the callee cannot tell from real data

```cs
----------[ Bad ]----------

public void Send(string? email, string body)
{
    mail(email ?? "", body);
}

----------[ Good ]----------

public void SendIfAddressed(string? email, string body)
{
    if (email is null)
    {
        return;
    }

    mail(email, body);
}
```

### csharp-null-forgiven

The null-forgiving `!` on a value declared nullable — the compiler told the caller it may be null, and `!` silences it instead of deciding

```cs
----------[ Bad ]----------

public IReadOnlyList<string> Emails() => customers.Where(customer => customer.Email != null).Select(customer => customer.Email!).ToList();

----------[ Good ]----------

public IReadOnlyList<string> Addresses() => customers.Select(customer => customer.Email).OfType<string>().ToList();
```
