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
    /// when it is reached, dependencies first, its asked files handed to <paramref name="write"/>, and its compilation
    /// let go once no project still to be written reaches it. An assembly is loaded once for the workspace and shared
    /// by every project and every run that reaches it. Files outside every project come last, compiled alone.
    /// </summary>
    public void Stream(IReadOnlyList<string> paths, IReadOnlyList<string> support, Action<Asked> write)
    {
        var asked = Sources.Under(paths).ToHashSet(StringComparer.Ordinal);
        var solution = Solution.Of(support);
        var owned = new Dictionary<string, List<string>>(StringComparer.Ordinal);
        var loose = new List<string>();

        foreach (var file in solution.Files().Concat(asked).Distinct())
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
        var keys = new Dictionary<string, string>(StringComparer.Ordinal);

        foreach (var csproj in order)
        {
            var files = owned.GetValueOrDefault(csproj) ?? [];
            var usings = GlobalUsings.Of(csproj, solution.GeneratedUsingsOf(csproj));
            var own = References.Of(csproj);
            var reached = reach[csproj].Where(compilations.ContainsKey).ToList();
            keys[csproj] = KeyOf(csproj, files, usings, own, reach[csproj].Select(other => keys.GetValueOrDefault(other) ?? ""));
            compilations[csproj] = new(() =>
            {
                var sources = files.Select(Parse).ToList();
                var projects = reached.Select(other => compilations[other].Value.Compilation.ToMetadataReference());
                var assemblies = References.Load(own, reach[csproj].Select(References.Of), loaded);

                return (sources, CSharpCompilation.Create(Path.GetFileNameWithoutExtension(csproj), [..sources, CSharpSyntaxTree.ParseText(usings, Options)], [..assemblies, ..projects], Compiled));
            });
            var compiled = compilations[csproj];
            WriteAsked(keys[csproj], files, () => compiled.Value, asked, solution.Projects[csproj].IsTestProject(), write);

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
            WriteAsked(KeyOf("", loose, "", null, []), loose, () => compiled.Value, asked, false, write);
        }
    }

    /// <summary>Hands the asked files among <paramref name="files"/> to <paramref name="write"/> under the project's key, the project compiled only when the writer asks for it.</summary>
    private static void WriteAsked(string key, List<string> files, Func<(List<SyntaxTree> Sources, CSharpCompilation Compilation)> compiled, HashSet<string> asked, bool test, Action<Asked> write)
    {
        var wanted = files.Where(asked.Contains).ToList();

        if (wanted.Count == 0)
        {
            return;
        }

        write(new Asked(key, wanted, () =>
        {
            var (sources, compilation) = compiled();
            var trees = sources.Where(tree => asked.Contains(tree.FilePath)).ToList();

            return new Project(trees, trees.ToDictionary(tree => tree, _ => compilation), test ? trees.ToHashSet() : []);
        }));
    }

    /// <summary>
    /// The key of a project as one file's semantic facts see it, made of contents alone and never a path: its project
    /// file, its global usings, what it references, the keys of the projects it reaches, and every source's
    /// declarations with the bodies of its members cut out. A change inside a body keeps every other file's line; a
    /// changed declaration starts them over.
    /// </summary>
    private static string KeyOf(string csproj, List<string> files, string usings, Reach? own, IEnumerable<string> reached)
    {
        var parts = new List<string> { File.Exists(csproj) ? File.ReadAllText(csproj) : "", usings };

        if (own is not null)
        {
            parts.AddRange([own.Tfm, ..own.Frameworks, ..own.Packages]);
        }

        parts.AddRange(reached);
        parts.AddRange(files.Select(Declarations).Order(StringComparer.Ordinal));

        return TreeCache.KeyOf(parts);
    }

    /// <summary>What <paramref name="file"/> declares, as its text with every member body cut out: all one file's types can see of another.</summary>
    private static string Declarations(string file)
    {
        var text = File.ReadAllText(file);
        var bodies = CSharpSyntaxTree.ParseText(text, Options).GetRoot().DescendantNodes()
            .Select(node => node switch
            {
                BaseMethodDeclarationSyntax method => (SyntaxNode?)method.Body ?? method.ExpressionBody,
                AccessorDeclarationSyntax accessor => (SyntaxNode?)accessor.Body ?? accessor.ExpressionBody,
                _ => null,
            })
            .OfType<SyntaxNode>()
            .Select(body => body.Span)
            .OrderBy(span => span.Start)
            .ToList();

        var kept = new System.Text.StringBuilder();
        var from = 0;

        foreach (var span in bodies.Where(span => span.Start >= from))
        {
            kept.Append(text, from, span.Start - from);
            from = span.End;
        }

        return TreeCache.KeyOf(kept.Append(text, from, text.Length - from).ToString());
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
