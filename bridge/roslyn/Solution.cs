namespace CodeCommandments.Bridge;

/// <summary>
/// The projects a request reaches, found as MSBuild would: every project under the roots, the project a
/// root sits inside, and every project those reference, however deep. Each file belongs to the project
/// whose folder holds it most closely — a nested project's files are its own.
/// </summary>
public sealed class Solution
{
    private readonly Dictionary<string, ProjectFile> projects = new(StringComparer.Ordinal);

    private Solution(IReadOnlyList<string> roots)
    {
        var pending = new Queue<string>(roots.SelectMany(Found).Distinct());

        while (pending.TryDequeue(out var csproj))
        {
            if (projects.ContainsKey(csproj) || !File.Exists(csproj))
            {
                continue;
            }

            projects[csproj] = ProjectFile.Read(csproj);

            foreach (var referenced in projects[csproj].ProjectReferences())
            {
                pending.Enqueue(referenced);
            }
        }
    }

    public static Solution Around(IReadOnlyList<string> roots) => new(roots);

    /// <summary>Every project found, by the full path of its project file.</summary>
    public IReadOnlyDictionary<string, ProjectFile> Projects => projects;

    /// <summary>The project <paramref name="file"/> belongs to — none for a file outside every project.</summary>
    public string? Owner(string file) =>
        projects.Keys
            .Where(csproj => file.StartsWith(Path.GetDirectoryName(csproj)! + Path.DirectorySeparatorChar, StringComparison.Ordinal))
            .MaxBy(csproj => Path.GetDirectoryName(csproj)!.Length);

    /// <summary>The source files of every project found — what compiles, though only the asked files are written.</summary>
    public IReadOnlyList<string> Files() => Sources.Under(projects.Keys.Select(csproj => Path.GetDirectoryName(csproj)!));

    /// <summary>The projects under <paramref name="root"/>, or the one it sits inside.</summary>
    private static IEnumerable<string> Found(string root)
    {
        var below = Directory.Exists(root)
            ? Directory.EnumerateFiles(root, "*.csproj", SearchOption.AllDirectories).Where(Sources.IsSource).ToList()
            : [];

        return below.Count > 0 ? below : Enclosing(root);
    }

    /// <summary>The project file in the nearest folder above <paramref name="path"/> that holds one.</summary>
    private static IEnumerable<string> Enclosing(string path)
    {
        for (var directory = Directory.Exists(path) ? path : Path.GetDirectoryName(path); directory is not null; directory = Path.GetDirectoryName(directory))
        {
            var csproj = Directory.EnumerateFiles(directory, "*.csproj").FirstOrDefault();

            if (csproj is not null)
            {
                return [csproj];
            }
        }

        return [];
    }
}
