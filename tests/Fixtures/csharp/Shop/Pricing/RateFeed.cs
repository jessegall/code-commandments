namespace Shop.Pricing;

// Exchange rates come from a feed that is sometimes down; a timeout is reported differently from a bad answer.
public static class RateFeed
{
    public static decimal Rate(string answer)
    {
        try
        {
            return decimal.Parse(answer);
        }
        catch (Exception e) when (e is FormatException or OverflowException)
        {
            if (answer.Length == 0)
            {
                // @sin WrappingWithoutCause
                throw new TimeoutException("The rate feed sent nothing.");
            }

            throw new InvalidDataException($"The rate feed sent {answer}.", e);
        }
    }
}
