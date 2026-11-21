# C# flow — guard at the top, keep the body flat — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-collection-mutated-while-iterated

a `foreach` that adds to or removes from the very collection it walks — the enumerator reads a collection that moved under it, which throws InvalidOperationException

```cs
----------[ Bad ]----------

// A reserve shelf added to the list the foreach is walking, which grows the list it is reading: every shelf
// added names the next one to add, so the walk never reaches its end.
public static class AisleBlocks
{
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

    public static List<string> Restocked(List<string> shelves, IReadOnlyCollection<string> below)
    {
        var reserves = shelves.Where(shelf => below.Contains(shelf)).Select(shelf => $"{shelf}-reserve").ToList();
        shelves.AddRange(reserves);

        return shelves;
    }
}

----------[ Good ]----------

// A reserve shelf added to the list the foreach is walking, which grows the list it is reading: every shelf
// added names the next one to add, so the walk never reaches its end.
public static class AisleBlocks
{
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

    public static List<string> Restocked(List<string> shelves, IReadOnlyCollection<string> below)
    {
        var reserves = shelves.Where(shelf => below.Contains(shelf)).Select(shelf => $"{shelf}-reserve").ToList();
        shelves.AddRange(reserves);

        return shelves;
    }
}
```

### csharp-inline-throw

a `?? throw` inside a call's argument or in front of a member call — the check that stops the method is hidden in the middle of the work

```cs
----------[ Bad ]----------

// The country an address ships to, as the carrier's two-letter code.
public static class CountryCodes
{
    public static string Upper(string? country)
    {
        return Normalise(country ?? throw new AddressIncomplete("country"));
    }

    public static string CheckedUpper(string? country)
    {
        var given = country ?? throw new AddressIncomplete("country");

        return Normalise(given);
    }

    private static string Normalise(string country) => country.Trim().ToUpperInvariant();
}

----------[ Good ]----------

// The country an address ships to, as the carrier's two-letter code.
public static class CountryCodes
{
    public static string Upper(string? country)
    {
        return Normalise(country ?? throw new AddressIncomplete("country"));
    }

    public static string CheckedUpper(string? country)
    {
        var given = country ?? throw new AddressIncomplete("country");

        return Normalise(given);
    }

    private static string Normalise(string country) => country.Trim().ToUpperInvariant();
}
```
