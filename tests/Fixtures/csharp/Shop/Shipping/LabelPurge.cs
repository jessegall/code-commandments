using System.IO;

namespace Shop.Shipping;

public sealed class LabelPurge(Action<string> purge, Action<int> audit)
{
    public int RemoveExpired(IEnumerable<string> names, ISet<string> retained)
    {
        var expired = 0;
        foreach (var name in names)
        {
            if (retained.Contains(name)) continue;
            // @sin RepeatedFolderPath
            var store = Path.Combine("warehouse/saved-labels", "expired.pdf");
            purge(store);
            audit(++expired);
        }
        return expired;
    }

    // @righteous RepeatedFolderPath
    public void RemoveDraft()
    {
        purge(Path.Combine("warehouse-drafts", "label.pdf"));
    }
}
