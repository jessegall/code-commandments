namespace Shop.Shipping;

// A courier pickup booked for the warehouse.
public sealed record Pickup
{
    public required string Courier { get; init; }

    public required string Confirmation { get; init; }

    public string? Note { get; init; }

    // @righteous PlaceholderFilledData
    public static Pickup None { get; } = new() { Courier = "", Confirmation = "" };
}

public static class Pickups
{
    public static Pickup Booked(string courier)
    {
        // @sin PlaceholderFilledData
        return new Pickup { Courier = courier, Confirmation = string.Empty, Note = "" };
    }
}
