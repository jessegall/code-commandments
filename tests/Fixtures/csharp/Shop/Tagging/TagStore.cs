namespace Shop.Tagging;

public interface ITagStore
{
    bool TryFind(string id, out string tag);
}

// Reading a tag the store answers for. The store says true exactly when it filled the tag, which its own
// declaration does not state for the compiler, so the read inside that branch is the one forgiveness a caller
// cannot avoid: the annotation is the store's to add.
public sealed class TagReader(ITagStore store)
{
    private string? last;

    // @righteous NullForgiven
    public string Of(string id)
    {
        if (store.TryFind(id, out var tag))
        {
            return tag!.Trim();
        }

        return "";
    }

    public string Missed(string id)
    {
        if (!store.TryFind(id, out var tag))
        {
            // @sin NullForgiven
            return tag!.Trim();
        }

        return "";
    }

    // @sin NullForgiven
    public string Last() => last!.Trim();
}
