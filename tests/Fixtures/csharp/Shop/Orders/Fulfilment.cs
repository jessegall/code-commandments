namespace Shop.Orders;

// Where a parcel is on its way to the customer.
public enum DeliveryStage
{
    Packed,
    Shipped,
    Delivered,
    Returned,
}

// @fixed EnumCaseOrChain
public static class DeliveryStageRules
{
    public static bool HasLeftTheWarehouse(this DeliveryStage stage) => stage switch
    {
        DeliveryStage.Shipped or DeliveryStage.Delivered => true,
        _ => false,
    };
}

public static class Fulfilment
{
    public static string Note(DeliveryStage stage)
    {
        // @sin EnumCaseOrChain
        if (stage == DeliveryStage.Shipped || stage == DeliveryStage.Delivered)
        {
            return "on its way";
        }

        return "still here";
    }

    // @fixed EnumCaseOrChain
    public static string Noted(DeliveryStage stage) => stage.HasLeftTheWarehouse() ? "on its way" : "still here";
}
