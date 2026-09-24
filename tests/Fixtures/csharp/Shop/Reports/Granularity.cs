namespace Shop.Reports;

// How finely a sales chart buckets its numbers.
public enum Granularity
{
    Hourly,
    Daily,
    Monthly,
}

public static class BucketFormats
{
    public static string? FormatFor(Granularity granularity)
    {
        // @sin MatchDefaultReturnsNull
        switch (granularity)
        {
            case Granularity.Hourly:
                return "HH:00";
            case Granularity.Daily:
                return "dd MMM";
            case Granularity.Monthly:
                return "MMM yyyy";
            default:
                return default;
        }
    }
}
