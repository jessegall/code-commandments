namespace Shop.Pricing;

// Exchange rates are cached on disk for the next run — and if the write fails, for whatever reason, the
// failure is caught and dropped, so nobody learns the cache is never written.
public sealed class RateCache(string path)
{
    public void Store(IReadOnlyDictionary<string, decimal> rates)
    {
        try
        {
            File.WriteAllLines(path, rates.Select(rate => $"{rate.Key};{rate.Value}"));
        }
        // @sin SwallowedException
        catch
        {
        }
    }
}
