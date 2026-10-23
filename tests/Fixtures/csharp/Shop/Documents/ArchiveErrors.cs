namespace Shop.Documents;

public sealed class ArchiveError
{
    public string Said { get; }

    private ArchiveError(string said)
    {
        Said = said;
    }

    public static ArchiveError New() => new("");

    public ArchiveError Saying(string said) => new(said);

    public ArchiveError Causedby(Exception cause) => new(Said + cause.Message);
}

public sealed class ArchiveRefused(ArchiveError error) : Exception(error.Said);

// Reading an archive code a client sent. What is thrown carries the cause itself, handed to the builder the
// framework offers instead of a constructor, so the stack and the type travel with the refusal.
public sealed class ArchiveCodes
{
    public string Parse(string given)
    {
        try
        {
            return given.Trim();
        }
        // @righteous WrappingWithoutCause
        catch (FormatException ex)
        {
            throw new ArchiveRefused(ArchiveError.New().Saying("Invalid archive code.").Causedby(ex));
        }
    }
}
