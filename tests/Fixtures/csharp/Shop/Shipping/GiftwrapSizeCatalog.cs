using System;

namespace Shop.Shipping;

public abstract class GiftwrapSize
{
    public abstract string Label { get; }
}

public sealed class SampleGiftwrapSize : GiftwrapSize
{
    public override string Label => "Sample";
}

public sealed class SmallGiftwrapSize : GiftwrapSize
{
    public override string Label => "Small";
}

public sealed class RegularGiftwrapSize : GiftwrapSize
{
    public override string Label => "Regular";
}

public sealed class LargeGiftwrapSize : GiftwrapSize
{
    public override string Label => "Large";
}

public sealed class DisplayGiftwrapSize : GiftwrapSize
{
    public override string Label => "Display";
}

public static class GiftwrapSizeCatalog
{
    public static Type[] SizesFor(bool displaying)
    {
        if (!displaying) return [typeof(SampleGiftwrapSize)];
        // @sin ListMirrorsFolder
        return new[] {
            typeof(SampleGiftwrapSize),
            typeof(SmallGiftwrapSize),
            typeof(RegularGiftwrapSize),
            typeof(LargeGiftwrapSize),
            typeof(DisplayGiftwrapSize),
        };
    }

    public static int InstallByVolume(IEnumerable<GiftwrapSize> sizes, IDictionary<string, GiftwrapSize> byLabel)
    {
        foreach (var size in sizes)
        {
            byLabel.Add(size.Label, size);
        }
        return byLabel.Count;
    }
}
