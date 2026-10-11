// @example SideTableTrait bad
using System.Collections.Generic;

namespace Shop.Tags;

public sealed record SpeechTag(string Name, string Command);

public static class TagSpeech
{
    public static readonly SpeechTag[] All = {
        new SpeechTag("reply", "message reply"),
        new SpeechTag("log", "work log"),
        new SpeechTag("todo", "todo create")
    };

    // @sin SideTableTrait
    private static readonly HashSet<string> Quiet = new() { "reply", "log" };

    public static bool SaidInChat(string name) => !Quiet.Contains(name);
}
