namespace Shop.Shipping;

// A parcel and an envelope check their own measurements the same way when they are made — each
// constructor sets ITS OWN state, so the two are not one decision made twice.
public sealed class Parcel
{
    private readonly int grams;

    private readonly int length;

    // @righteous DuplicateMethod
    public Parcel(int grams, int length)
    {
        if (grams <= 0 || length <= 0)
        {
            throw new ArgumentOutOfRangeException(nameof(grams), "A parcel has a weight and a size.");
        }

        this.grams = grams;
        this.length = length;
    }

    public int Volume => grams * length;
}

public sealed class Envelope
{
    private readonly int grams;

    private readonly int length;

    // @righteous DuplicateMethod
    public Envelope(int grams, int length)
    {
        if (grams <= 0 || length <= 0)
        {
            throw new ArgumentOutOfRangeException(nameof(grams), "A parcel has a weight and a size.");
        }

        this.grams = grams;
        this.length = length;
    }

    public bool FitsLetterbox => grams < 500 && length < 380;
}
