using System;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading.Tasks;

namespace Shop.Shipping;

public interface ILabelStore
{
    Task<string> ReadAsync(string id);
}

// Reading a label out of JSON. The converter's Read is declared by JsonConverter<T> and returns the label itself,
// so the store's task has to be waited for here: awaiting would mean changing a signature this code does not
// declare.
public sealed class LabelConverter(ILabelStore store) : JsonConverter<string>
{
    // @righteous BlockedAwait
    public override string Read(ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options)
    {
        return store.ReadAsync(reader.GetString()).GetAwaiter().GetResult();
    }

    public override void Write(Utf8JsonWriter writer, string value, JsonSerializerOptions options)
    {
        writer.WriteStringValue(value);
    }
}
