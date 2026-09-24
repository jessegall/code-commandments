namespace Shop.Shipping;

/// <summary>A courier the shop can book.</summary>
/// <remarks>
/// Each courier has its own label format, its own cut-off time for same-day collection, and its own way of
/// reporting a lost parcel, which the support desk has to chase by hand.
/// </remarks>
// @sin BloatedDocblock
public sealed record BookableCourier(string Name, TimeOnly CutOff);

/// <summary>A courier's same-day cut-off, in the warehouse's time zone.</summary>
/// <param name="At">The last moment a parcel can be handed over.</param>
// @righteous BloatedDocblock
public sealed record CutOff(TimeOnly At);
