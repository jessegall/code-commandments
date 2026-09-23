using System.Text.Json;

namespace Shop.Shipping;

public sealed record CarrierEvent(string Parcel, string Status);

// A carrier's webhook is read straight off the JSON, field by field — and through a helper that is
// handed each field's name — so the event's shape lives only in the strings.
public static class Webhooks
{
    public static string Parcel(JsonElement payload)
    {
        // @sin DictionaryBag
        return payload.GetProperty("parcel").GetString() ?? throw new JsonException("A carrier event names its parcel.");
    }

    public static string Status(IReadOnlyDictionary<string, string> fields)
    {
        // @sin DictionaryBag
        return Field(fields, "status");
    }

    // @righteous DictionaryBag
    public static CarrierEvent Parse(IReadOnlyDictionary<string, string> fields) => new(Field(fields, "parcel"), Field(fields, "status"));

    private static string Field(IReadOnlyDictionary<string, string> fields, string name) => fields[name].Trim();
}
