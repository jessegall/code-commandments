namespace Shop.Feeds;

public sealed record ListingKeys(string Sku, string Title);

// Reading a listing's keys back from the shape a feed stored them in. The mapping lives here rather than on the
// record, because the stored shape is the feed's and the record should not know it: that is the conversion at the
// edge the rule asks for, and the keys read on the way are the reading.
public static class ListingKeyMaps
{
    // @righteous DictionaryBag
    public static ListingKeys FromStored(IReadOnlyDictionary<string, string> stored)
    {
        var sku = stored["sku"];
        var title = stored["title"];

        return new ListingKeys(sku, title);
    }
}
