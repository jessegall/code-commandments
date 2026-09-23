using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;

namespace CodeCommandments.Bridge;

/// <summary>
/// What a bridge keeps between runs of one process: each project's references, and each file's parsed
/// tree while the file is unchanged — so a warm bridge answers the next request without loading
/// assemblies or re-parsing what did not change. Every run compiles each project on its own, as MSBuild
/// does: a name one project declares is never visible to a project that does not reference it.
/// </summary>
public sealed class Workspace
{
    private static readonly CSharpParseOptions Options = new(LanguageVersion.Preview);

    private static readonly CSharpCompilationOptions Compiled = new(OutputKind.DynamicallyLinkedLibrary, nullableContextOptions: NullableContextOptions.Enable);

    private readonly Dictionary<string, Reach> reaches = new(StringComparer.Ordinal);

    private readonly Dictionary<string, (DateTime Written, long Length, SyntaxTree Tree)> trees = new(StringComparer.Ordinal);

    public Project Read(IReadOnlyList<string> paths)
    {
        var roots = paths.Select(Path.GetFullPath).ToList();
        var asked = Sources.Under(roots);
        var solution = Solution.Around(roots);
        var owned = solution.Files().Concat(asked).Distinct().GroupBy(file => solution.Owner(file) ?? "").ToDictionary(group => group.Key, group => group.Select(Tree).ToList());
        var compilations = new Dictionary<string, CSharpCompilation>(StringComparer.Ordinal);

        foreach (var csproj in solution.Projects.Keys)
        {
            Compile(csproj, solution, owned, compilations, []);
        }

        var byTree = new Dictionary<SyntaxTree, CSharpCompilation>();

        foreach (var (owner, ownedTrees) in owned)
        {
            var compilation = owner == ""
                ? CSharpCompilation.Create("loose", ownedTrees, References.Loose(), Compiled)
                : compilations[owner];

            foreach (var tree in ownedTrees)
            {
                byTree[tree] = compilation;
            }
        }

        return new Project(asked.Select(Tree).ToList(), byTree);
    }

    /// <summary>
    /// The compilation of <paramref name="csproj"/>, its referenced projects compiled first. What a
    /// referenced project reaches flows on, as MSBuild lets it: the projects it references, its packages,
    /// and its shared frameworks by name — loaded for this project's own target framework. A reference back into a
    /// project still being compiled — a cycle MSBuild would refuse — is left out.
    /// </summary>
    private CSharpCompilation Compile(string csproj, Solution solution, IReadOnlyDictionary<string, List<SyntaxTree>> owned, Dictionary<string, CSharpCompilation> compilations, HashSet<string> compiling)
    {
        if (compilations.TryGetValue(csproj, out var done))
        {
            return done;
        }

        compiling.Add(csproj);

        var reached = Reached(csproj, solution, compiling);
        var projects = reached.Select(other => Compile(other, solution, owned, compilations, compiling).ToMetadataReference());
        var assemblies = References.Load(ReachOf(csproj), reached.Select(ReachOf));
        var sources = owned.GetValueOrDefault(csproj) ?? [];
        var usings = CSharpSyntaxTree.ParseText(GlobalUsings.Of(csproj), Options);

        compiling.Remove(csproj);

        return compilations[csproj] = CSharpCompilation.Create(Path.GetFileNameWithoutExtension(csproj), [..sources, usings], [..assemblies, ..projects], Compiled);
    }

    /// <summary>Every project <paramref name="csproj"/> reaches through its project references, however deep, bar one still being compiled.</summary>
    private static List<string> Reached(string csproj, Solution solution, HashSet<string> compiling)
    {
        var reached = new List<string>();
        var pending = new Stack<string>(solution.Projects[csproj].ProjectReferences());

        while (pending.TryPop(out var other))
        {
            if (!solution.Projects.ContainsKey(other) || compiling.Contains(other) || reached.Contains(other))
            {
                continue;
            }

            reached.Add(other);

            foreach (var further in solution.Projects[other].ProjectReferences())
            {
                pending.Push(further);
            }
        }

        return reached;
    }

    private Reach ReachOf(string csproj)
    {
        if (!reaches.TryGetValue(csproj, out var found))
        {
            reaches[csproj] = found = References.Of(csproj);
        }

        return found;
    }

    private SyntaxTree Tree(string file)
    {
        var info = new FileInfo(file);

        if (trees.TryGetValue(file, out var kept) && kept.Written == info.LastWriteTimeUtc && kept.Length == info.Length)
        {
            return kept.Tree;
        }

        var tree = CSharpSyntaxTree.ParseText(File.ReadAllText(file), Options, path: file);
        trees[file] = (info.LastWriteTimeUtc, info.Length, tree);

        return tree;
    }
}
