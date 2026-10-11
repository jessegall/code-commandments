// @example SideTableTrait good
using System.Collections.Generic;
using System.Linq;

namespace Shop.Tags;

// @fixed SideTableTrait
public sealed record SpokenTag(string Name, string Command, bool SaidInChat);

public static class TagSpeechFixed
{
    // @fixed SideTableTrait
    public static readonly SpokenTag[] All = {
        new SpokenTag("reply", "message reply", false),
        new SpokenTag("log", "work log", false),
        new SpokenTag("todo", "todo create", true)
    };

    // @fixed SideTableTrait
    private static readonly HashSet<string> Quiet = All.Where(tag => !tag.SaidInChat)
        .Select(tag => tag.Name).ToHashSet();

    // @fixed SideTableTrait
    public static bool SaidInChat(SpokenTag tag) => tag.SaidInChat;
}
