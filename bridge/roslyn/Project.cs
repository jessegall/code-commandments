using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;

namespace CodeCommandments.Bridge;

/// <summary>
/// The files of one run, parsed, and compiled together with their projects' global usings against the
/// references that resolve. Only the files themselves are written out.
/// </summary>
public sealed class Project
{
    private Project(IReadOnlyList<SyntaxTree> trees, CSharpCompilation compilation)
    {
        Trees = trees;
        Compilation = compilation;
    }

    public IReadOnlyList<SyntaxTree> Trees { get; }

    public CSharpCompilation Compilation { get; }

    public static Project Read(IReadOnlyList<string> files, IReadOnlyList<string> roots)
    {
        var options = new CSharpParseOptions(LanguageVersion.Preview);
        var trees = files.Select(file => CSharpSyntaxTree.ParseText(File.ReadAllText(file), options, path: file)).ToList();
        var usings = GlobalUsings.Under(roots).Select(source => CSharpSyntaxTree.ParseText(source, options));
        var compilation = CSharpCompilation.Create(
            "judged",
            [..trees, ..usings],
            References.For(roots),
            new CSharpCompilationOptions(OutputKind.DynamicallyLinkedLibrary, nullableContextOptions: NullableContextOptions.Enable));

        return new Project(trees, compilation);
    }
}
