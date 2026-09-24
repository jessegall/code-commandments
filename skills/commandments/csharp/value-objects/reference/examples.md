# C# value objects — give related data a type — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-array-return-bag

a method that returns `new Dictionary<string, object> { ["sku"] = …, ["qty"] = … }` — a record with fixed fields, handed back as a dictionary

```cs
----------[ Bad ]----------

public static Dictionary<string, object> Footer(string orderId, int totalCents, DateOnly paidOn) => new()
{
    ["order"] = orderId,
    ["total"] = totalCents,
    ["paid"] = paidOn,
};

----------[ Good ]----------

// in Receipts.cs
public sealed record ReceiptFooter(string OrderId, int TotalCents, DateOnly PaidOn);

// in Receipts.cs
public static ReceiptFooter PaidToday(string orderId, int totalCents) => new(orderId, totalCents, DateOnly.FromDateTime(DateTime.Today));
```

### csharp-data-clump

The same three or more string, number, date or id parameters threaded through methods of two or more types — values that always travel together but have no type of their own.

```cs
----------[ Bad ]----------

// in LabelPrinter.cs
public string Print(string street, string city, string postcode) => $"{street}\n{postcode} {city}";

// in Quotes.cs
public decimal Price(string postcode, string city, string street) =>
    street.Length + city.Length > 40 ? perKilometre * 2 : postcode.StartsWith('1') ? perKilometre : perKilometre * 1.5m;

// in SalesReport.cs
public long Total(DateTime from, DateTime until, string zone) =>
    sales.Where(sale => sale.At >= from && sale.At < until && zone.Length > 0).Sum(sale => sale.Cents);

// in SalesReport.cs
public int Count(string zone, DateTime until, DateTime from) =>
    returns.Count(entry => entry.At >= from && entry.At < until && zone.Length > 0);

// in Listings.cs
public IReadOnlyList<string> Page(int page, int size, bool descending) =>
    (descending ? skus.OrderDescending() : skus.Order()).Skip(page * size).Take(size).ToList();

// in Listings.cs
public IReadOnlyList<string> Page(int page, int size, bool descending)
{
    var ordered = descending ? backorders.OrderByDescending(entry => entry.Missing) : backorders.OrderBy(entry => entry.Missing);

    return ordered.Skip(page * size).Take(size).Select(entry => $"{entry.Sku}: {entry.Missing}").ToList();
}

----------[ Good ]----------

// in LabelPrinter.cs
public string PrintFor(Address address) => $"{address.Street}\n{address.Postcode} {address.City}";

// in LabelPrinter.cs
public sealed record Address(string Street, string City, string Postcode);
```

### csharp-dictionary-bag

A string-keyed dictionary or JSON object read by keys written in the source — `row["sku"]`, `json.GetProperty("name")` — a record nobody declared

```cs
----------[ Bad ]----------

public int Units(IReadOnlyDictionary<string, string> row)
{
    return int.Parse(row["units"]) * 2;
}

----------[ Good ]----------

// in ImportRows.cs
public int UnitsOf(ImportRow row) => row.Units * 2;

// in ImportRows.cs
public sealed record ImportRow(string Sku, int Units)
{
    public static ImportRow From(IReadOnlyDictionary<string, string> row) => new(row["sku"], int.Parse(row["units"]));
}
```

### csharp-mutable-value-object

a record that can change after it is built — a `set` accessor, or a method that writes its own state — so two holders of the same value can end up seeing different things

```cs
----------[ Bad ]----------

public int Items { get; set; }

----------[ Good ]----------

// in Baskets.cs
public int Count { get; init; }

// in Baskets.cs
public Basket WithOneMore() => this with { Count = Count + 1 };
```
