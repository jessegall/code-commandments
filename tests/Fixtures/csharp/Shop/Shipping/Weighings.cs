namespace Shop.Shipping;

// A scale reading taken at the packing bench.
public record struct Weighing(int Grams)
{
    // @sin MutableValueObject
    public void Tare(int container)
    {
        Grams -= container;
    }
}
