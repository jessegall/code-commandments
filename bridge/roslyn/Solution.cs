namespace CodeCommandments.Bridge;

/// <summary>
/// The projects a request reaches, from the support it names: every project file listed, and every project those
/// reference, however deep. Each file belongs to the project whose folder holds it most closely, so a nested
/// project's files are its own. The bridge walks no folder: the Go side lists every project file, source and
/// generated usings file through its one funnel.
/// </summary>
public sealed class Solution
{
    private readonly Dictionary<string, ProjectFile> projects = new(StringComparer.Ordinal);

    private readonly IReadOnlyList<string> support;

    private Solution(IReadOnlyList<string> support)
    {
        this.support = support.Select(Path.GetFullPath).ToList();
        var pending = new Queue<string>(this.support.Where(path => path.EndsWith(".csproj", StringComparison.Ordinal)));

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

    public static Solution Of(IReadOnlyList<string> support) => new(support);

    /// <summary>Every project found, by the full path of its project file.</summary>
    public IReadOnlyDictionary<string, ProjectFile> Projects => projects;

    /// <summary>The project <paramref name="file"/> belongs to — none for a file outside every project.</summary>
    public string? Owner(string file) =>
        projects
            .Where(project => file.StartsWith(project.Value.Folder + Path.DirectorySeparatorChar, StringComparison.Ordinal))
            .OrderByDescending(project => project.Value.Folder.Length)
            .Select(project => project.Key)
            .FirstOrDefault();

    /// <summary>The source files of every project found — what compiles, though only the asked files are written.</summary>
    public IReadOnlyList<string> Files() => Sources.Under(support.Where(path => !path.EndsWith(".GlobalUsings.g.cs", StringComparison.Ordinal)));

    /// <summary>The global usings file the SDK generated for <paramref name="csproj"/>, the newest when there are several.</summary>
    public string? GeneratedUsingsOf(string csproj)
    {
        var name = Path.GetFileNameWithoutExtension(csproj) + ".GlobalUsings.g.cs";

        return support
            .Where(path => Path.GetFileName(path) == name && File.Exists(path))
            .OrderByDescending(File.GetLastWriteTimeUtc)
            .FirstOrDefault();
    }
}
