# C# tell, don't ask — behaviour lives with its data — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-feature-envy

a method that loops another object's collection or writes its members, reaching into it more than into its own state — behaviour exiled from the object it works on

```cs
----------[ Bad ]----------

public int Weigh(Tote tote)
{
    var grams = 0;

    foreach (var item in tote.Items)
    {
        grams += item.Grams;
    }

    return grams;
}

----------[ Good ]----------

public int TotalGrams() => Items.Sum(item => item.Grams);
```

### csharp-type-switch

`shape switch { Circle c => …, Square s => … }` — asking which of your own types a value is, to decide what to do with it

```cs
----------[ Bad ]----------

public static string Label(Promotion promotion) => promotion switch
{
    PercentOff p => $"{p.Percent}% off",
    AmountOff a => $"{a.Amount / 100m:C} off",
    _ => "",
};

----------[ Good ]----------

// in Promotions.cs
// A promotion a basket can carry, and what it takes off the price.
public abstract class Promotion
{
    public abstract int Discount(int cents);

    public abstract string Label();
}

// in Promotions.cs
public override string Label() => $"{Percent}% off";

// in Promotions.cs
public override string Label() => $"{Amount / 100m:C} off";

// in Promotions.cs
public static string Describe(Promotion promotion) => promotion.Label();
```
