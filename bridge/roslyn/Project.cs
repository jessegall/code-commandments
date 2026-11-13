using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;

namespace CodeCommandments.Bridge;

/// <summary>
/// The files of one run, parsed, each compiled in its own project's compilation — its references, its
/// global usings, the projects it references. Only the files asked for are written out.
/// </summary>
public sealed class Project(IReadOnlyList<SyntaxTree> trees, IReadOnlyDictionary<SyntaxTree, CSharpCompilation> compilations, IReadOnlySet<SyntaxTree> tests)
{
    public IReadOnlyList<SyntaxTree> Trees { get; } = trees;

    /// <summary>What the compiler knows about <paramref name="tree"/>, read in the compilation it belongs to.</summary>
    public SemanticModel Model(SyntaxTree tree) => compilations[tree].GetSemanticModel(tree);

    /// <summary>Does <paramref name="tree"/> belong to a test project?</summary>
    public bool IsTest(SyntaxTree tree) => tests.Contains(tree);

    /// <summary>Every compilation's diagnostics, each reported once.</summary>
    public IEnumerable<Diagnostic> Diagnostics() => compilations.Values.Distinct().SelectMany(compilation => compilation.GetDiagnostics());
}

/// <summary>
/// The files of one project to write, every other file whose contents shape their lines (the project's files and
/// settings, and those of every project it reaches), and the project read only when a writer asks for it.
/// </summary>
public sealed record Asked(IReadOnlyList<string> Reads, IReadOnlyList<string> Files, Func<Project> Read);
