namespace Shop.Shipping;

// The account the shop holds with a courier, and how much credit is left on it.
public sealed class CourierAccount
{
    public CourierAccount(string courier, int creditCents)
    {
        Courier = courier;
        CreditCents = creditCents;
    }

    // @sin MemberAfterMethod
    public string Courier { get; }

    // @sin MemberAfterMethod
    public int CreditCents { get; }

    // @righteous MemberAfterMethod
    public bool IsOverdrawn => CreditCents < 0;
}
