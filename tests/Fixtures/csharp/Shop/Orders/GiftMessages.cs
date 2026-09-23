namespace Shop.Orders;

// A gift order carries a message card; the packer asks whether there is one to print.
public sealed class GiftMessage
{
    public string? Text { get; init; }

    // @sin CancelledCoalesce
    public bool IsBlank() => string.Empty == (Text ?? string.Empty);
}
