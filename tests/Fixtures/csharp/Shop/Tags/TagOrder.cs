using System.Collections.Generic;

namespace Shop.Tags;

public sealed record OrderedTag(string Name, int Rank);

public static class TagOrder
{
    public static readonly OrderedTag[] All = {
        new OrderedTag("reply", 1),
        new OrderedTag("log", 2),
        new OrderedTag("todo", 3)
    };

    // @righteous SideTableTrait
    private static readonly List<string> VisibleOrder = new() { "reply", "log" };

    public static IEnumerable<string> OrderedNames()
    {
        foreach (var name in VisibleOrder) yield return name;
    }
}
