using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;

namespace CodeCommandments.Bridge;

/// <summary>
/// The files of one run, parsed, and compiled together with their projects' global usings against the
/// references that resolve. Only the files themselves are written out.
/// </summary>
public sealed class Project(IReadOnlyList<SyntaxTree> trees, CSharpCompilation compilation)
{
    public IReadOnlyList<SyntaxTree> Trees { get; } = trees;

    public CSharpCompilation Compilation { get; } = compilation;
}
