namespace CodeCommandments.Bridge;

/// <summary>
/// The C# files a run reads: the .cs files it names, each once. The bridge walks no folder: the Go side lists every
/// file through its one funnel, so a folder is never handed here.
/// </summary>
public static class Sources
{
    public static IReadOnlyList<string> Under(IEnumerable<string> paths) =>
        new SortedSet<string>(paths.Select(Path.GetFullPath).Where(IsSource), StringComparer.Ordinal).ToList();

    public static bool IsSource(string path) => path.EndsWith(".cs", StringComparison.Ordinal) && File.Exists(path);
}
