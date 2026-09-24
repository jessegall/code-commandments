namespace Shop.Reports;

public static class ReportKind
{
    public const string Daily = "daily";
    public const string Monthly = "monthly";
}

public sealed class ReportRunner
{
    public int Run(string kind) => kind.Length;
}

public sealed class ReportSchedule(ReportRunner runner)
{
    public int Morning() => runner.Run(ReportKind.Daily);

    // @sin UnnamedVocabularyLiteral
    public int EndOfMonth() => runner.Run("monthly");

    // @fixed UnnamedVocabularyLiteral
    public int Close() => runner.Run(ReportKind.Monthly);
}
