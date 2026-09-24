namespace Shop.Stock;

// What a shelf audit found, and the error it raises when the count is off.
public interface IAuditFailure
{
    IReadOnlyDictionary<string, object> Details { get; }
}

public sealed class CountMismatch(string shelf, int expected, int counted) : IAuditFailure
{
    // @righteous ArrayReturnBag
    public IReadOnlyDictionary<string, object> Details => new Dictionary<string, object>
    {
        ["shelf"] = shelf,
        ["expected"] = expected,
        ["counted"] = counted,
    };

    public Dictionary<string, string> Labels()
    {
        // @sin ArrayReturnBag
        return (new Dictionary<string, string> { ["title"] = $"Shelf {shelf}", ["subtitle"] = $"{counted} of {expected}" });
    }
}
