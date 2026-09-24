namespace Shop.Reports;

// A scheduled export of the sales report to a file.
public sealed record ExportJob(string Path)
{
    public string Describe() => $"{Format} to {Path}";

    // @sin MemberAfterMethod
    public string Format { get; init; } = "csv";
}
