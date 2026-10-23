namespace Shop.Shipping;

// What a collection point will take in and what it asks of a customer. Every boolean here is a verb with its
// object, which is the affirmative phrase C# asks a boolean member for, and none of them is a narration of an
// action the point performs.
public sealed record CollectionPoint
{
    // @righteous BareStatePredicate
    public required bool RequiresCollectionCode { get; init; }

    // @righteous BareStatePredicate
    public required bool ReceivesOversizedParcels { get; init; }

    // @righteous BareStatePredicate
    public required bool ShipsToNeighbours { get; init; }

    public required int FreeSlots { get; init; }

    public bool IsFull => FreeSlots == 0;
}
