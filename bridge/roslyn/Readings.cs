using System.Text;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis.CSharp.Syntax;

namespace CodeCommandments.Bridge;

/// <summary>
/// What the tree writer reads of a project beyond one node: how a type or member is named, whether a file's names
/// are blind to a reference the compilation lacks, and the readings of a comment, a constant or a declaration.
/// </summary>
public sealed class Readings(Project project)
{
    /// <summary>How every type and member is written: fully qualified, `System.String` never `string`, `?` kept.</summary>
    internal static readonly SymbolDisplayFormat Qualified = SymbolDisplayFormat.FullyQualifiedFormat
        .RemoveMiscellaneousOptions(SymbolDisplayMiscellaneousOptions.UseSpecialTypes)
        .AddMiscellaneousOptions(SymbolDisplayMiscellaneousOptions.IncludeNullableReferenceTypeModifier);

    /// <summary>How a declared member is named: fully qualified, with its containing type and its parameters' types — as a call's resolved target names it.</summary>
    internal static readonly SymbolDisplayFormat Declared = Qualified
        .WithMemberOptions(SymbolDisplayMemberOptions.IncludeContainingType | SymbolDisplayMemberOptions.IncludeParameters)
        .WithParameterOptions(SymbolDisplayParameterOptions.IncludeType);

    private bool? blindGlobally;

    private readonly Dictionary<SyntaxTree, bool> blindFiles = [];

    /// <summary>
    /// Are the names in scope of <paramref name="model"/>'s file blind — the compiler finding a type or namespace
    /// missing there (CS0246, CS0234), or a global `using` anywhere in the project resolving to nothing — so a
    /// name may live in a reference the compilation lacks?
    /// </summary>
    internal bool IsBlind(SemanticModel model)
    {
        blindGlobally ??= project.Trees.Any(tree => UnresolvedUsings(tree.GetRoot(), project.Model(tree)).Any(directive => directive.GlobalKeyword.IsKind(SyntaxKind.GlobalKeyword)));

        if (!blindFiles.TryGetValue(model.SyntaxTree, out var blind))
        {
            blind = model.GetDiagnostics().Any(diagnostic => diagnostic.Id is "CS0246" or "CS0234");
            blindFiles[model.SyntaxTree] = blind;
        }

        return blindGlobally.Value || blind;
    }

    /// <summary>The longest qualifier of <paramref name="cref"/> that resolves — <c>Shop.Orders</c> of <c>Shop.Orders.Gone</c>.</summary>
    internal static ISymbol? Owner(CrefSyntax cref, SemanticModel model)
    {
        var container = cref switch
        {
            QualifiedCrefSyntax qualified => qualified.Container,
            TypeCrefSyntax { Type: QualifiedNameSyntax name } => name.Left,
            NameMemberCrefSyntax { Name: QualifiedNameSyntax name } => name.Left,
            _ => null,
        };

        while (container is not null)
        {
            var info = model.GetSymbolInfo(container);

            if ((info.Symbol ?? info.CandidateSymbols.FirstOrDefault()) is { } found)
            {
                return found;
            }

            container = (container as QualifiedNameSyntax)?.Left;
        }

        return null;
    }

    /// <summary>The `using` directives written in <paramref name="root"/> whose namespace or type resolves to nothing.</summary>
    internal static IEnumerable<UsingDirectiveSyntax> UnresolvedUsings(SyntaxNode root, SemanticModel model) => root
        .DescendantNodes(node => node is CompilationUnitSyntax or BaseNamespaceDeclarationSyntax)
        .OfType<UsingDirectiveSyntax>()
        .Where(directive => directive.NamespaceOrType is { } target && model.GetSymbolInfo(target).Symbol is null);

    internal static bool IsComment(SyntaxTrivia trivia) => trivia.Kind() is SyntaxKind.SingleLineCommentTrivia
        or SyntaxKind.MultiLineCommentTrivia
        or SyntaxKind.SingleLineDocumentationCommentTrivia
        or SyntaxKind.MultiLineDocumentationCommentTrivia;

    internal static string CommentKind(SyntaxTrivia trivia) => trivia.Kind() switch
    {
        SyntaxKind.SingleLineCommentTrivia => "line",
        SyntaxKind.MultiLineCommentTrivia => "block",
        _ => "doc",
    };

    /// <summary>
    /// Where each character position of <paramref name="text"/> falls in the file's bytes — Roslyn counts
    /// UTF-16 units from after the byte-order mark, a PHP reader counts bytes from the file's first, and
    /// the two part at the mark and at the first character outside ASCII.
    /// </summary>
    internal static int[] ByteOffsets(string text, int mark)
    {
        var offsets = new int[text.Length + 1];
        var total = mark;

        for (var i = 0; i < text.Length; i++)
        {
            offsets[i] = total;
            total += Utf8Width(text[i]);
        }

        offsets[text.Length] = total;

        return offsets;
    }

    /// <summary>How many UTF-8 bytes <paramref name="character"/> takes: a surrogate pair's four all on its high half.</summary>
    private static int Utf8Width(char character) => character switch
    {
        _ when char.IsHighSurrogate(character) => 4,
        _ when char.IsLowSurrogate(character) => 0,
        < (char)0x80 => 1,
        < (char)0x800 => 2,
        _ => 3,
    };

    /// <summary>The length of the UTF-8 byte-order mark <paramref name="path"/> opens with, which the parsed text drops; none for a tree parsed from text, such as a comment's words.</summary>
    internal static int MarkLength(string path)
    {
        if (path.Length == 0)
        {
            return 0;
        }

        using var file = File.OpenRead(path);
        Span<byte> head = stackalloc byte[3];

        return file.Read(head) == 3 && head.SequenceEqual(Encoding.UTF8.Preamble) ? 3 : 0;
    }

    /// <summary>Every declaration the member overrides, innermost first, then every interface member it implements.</summary>
    internal static IEnumerable<ISymbol> Filled(ISymbol member)
    {
        for (var overridden = Overrides(member); overridden is not null; overridden = Overrides(overridden))
        {
            yield return overridden;
        }

        if (member.ContainingType is not { } type)
        {
            yield break;
        }

        foreach (var candidate in type.AllInterfaces.SelectMany(@interface => @interface.GetMembers()))
        {
            if (SymbolEqualityComparer.Default.Equals(type.FindImplementationForInterfaceMember(candidate), member))
            {
                yield return candidate;
            }
        }
    }

    private static ISymbol? Overrides(ISymbol member) => member switch
    {
        IMethodSymbol method => method.OverriddenMethod,
        IPropertySymbol property => property.OverriddenProperty,
        IEventSymbol @event => @event.OverriddenEvent,
        _ => null,
    };
}
