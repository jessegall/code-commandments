namespace Shop.Shipping;

// A scale reading taken at the packing bench.
public record struct Weighing(int Grams)
{
    public void Tare(int container)
    {
        // @sin MutableValueObject
        Grams -= container;
    }
}
