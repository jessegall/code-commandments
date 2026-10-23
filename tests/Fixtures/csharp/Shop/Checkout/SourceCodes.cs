namespace Shop.Checkout;

public abstract record PaymentSource;

public sealed record CardSource(string Last4) : PaymentSource;

public sealed record VoucherSource(string Code) : PaymentSource;

public sealed record AccountSource(string Iban) : PaymentSource;

// The wire name of a payment source, which no source is in a position to know: the name belongs to the stored
// shape, not to the type, so the table that maps one to the other lives here.
public static class SourceCodes
{
    // @righteous TypeSwitch
    public static string Of(PaymentSource source) => source switch
    {
        CardSource => "card",
        VoucherSource => "voucher",
        AccountSource => "account",
        _ => "unknown",
    };
}
