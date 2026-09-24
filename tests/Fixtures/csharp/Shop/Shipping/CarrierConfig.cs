namespace Shop.Shipping;

// The settings file the label printer reads for each courier.
public static class CarrierConfig
{
    // @sin AssembledTemplate
    public static string For(string courier, int dpi) => string.Join("\n", new[] { $"[{courier}]", $"dpi = {dpi}", "format = zpl", "rotate = false" });
}
