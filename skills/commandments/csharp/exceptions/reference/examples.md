# C# exceptions — fail loud, named, at the source — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-generic-throw

`throw new Exception/InvalidOperationException("…")` — a failure that names nothing, described in prose at the throw site

```cs
----------[ Bad ]----------

public string Account(string carrier)
{
    if (!accounts.TryGetValue(carrier, out var account))
    {
        throw new Exception($"No carrier is registered as '{carrier}'.");
    }

    return account;
}

----------[ Good ]----------

// in CarrierDirectory.cs
public string AccountOf(string carrier)
{
    if (!accounts.TryGetValue(carrier, out var account))
    {
        throw UnknownCarrier.Named(carrier);
    }

    return account;
}

// in CarrierDirectory.cs
public sealed class UnknownCarrier : InvalidOperationException
{
    private UnknownCarrier(string message) : base(message) {}

    public static UnknownCarrier Named(string carrier) => new($"No carrier is registered as '{carrier}'.");
}
```

### csharp-swallowed-exception

A bare `catch` or `catch (Exception)` whose body is empty, continues, or returns nothing — every failure, expected or not, made to vanish

```cs
----------[ Bad ]----------

public Dictionary<string, int>? Load()
{
    try
    {
        return JsonSerializer.Deserialize<Dictionary<string, int>>(File.ReadAllText(Path.Combine(folder, "stock.json")));
    }
    catch (Exception)
    {
        return null;
    }
}

----------[ Good ]----------

public Dictionary<string, int> LoadOrEmpty()
{
    try
    {
        return JsonSerializer.Deserialize<Dictionary<string, int>>(File.ReadAllText(Path.Combine(folder, "stock.json"))) ?? [];
    }
    catch (FileNotFoundException)
    {
        return [];
    }
}
```

### csharp-wrapping-without-cause

a `catch` that throws a new exception without passing the caught one as its inner exception, so the original stack trace is lost

```cs
----------[ Bad ]----------

public static string Read(string path)
{
    try
    {
        return File.ReadAllText(path);
    }
    catch (IOException e)
    {
        throw new StockFileUnreadable($"Could not read the stock file: {e.Message}");
    }
}

----------[ Good ]----------

public static string ReadKeepingTheCause(string path)
{
    try
    {
        return File.ReadAllText(path);
    }
    catch (IOException e)
    {
        throw new StockFileUnreadable("Could not read the stock file.", e);
    }
}
```
