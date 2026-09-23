using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;

namespace CodeCommandments.Bridge;

/// <summary>
/// What a bridge keeps between runs of one process: the references found for a set of roots, and each
/// file's parsed tree while the file is unchanged — so a warm bridge answers the next request without
/// loading assemblies or re-parsing what did not change.
/// </summary>
public sealed class Workspace
{
    private static readonly CSharpParseOptions Options = new(LanguageVersion.Preview);

    private readonly Dictionary<string, IReadOnlyList<MetadataReference>> references = new();

    private readonly Dictionary<string, (DateTime Written, long Length, SyntaxTree Tree)> trees = new();

    public Project Read(IReadOnlyList<string> paths)
    {
        var roots = paths.Select(Path.GetFullPath).ToList();
        var files = Sources.Under(roots);
        var parsed = files.Select(Tree).ToList();
        var usings = GlobalUsings.Under(roots).Select(source => CSharpSyntaxTree.ParseText(source, Options));
        var key = string.Join("\n", roots.Order(StringComparer.Ordinal));

        if (!references.TryGetValue(key, out var found))
        {
            references[key] = found = References.For(roots);
        }

        var compilation = CSharpCompilation.Create(
            "judged",
            [..parsed, ..usings],
            found,
            new CSharpCompilationOptions(OutputKind.DynamicallyLinkedLibrary, nullableContextOptions: NullableContextOptions.Enable));

        return new Project(parsed, compilation);
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
