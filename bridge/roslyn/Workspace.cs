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

    /// <summary>Every assembly a streamed run has loaded, by its path, shared by every later run of this workspace.</summary>
    private readonly Dictionary<string, MetadataReference> loaded = new(StringComparer.Ordinal);

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
        var tests = new HashSet<SyntaxTree>();

        foreach (var (owner, ownedTrees) in owned)
        {
            var compilation = owner == ""
                ? CSharpCompilation.Create("loose", ownedTrees, References.Loose(), Compiled)
                : compilations[owner];

            foreach (var tree in ownedTrees)
            {
                byTree[tree] = compilation;

                if (owner != "" && solution.Projects[owner].IsTestProject())
                {
                    tests.Add(tree);
                }
            }
        }

        return new Project(asked.Select(Tree).ToList(), byTree, tests);
    }

    /// <summary>
    /// The run as a stream, one project at a time, holding no source between runs: each project is parsed and compiled
    /// when it is reached, dependencies first, its asked files handed to <paramref name="write"/>, and its compilation
    /// let go once no project still to be written reaches it. An assembly is loaded once for the workspace and shared
    /// by every project and every run that reaches it. Files outside every project come last, compiled alone.
    /// </summary>
    public void Stream(IReadOnlyList<string> paths, Action<Project> write)
    {
        var roots = paths.Select(Path.GetFullPath).ToList();
        var asked = Sources.Under(roots).ToHashSet(StringComparer.Ordinal);
        var solution = Solution.Around(roots);
        var owned = solution.Files().Concat(asked).Distinct().GroupBy(file => solution.Owner(file) ?? "").ToDictionary(group => group.Key, group => group.ToList());
        var order = new List<string>();
        var reach = new Dictionary<string, List<string>>(StringComparer.Ordinal);

        foreach (var csproj in solution.Projects.Keys)
        {
            Order(csproj, solution, reach, order, []);
        }

        var readers = order.SelectMany(csproj => reach[csproj]).GroupBy(other => other).ToDictionary(group => group.Key, group => group.Count());
        var compilations = new Dictionary<string, CSharpCompilation>(StringComparer.Ordinal);

        foreach (var csproj in order)
        {
            var sources = (owned.GetValueOrDefault(csproj) ?? []).Select(Parse).ToList();
            var usings = CSharpSyntaxTree.ParseText(GlobalUsings.Of(csproj), Options);
            var projects = reach[csproj].Where(compilations.ContainsKey).Select(other => compilations[other].ToMetadataReference());
            var assemblies = References.Load(References.Of(csproj), reach[csproj].Select(References.Of), loaded);
            var compilation = CSharpCompilation.Create(Path.GetFileNameWithoutExtension(csproj), [..sources, usings], [..assemblies, ..projects], Compiled);
            compilations[csproj] = compilation;
            WriteAsked(sources, compilation, asked, solution.Projects[csproj].IsTestProject(), write);

            foreach (var other in reach[csproj].Where(readers.ContainsKey))
            {
                if (--readers[other] == 0)
                {
                    compilations.Remove(other);
                }
            }

            if (!readers.ContainsKey(csproj))
            {
                compilations.Remove(csproj);
            }
        }

        if (owned.TryGetValue("", out var loose))
        {
            var sources = loose.Select(Parse).ToList();
            WriteAsked(sources, CSharpCompilation.Create("loose", sources, References.Loose(loaded), Compiled), asked, false, write);
        }
    }

    /// <summary>Hands the asked files among <paramref name="sources"/> to <paramref name="write"/>, as one project read in <paramref name="compilation"/>.</summary>
    private static void WriteAsked(List<SyntaxTree> sources, CSharpCompilation compilation, HashSet<string> asked, bool test, Action<Project> write)
    {
        var trees = sources.Where(tree => asked.Contains(tree.FilePath)).ToList();

        if (trees.Count == 0)
        {
            return;
        }

        write(new Project(trees, trees.ToDictionary(tree => tree, _ => compilation), test ? trees.ToHashSet() : []));
    }

    /// <summary>Puts <paramref name="csproj"/> in <paramref name="order"/> after every project it reaches, and keeps what it reaches in <paramref name="reach"/>.</summary>
    private static void Order(string csproj, Solution solution, Dictionary<string, List<string>> reach, List<string> order, HashSet<string> visiting)
    {
        if (reach.ContainsKey(csproj) || !visiting.Add(csproj))
        {
            return;
        }

        var reached = Reached(csproj, solution, visiting);

        foreach (var other in reached)
        {
            Order(other, solution, reach, order, visiting);
        }

        visiting.Remove(csproj);
        reach[csproj] = reached;
        order.Add(csproj);
    }

    /// <summary><paramref name="file"/> parsed, never kept: a streamed run holds nothing between runs.</summary>
    private static SyntaxTree Parse(string file) => CSharpSyntaxTree.ParseText(File.ReadAllText(file), Options, path: file);

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
