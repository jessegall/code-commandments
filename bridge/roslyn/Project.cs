using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;

namespace CodeCommandments.Bridge;

/// <summary>The files of one run, parsed, and compiled together against the references that resolve.</summary>
public sealed class Project
{
    private Project(IReadOnlyList<SyntaxTree> trees, CSharpCompilation compilation)
    {
        Trees = trees;
        Compilation = compilation;
    }

    public IReadOnlyList<SyntaxTree> Trees { get; }

    public CSharpCompilation Compilation { get; }

    public static Project Read(IReadOnlyList<string> files)
    {
        var options = new CSharpParseOptions(LanguageVersion.Preview);
        var trees = files.Select(file => CSharpSyntaxTree.ParseText(File.ReadAllText(file), options, path: file)).ToList();
        var compilation = CSharpCompilation.Create(
            "judged",
            trees,
            References.Runtime(),
            new CSharpCompilationOptions(OutputKind.DynamicallyLinkedLibrary, nullableContextOptions: NullableContextOptions.Enable));

        return new Project(trees, compilation);
    }
}
