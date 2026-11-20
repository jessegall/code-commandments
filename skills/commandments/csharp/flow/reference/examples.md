# C# flow — guard at the top, keep the body flat — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-collection-mutated-while-iterated

a `foreach` that adds to or removes from the very collection it walks — the enumerator reads a collection that moved under it, which throws InvalidOperationException

```cs
----------[ Bad ]----------

public static List<string> Restock(List<string> shelves, IReadOnlyCollection<string> below)
{
    foreach (var shelf in shelves)
    {
        if (below.Contains(shelf))
        {
            shelves.Add($"{shelf}-reserve");
        }
    }

    return shelves;
}

----------[ Good ]----------

public static List<string> Restocked(List<string> shelves, IReadOnlyCollection<string> below)
{
    var reserves = shelves.Where(shelf => below.Contains(shelf)).Select(shelf => $"{shelf}-reserve").ToList();
    shelves.AddRange(reserves);

    return shelves;
}
```

### csharp-inline-throw

a `?? throw` inside a call's argument or in front of a member call — the check that stops the method is hidden in the middle of the work

```cs
----------[ Bad ]----------

public static string Upper(string? country)
{
    return Normalise(country ?? throw new AddressIncomplete("country"));
}

----------[ Good ]----------

public static string CheckedUpper(string? country)
{
    var given = country ?? throw new AddressIncomplete("country");

    return Normalise(given);
}
```
