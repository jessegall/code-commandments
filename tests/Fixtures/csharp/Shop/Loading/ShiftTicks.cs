namespace Shop.Loading;

// The announcer that says the next run out loud. The condition asks the tick one thing, the board another and
// holds a third of its own, so what the three together MEAN is a sentence about the announcer that the
// announcer never says: it belongs on it, under a name.
public readonly record struct ShiftTick(int At);

public sealed class RunBoard
{
    private bool shown;

    public void Show() => shown = true;

    public bool IsBlank() => !shown;
}

public sealed class TickAnnouncer(int since)
{
    private int said;

    public void OnTicked(ShiftTick tick, RunBoard board, string? next)
    {
        // @sin UnnamedCondition
        if (tick.At > since && board.IsBlank() && next is not null)
        {
            board.Show();
            said++;
        }
    }

    public string Heard() => said switch
    {
        0 => "silent",
        1 => "said once",
        _ => $"said {said} times",
    };
}
