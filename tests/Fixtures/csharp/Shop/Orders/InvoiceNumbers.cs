namespace Shop.Orders;

// Hands out invoice numbers in a yearly series.
public sealed class InvoiceNumbers(int year)
{
    private int issued;

    public string Next() => $"{year}-{++issued:D5}";

    // @sin MemberAfterMethod
    private const string Prefix = "INV";

    public string Formatted() => $"{Prefix}/{Next()}";
}

// @fixed MemberAfterMethod
public sealed class CreditNoteNumbers(int year)
{
    private const string Prefix = "CN";

    private int issued;

    public string Next() => $"{Prefix}/{year}-{++issued:D5}";
}
