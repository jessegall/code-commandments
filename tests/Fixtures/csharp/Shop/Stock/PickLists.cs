namespace Shop.Stock;

// Prints the pick list a picker walks the aisles with.
public static class PickLists
{
    public static List<string> Lines(List<string>? skus)
    {
        var lines = new List<string>();

        // @sin CoalescedLoopSubject
        foreach (var sku in skus ?? [])
        {
            lines.Add($"[ ] {sku}");
        }

        return lines;
    }

    // @fixed CoalescedLoopSubject
    public static List<string> GuardedLines(List<string>? skus)
    {
        if (skus is null)
        {
            return [];
        }

        var lines = new List<string>();

        foreach (var sku in skus)
        {
            lines.Add($"[ ] {sku}");
        }

        return lines;
    }
}
