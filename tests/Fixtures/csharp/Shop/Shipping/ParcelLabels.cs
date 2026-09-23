namespace Shop.Shipping;

// The labels stuck on a parcel: its handling marks, each printed once.
public static class ParcelLabels
{
    public static HashSet<string> Marks(Dictionary<string, List<string>> byParcel, string parcel)
    {
        var marks = new HashSet<string>();
        byParcel.TryGetValue(parcel, out var found);

        // @sin CoalescedLoopSubject
        foreach (var mark in found ?? new List<string>())
        {
            marks.Add(mark.ToUpperInvariant());
        }

        return marks;
    }
}
