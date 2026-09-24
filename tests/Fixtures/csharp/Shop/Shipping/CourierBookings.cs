namespace Shop.Shipping;

public sealed record Sender(string Name, string Postcode);

public sealed record Booking(string Reference, Sender Sender, int Parcels);

public sealed class CourierDesk
{
    public string Book(Booking booking, string postcode) => $"{booking.Reference}@{postcode}x{booking.Parcels}";
}

public sealed class Dispatcher(CourierDesk desk)
{
    // @sin DerivedArgument
    public string Today(Booking booking) => desk.Book(booking, booking.Sender.Postcode);

    public IEnumerable<string> Batch(IEnumerable<Booking> bookings) =>
        // @sin DerivedArgument
        bookings.Select(booking => desk.Book(booking, booking.Sender.Postcode));
}
