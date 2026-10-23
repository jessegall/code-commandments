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

    /// <summary>
    /// Does this `//` or `/* */` comment hold C# rather than prose — one statement from end to end, with the
    /// punctuation code has (<c>total += rate</c>, <c>return order.Total()</c>)? Words alone parse as a
    /// declaration (<c>flush buffers</c>), and read as prose.
    /// </summary>
    internal static bool IsCode(SyntaxTrivia trivia)
    {
        var text = trivia.ToFullString();
        var body = trivia.Kind() switch
        {
            SyntaxKind.MultiLineCommentTrivia => text[2..^2].Trim(),
            SyntaxKind.SingleLineCommentTrivia => text[2..].Trim(),
            _ => "",
        };

        if (body.Length == 0)
        {
            return false;
        }

        var written = body.EndsWith(';') || body.EndsWith('}') ? body : body + ";";
        var statement = SyntaxFactory.ParseStatement(written);

        return !statement.ContainsDiagnostics
            && statement.FullSpan.End >= written.Length
            && statement.DescendantTokens().Any(token => token.IsKind(SyntaxKind.DotToken) || token.IsKind(SyntaxKind.OpenParenToken) || token.IsKind(SyntaxKind.OpenBracketToken) || SyntaxFacts.IsAssignmentExpressionOperatorToken(token.Kind()) || SyntaxFacts.IsBinaryExpressionOperatorToken(token.Kind()));
    }

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

    /// <summary>The length of the UTF-8 byte-order mark <paramref name="path"/> opens with, which the parsed text drops.</summary>
    internal static int MarkLength(string path)
    {
        using var file = File.OpenRead(path);
        Span<byte> head = stackalloc byte[3];

        return file.Read(head) == 3 && head.SequenceEqual(Encoding.UTF8.Preamble) ? 3 : 0;
    }

    /// <summary>
    /// What part the node plays — a statement, an expression, a member or type declaration, a type, or
    /// anything else (a parameter, an argument, a clause) — as Roslyn's own class hierarchy says it.
    /// </summary>
    internal static string Role(SyntaxNode node) => node switch
    {
        StatementSyntax => "statement",
        MemberDeclarationSyntax => "member",
        TypeSyntax type when SyntaxFacts.IsInTypeOnlyContext(type) => "type",
        ExpressionSyntax => "expression",
        _ => "other",
    };

    /// <summary>
    /// Has <paramref name="expression"/> a value the compiler fixes — folded; a name bound to an enum
    /// member or a <c>const</c>, as the right side of <c>x is Status.Paid</c> is, though it stands where
    /// a type could; or <c>typeof</c> a concrete type. <c>typeof(T)</c> over a type parameter varies.
    /// </summary>
    internal static bool IsConstant(ExpressionSyntax expression, SemanticModel model) =>
        model.GetConstantValue(expression).HasValue
        || model.GetSymbolInfo(expression).Symbol is IFieldSymbol { HasConstantValue: true }
        || (expression is TypeOfExpressionSyntax typeOf && model.GetTypeInfo(typeOf.Type).Type is not (null or ITypeParameterSymbol or IErrorTypeSymbol));

    /// <summary>
    /// Does what <paramref name="operand"/> names — a field, property, local, parameter, or a method's
    /// return — declare a type that admits null? What a <c>!</c> on it silences, read from the
    /// declaration, since the operand of a <c>!</c> reports the state after it.
    /// </summary>
    internal static bool IsDeclaredNullable(ExpressionSyntax operand, SemanticModel model)
    {
        var declared = model.GetSymbolInfo(operand).Symbol switch
        {
            IFieldSymbol field => field.Type,
            IPropertySymbol property => property.Type,
            ILocalSymbol local => local.Type,
            IParameterSymbol parameter => parameter.Type,
            IMethodSymbol method => method.ReturnType,
            _ => null,
        };

        return declared is { NullableAnnotation: NullableAnnotation.Annotated } || declared is INamedTypeSymbol { OriginalDefinition.SpecialType: SpecialType.System_Nullable_T };
    }

    internal static bool ImplementsInterfaceMember(ISymbol member) => Fills(member).Any();

    /// <summary>Whether every declaration the member fills was written outside this solution.</summary>
    /// <remarks>
    /// A member that fills a declaration cannot change its own signature, and whether that matters depends on who
    /// wrote the declaration. A solution can change an interface of its own, wherever in the solution it lives,
    /// and cannot change one a framework or a package wrote. Only the compiler knows which, since a type of
    /// another project in the solution compiles from source while a package's type arrives as metadata, and the
    /// scan a reader sees may hold neither.
    /// </remarks>
    internal static bool FillsOutsideTheSolution(ISymbol member)
    {
        var filled = Fills(member).ToList();

        return filled.Count > 0 && !filled.Any(declaring => declaring.Locations.Any(location => location.IsInSource));
    }

    /// <summary>The types declaring what the member implements or overrides.</summary>
    private static IEnumerable<INamedTypeSymbol> Fills(ISymbol member) => Overridden(member).Concat(Implemented(member));

    /// <summary>The types of the base members the member overrides, innermost first.</summary>
    private static IEnumerable<INamedTypeSymbol> Overridden(ISymbol member)
    {
        var overridden = Overrides(member);

        while (overridden is not null)
        {
            if (overridden.ContainingType is { } declaring)
            {
                yield return declaring;
            }

            overridden = Overrides(overridden);
        }
    }

    /// <summary>The interfaces that declare a member this member implements.</summary>
    private static IEnumerable<INamedTypeSymbol> Implemented(ISymbol member) =>
        member.ContainingType is not { } type
            ? []
            : type.AllInterfaces.Where(@interface => @interface.GetMembers().Any(candidate =>
                SymbolEqualityComparer.Default.Equals(type.FindImplementationForInterfaceMember(candidate), member)));

    private static ISymbol? Overrides(ISymbol member) => member switch
    {
        IMethodSymbol method => method.OverriddenMethod,
        IPropertySymbol property => property.OverriddenProperty,
        IEventSymbol @event => @event.OverriddenEvent,
        _ => null,
    };
}
