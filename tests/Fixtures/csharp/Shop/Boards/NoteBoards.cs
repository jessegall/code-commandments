namespace Shop.Boards;

// The notes pinned to one board, kept in a list the board builds. The property hands that very list back, so
// any caller can pin or unpin without the board knowing, and the board cannot keep its own order. Below it,
// the repair: the caller is given something it cannot change, and the pinning stays with the board.
public sealed class NoteBoard
{
    private readonly List<string> notes = new();

    public void Pin(string note) => this.notes.Add(note);

    // @sin HandedOutCollection
    public List<string> Notes => this.notes;
}

// @fixed HandedOutCollection
public sealed class PinnedBoard
{
    private readonly List<string> notes = new();

    public void Pin(string note) => this.notes.Add(note);

    public IReadOnlyList<string> Notes => this.notes;
}
