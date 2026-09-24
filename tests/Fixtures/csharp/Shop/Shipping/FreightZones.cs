namespace Shop.Shipping;

public sealed class FreightZones
{
    private readonly Dictionary<string, int> zoneByCountry = new() { ["NL"] = 1, ["DE"] = 2 };

    /// <summary>The zone a country ships in, replacing <see cref="Shop.Shipping.LegacyZoneTable"/>.</summary>
    // @sin DanglingDocReference
    public int ZoneOf(string country) => zoneByCountry.TryGetValue(country, out var zone) ? zone : throw new KeyNotFoundException(country);

    /// <summary>Whether a country ships at all; see <see cref="ZoneOf"/> for its zone.</summary>
    // @righteous DanglingDocReference
    public bool Ships(string country) => zoneByCountry.ContainsKey(country);
}
