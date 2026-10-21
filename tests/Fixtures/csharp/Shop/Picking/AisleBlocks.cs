namespace Shop.Picking;

// A reserve shelf added to the list the foreach is walking, which grows the list it is reading: every shelf
// added names the next one to add, so the walk never reaches its end.
public static class AisleBlocks
{
    public static List<string> Restock(List<string> shelves, IReadOnlyCollection<string> below)
    {
        foreach (var shelf in shelves)
        {
            if (below.Contains(shelf))
            {
                // @sin CollectionMutatedWhileIterated
                shelves.Add($"{shelf}-reserve");
            }
        }

        return shelves;
    }

    // @fixed CollectionMutatedWhileIterated
    public static List<string> Restocked(List<string> shelves, IReadOnlyCollection<string> below)
    {
        var reserves = shelves.Where(shelf => below.Contains(shelf)).Select(shelf => $"{shelf}-reserve").ToList();
        shelves.AddRange(reserves);

        return shelves;
    }
}
