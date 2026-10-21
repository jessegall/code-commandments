namespace Shop.Boards;

// The screen a customer stands at, which the till asks about while they are there. The guard below answers "no
// minutes left" for a session the type says is always there, so the till can never tell a real zero from a
// session nobody passed.
public sealed class KioskScreen
{
    private const int Idle = 0;

    public int MinutesLeft(KioskSession session)
    {
        // @sin ImpossibleGuard
        if (session == null)
        {
            return Idle;
        }

        return session.Minutes;
    }

    public bool IsIdle(KioskSession session) => this.MinutesLeft(session) == Idle;
}

public sealed class KioskSession(string token, int minutes)
{
    public string Token { get; } = token;

    public int Minutes { get; } = minutes;
}
