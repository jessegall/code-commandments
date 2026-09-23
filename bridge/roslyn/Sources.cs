namespace CodeCommandments.Bridge;

/// <summary>The C# files a run reads: every .cs under the given roots, build output left out.</summary>
public static class Sources
{
    private static readonly string[] Skipped = ["bin", "obj", ".git", "node_modules"];

    public static IReadOnlyList<string> Under(IEnumerable<string> paths)
    {
        var files = new SortedSet<string>(StringComparer.Ordinal);

        foreach (var path in paths.Select(Path.GetFullPath))
        {
            if (File.Exists(path) && path.EndsWith(".cs", StringComparison.Ordinal))
            {
                files.Add(path);
            }
            else if (Directory.Exists(path))
            {
                foreach (var file in Directory.EnumerateFiles(path, "*.cs", SearchOption.AllDirectories).Where(IsSource))
                {
                    files.Add(file);
                }
            }
        }

        return files.ToList();
    }

    public static bool IsSource(string file) =>
        !file.Split(Path.DirectorySeparatorChar).Any(part => Skipped.Contains(part));
}
