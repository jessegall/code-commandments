# C# absence — decide "missing" where the value is born — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-blank-string-default

a `string` parameter or property defaulted to `""` and then checked with `== ""` or `string.IsNullOrEmpty` — the blank is being used to mean "missing"

```cs
----------[ Bad ]----------

public static string Of(string heading, string strapline = "")
{
    if (strapline == "")
    {
        return heading;
    }

    return $"{heading} — {strapline}";
}

----------[ Good ]----------

public static string Lined(string heading, string? strapline = null)
{
    if (strapline is null)
    {
        return heading;
    }

    return $"{heading} — {strapline}";
}
```

### csharp-cancelled-coalesce

a `??` fallback compared against the same value it falls back to — `(name ?? "") != ""` — so "missing" and "empty" end up in one branch without saying so

```cs
----------[ Bad ]----------

public static bool HasPostcode(string? postcode) => (postcode ?? "") != "";

----------[ Good ]----------

public static bool IsGiven(string? postcode) => postcode is not null && postcode != "";
```

### csharp-de-nulled-finder

a finder returning a nullable object whose every caller asserts it is there — `Find(id)!`, `Find(id) ?? throw …` — a miss the finder should have refused itself

```cs
----------[ Bad ]----------

public CouponCode? Find(string code) => byCode.GetValueOrDefault(code);

----------[ Good ]----------

public CouponCode Get(string code) => byCode.TryGetValue(code, out var coupon) ? coupon : throw new KeyNotFoundException(code);
```

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
