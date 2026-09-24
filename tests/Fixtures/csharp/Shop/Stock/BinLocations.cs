namespace Shop.Stock;

/// <summary>Renamed from ShelfSlot; used to be keyed by the aisle number.</summary>
// @sin ArchaeologyComment
public sealed record BinLocation(string Aisle, int Shelf)
{
    // the aisle letter the pickers read off the floor signs
    // @righteous ArchaeologyComment
    public string Label => $"{Aisle}{Shelf}";
}
