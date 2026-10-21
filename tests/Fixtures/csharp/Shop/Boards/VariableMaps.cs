namespace Shop.Boards;

// A chain whose only purpose is to assemble the map a caller then hands to the wire: every method answers with
// the map itself, so there is nothing to do with it but chain into it and take what it built. Handing the
// dictionary over is the whole point, and the chain keeps no invariant once it has.
public sealed class VariableMap
{
    private readonly Dictionary<string, object> data = new();

    public VariableMap Set(string key, object value)
    {
        this.data[key] = value;

        return this;
    }

    public VariableMap SetAll(IReadOnlyDictionary<string, object> values)
    {
        foreach (var held in values)
        {
            this.data[held.Key] = held.Value;
        }

        return this;
    }

    // @righteous HandedOutCollection
    public Dictionary<string, object> Build() => this.data;
}
