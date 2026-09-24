namespace Shop.Returns;

public sealed record InspectedItem(string Sku, bool Damaged, bool Opened);

public sealed class ReturnParcel
{
    public List<InspectedItem> Items { get; } = [];
}

public static class ReturnInspector
{
    // @sin FeatureEnvy
    public static bool NeedsRestocking(ReturnParcel parcel) => parcel.Items.Any(item => !item.Damaged && item.Opened);
}
