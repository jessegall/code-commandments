namespace Shop.Checkout;

public abstract record BasketFact;

public sealed record AddressGiven(string Postcode) : BasketFact;

public sealed record CarrierChosen(string Carrier) : BasketFact;

// A basket reading the facts it was told into its own slots. The facts travel in a message and cannot reach this
// state, so a member on a fact would need write access to the basket, which is worse than the one switch.
public sealed class Basket
{
    private string postcode = "";
    private string carrier = "";

    // @righteous TypeSwitch
    public void Apply(BasketFact fact)
    {
        switch (fact)
        {
            case AddressGiven given:
                postcode = given.Postcode;
                break;
            case CarrierChosen chosen:
                carrier = chosen.Carrier;
                break;
        }
    }

    public string Summary() => postcode + " " + carrier;
}
