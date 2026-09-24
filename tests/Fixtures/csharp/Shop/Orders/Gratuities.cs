namespace Shop.Orders;

public sealed class Gratuity(decimal percent)
{
    public decimal On(decimal subtotal)
    {
        // set the tip from the subtotal and the percent
        // @sin RestatedComment
        var tip = subtotal * percent / 100;

        return Math.Round(tip, 2);
    }

    public long TerminalCents(decimal subtotal)
    {
        // card terminals only accept whole cents, so the tip is rounded before it is shown
        // @righteous RestatedComment
        var cents = Math.Round(subtotal * percent, MidpointRounding.AwayFromZero);

        return (long) cents;
    }

    public string Offered(decimal subtotal)
    {
        // @fixed RestatedComment
        var suggested = subtotal * percent / 100;

        return suggested.ToString("0.00");
    }
}
