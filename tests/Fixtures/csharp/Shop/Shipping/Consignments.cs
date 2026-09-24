namespace Shop.Shipping;

public sealed class Consignment(List<decimal> parcelWeights)
{
    public decimal TotalWeight()
    {
        // return the sum
        // of the parcel weights
        // @sin RestatedComment
        return parcelWeights.Sum();
    }
}
