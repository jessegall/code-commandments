namespace Shop.Boards;

// Reads a carrier's quote out for the depot board, in words and then as a line for the printer. The quote it
// is handed is typed, and both guards still ask whether it is null and answer with words of their own, so a
// quote nobody passed and a quote of nothing read the same on the board.
public sealed class QuoteSpeech
{
    public string Spoken(CarrierQuote quote)
    {
        // @sin ImpossibleGuard
        return quote != null ? $"{quote.Carrier} at {quote.Cents}" : "no quote";
    }

    public string Printed(CarrierQuote quote, string depot)
    {
        // @sin ImpossibleGuard
        if (quote == null)
        {
            return $"{depot}: nothing quoted";
        }

        return $"{depot}: {quote.Carrier} {quote.Cents}";
    }
}

public sealed class CarrierQuote(string carrier, int cents)
{
    public string Carrier { get; } = carrier;

    public int Cents { get; } = cents;
}
