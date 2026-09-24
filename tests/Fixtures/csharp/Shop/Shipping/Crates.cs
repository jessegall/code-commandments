namespace Shop.Shipping;

// What goods travel in, and the lids a crate can be closed with.
public abstract class Package
{
    // @fixed RepeatedTypeGuard
    public bool IsScrewedCrate => this is Crate crate && crate.Lid is ScrewLid;
}

public sealed class Crate : Package
{
    public Lid? Lid { get; init; }
}

public abstract class Lid;

public sealed class ScrewLid : Lid;

public sealed class ClipLid : Lid;

public static class CrateHandling
{
    public static string Instructions(Package package)
    {
        // @sin RepeatedTypeGuard
        if (package is Crate crate && crate.Lid is ScrewLid)
        {
            return "use the drill";
        }

        return "lift by hand";
    }

    // @fixed RepeatedTypeGuard
    public static string Handling(Package package) => package.IsScrewedCrate ? "use the drill" : "lift by hand";

    // @righteous RepeatedTypeGuard
    public static bool IsCrate(Package package) => package is Crate;
}
