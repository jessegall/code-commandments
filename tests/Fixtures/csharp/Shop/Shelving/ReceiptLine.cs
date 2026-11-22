namespace Shop.Shelving;

public static class ReceiptLine
{
    public static string Describe(int cents, string currency, int quantity)
    {
        var unit = Price.Of(cents, currency).Cents;
        var total = unit * quantity;

        // @sin RepeatedStaticFactory
        return $"{quantity} x {unit} {Price.Of(cents, currency).Currency} = {total}";
    }

    // @fixed RepeatedStaticFactory
    public static string DescribeOnce(int cents, string currency, int quantity)
    {
        var price = Price.Of(cents, currency);

        return $"{quantity} x {price.Cents} {price.Currency} = {price.Cents * quantity}";
    }
}
