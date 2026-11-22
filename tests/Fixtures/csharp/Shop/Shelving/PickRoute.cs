namespace Shop.Shelving;

public static class PickRoute
{
    // @sin RepeatedStaticFactory
    public static string Stop(string written) => $"{ShelfCode.Parse(written).Aisle}/{ShelfCode.Parse(written).Slot}";

    // @fixed RepeatedStaticFactory
    public static string StopOnce(string written)
    {
        var code = ShelfCode.Parse(written);

        return $"{code.Aisle}/{code.Slot}";
    }
}
