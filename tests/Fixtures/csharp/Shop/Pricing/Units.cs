namespace Shop.Pricing;

public readonly record struct Grams(int Value);

public readonly record struct Litres(decimal Value);

public readonly record struct Pieces(int Value);

public readonly record struct Metres(decimal Value);

// A generic label asks which type it was called with, rung by rung — the behaviour belongs on the
// types themselves, not in a ladder over `typeof(T)`.
public static class Units
{
    public static string Suffix<T>()
    {
        // @sin SubjectLadder
        if (typeof(T) == typeof(Grams))
        {
            return "g";
        }
        else if (typeof(T) == typeof(Litres))
        {
            return "l";
        }
        else if (typeof(T) == typeof(Pieces))
        {
            return "pcs";
        }
        else if (typeof(T) == typeof(Metres))
        {
            return "m";
        }

        throw new NotSupportedException(typeof(T).Name);
    }

    // @righteous SubjectLadder
    public static string Band(int grams, bool fragile, bool express, string region)
    {
        if (grams > 20_000)
        {
            return "freight";
        }
        else if (fragile)
        {
            return "care";
        }
        else if (express)
        {
            return "priority";
        }
        else if (region == "island")
        {
            return "ferry";
        }

        return "standard";
    }
}
