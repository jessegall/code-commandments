using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis.CSharp.Syntax;

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

    public Project Read(IReadOnlyList<string> paths, IReadOnlyList<string> support)
    {
        var asked = Sources.Under(paths);
        var solution = Solution.Of(support);
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
    /// when it is reached, dependencies first, its files to write handed to <paramref name="write"/>, and its compilation
    /// let go once no project still to be written reaches it. A project with no file to write is never compiled. An
    /// assembly is loaded once for the workspace and shared by every project and every run that reaches it. Files outside
    /// every project come last, compiled alone. <paramref name="written"/> names the files to write; empty, every one.
    /// </summary>
    public void Stream(IReadOnlyList<string> paths, IReadOnlyList<string> support, IReadOnlySet<string> written, Action<Asked> write)
    {
        var named = Sources.Under(paths);
        var asked = named.Where(file => written.Count == 0 || written.Contains(file)).ToHashSet(StringComparer.Ordinal);
        var solution = Solution.Of(support);
        var owned = new Dictionary<string, List<string>>(StringComparer.Ordinal);
        var loose = new List<string>();

        foreach (var file in solution.Files().Concat(named).Distinct())
        {
            if (solution.Owner(file) is { } owner)
            {
                owned.TryAdd(owner, []);
                owned[owner].Add(file);
            }
            else
            {
                loose.Add(file);
            }
        }

        var order = new List<string>();
        var reach = new Dictionary<string, List<string>>(StringComparer.Ordinal);

        foreach (var csproj in solution.Projects.Keys)
        {
            Order(csproj, solution, reach, order, []);
        }

        var readers = order.SelectMany(csproj => reach[csproj]).GroupBy(other => other).ToDictionary(group => group.Key, group => group.Count());
        var compilations = new Dictionary<string, Lazy<(List<SyntaxTree> Sources, CSharpCompilation Compilation)>>(StringComparer.Ordinal);
        var reads = new Dictionary<string, List<string>>(StringComparer.Ordinal);

        foreach (var csproj in order)
        {
            var files = owned.GetValueOrDefault(csproj) ?? [];
            var usings = GlobalUsings.Of(csproj, solution.GeneratedUsingsOf(csproj));
            var own = References.Of(csproj);
            var reached = reach[csproj].Where(compilations.ContainsKey).ToList();
            reads[csproj] = [..Settings(csproj, solution), ..files, ..reach[csproj].SelectMany(other => reads[other])];
            compilations[csproj] = new(() =>
            {
                var sources = files.Select(Parse).ToList();
                var projects = reached.Select(other => compilations[other].Value.Compilation.ToMetadataReference());
                var assemblies = References.Load(own, reach[csproj].Select(References.Of), loaded);

                return (sources, CSharpCompilation.Create(Path.GetFileNameWithoutExtension(csproj), [..sources, CSharpSyntaxTree.ParseText(usings, Options)], [..assemblies, ..projects], Compiled));
            });
            var compiled = compilations[csproj];
            WriteAsked(reads[csproj], files, () => compiled.Value, asked, solution.Projects[csproj].IsTestProject(), write);

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

        if (loose.Count > 0)
        {
            var compiled = new Lazy<(List<SyntaxTree>, CSharpCompilation)>(() =>
            {
                var sources = loose.Select(Parse).ToList();

                return (sources, CSharpCompilation.Create("loose", sources, References.Loose(loaded), Compiled));
            });
            WriteAsked(loose, loose, () => compiled.Value, asked, false, write);
        }
    }

    /// <summary>Hands the asked files among <paramref name="files"/> to <paramref name="write"/> with what their lines read, the project compiled only when the writer asks for it.</summary>
    private static void WriteAsked(List<string> reads, List<string> files, Func<(List<SyntaxTree> Sources, CSharpCompilation Compilation)> compiled, HashSet<string> asked, bool test, Action<Asked> write)
    {
        var wanted = files.Where(asked.Contains).ToList();

        if (wanted.Count == 0)
        {
            return;
        }

        write(new Asked(reads.Distinct().ToList(), wanted, () =>
        {
            var (sources, compilation) = compiled();
            var trees = sources.Where(tree => asked.Contains(tree.FilePath)).ToList();

            return new Project(trees, trees.ToDictionary(tree => tree, _ => compilation), test ? trees.ToHashSet() : []);
        }));
    }

    /// <summary>The files besides its sources that shape <paramref name="csproj"/>'s compilation: the project file, what NuGet restored for it and its generated global usings.</summary>
    private static IEnumerable<string> Settings(string csproj, Solution solution)
    {
        yield return csproj;
        yield return Path.Combine(solution.Projects[csproj].Intermediate(), "project.assets.json");

        if (solution.GeneratedUsingsOf(csproj) is { } usings)
        {
            yield return usings;
        }
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
        var usings = CSharpSyntaxTree.ParseText(GlobalUsings.Of(csproj, solution.GeneratedUsingsOf(csproj)), Options);

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
