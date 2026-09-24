using System.Text;
using System.Text.Json;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis.CSharp.Syntax;

namespace CodeCommandments.Bridge;

/// <summary>
/// Writes a run as JSON: every file's syntax tree, node by node, with what the compiler resolved about
/// each — an expression's type, an invocation's target, whether a member overrides one. A fact the
/// compiler could not resolve is left out, never guessed.
/// </summary>
public sealed class TreeWriter(Project project, IReadOnlySet<string>? written = null)
{
    private int calls;

    /// <summary>The UTF-8 byte offset of every character position in the file being written.</summary>
    private int[] bytes = [];

    private int resolved;

    private bool? blindGlobally;

    private readonly Dictionary<SyntaxTree, bool> blindFiles = [];

    public const int Version = 4;

    /// <summary>How every type and member is written: fully qualified, `System.String` never `string`, `?` kept.</summary>
    private static readonly SymbolDisplayFormat Qualified = SymbolDisplayFormat.FullyQualifiedFormat
        .RemoveMiscellaneousOptions(SymbolDisplayMiscellaneousOptions.UseSpecialTypes)
        .AddMiscellaneousOptions(SymbolDisplayMiscellaneousOptions.IncludeNullableReferenceTypeModifier);

    /// <summary>How a declared member is named: fully qualified, with its containing type and its parameters' types — as a call's resolved target names it.</summary>
    private static readonly SymbolDisplayFormat Declared = Qualified
        .WithMemberOptions(SymbolDisplayMemberOptions.IncludeContainingType | SymbolDisplayMemberOptions.IncludeParameters)
        .WithParameterOptions(SymbolDisplayParameterOptions.IncludeType);

    /// <summary>
    /// The response as lines: the version, one line per file, then the resolution, which closes it — so
    /// a reader holds one file at a time, however large the project.
    /// </summary>
    public void Write(Stream output)
    {
        Line(output, json => json.WriteNumber("version", Version));

        foreach (var tree in project.Trees.Where(tree => written is null || written.Count == 0 || written.Contains(tree.FilePath)))
        {
            var model = project.Model(tree);
            bytes = ByteOffsets(tree.GetText().ToString(), MarkLength(tree.FilePath));

            Line(output, json =>
            {
                json.WriteString("path", tree.FilePath);
                json.WriteNumber("errors", tree.GetDiagnostics().Count(diagnostic => diagnostic.Severity == DiagnosticSeverity.Error));

                if (project.IsTest(tree))
                {
                    json.WriteBoolean("test", true);
                }
                WriteComments(json, tree.GetRoot(), model);
                json.WritePropertyName("root");
                WriteNode(json, tree.GetRoot(), model);
            });
        }

        Line(output, json =>
        {
            json.WriteStartObject("resolution");
            json.WriteNumber("calls", calls);
            json.WriteNumber("resolved", resolved);
            json.WriteEndObject();
        });
    }

    /// <summary>
    /// Are the names in scope of <paramref name="model"/>'s file blind — the compiler finding a type or namespace
    /// missing there (CS0246, CS0234), or a global `using` anywhere in the project resolving to nothing — so a
    /// name may live in a reference the compilation lacks?
    /// </summary>
    private bool IsBlind(SemanticModel model)
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
    private static ISymbol? Owner(CrefSyntax cref, SemanticModel model)
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
    private static IEnumerable<UsingDirectiveSyntax> UnresolvedUsings(SyntaxNode root, SemanticModel model) => root
        .DescendantNodes(node => node is CompilationUnitSyntax or BaseNamespaceDeclarationSyntax)
        .OfType<UsingDirectiveSyntax>()
        .Where(directive => directive.NamespaceOrType is { } target && model.GetSymbolInfo(target).Symbol is null);

    /// <summary>One JSON object on a line of its own, its members written by <paramref name="members"/>.</summary>
    private static void Line(Stream output, Action<Utf8JsonWriter> members)
    {
        using (var json = new Utf8JsonWriter(output))
        {
            json.WriteStartObject();
            members(json);
            json.WriteEndObject();
        }

        output.WriteByte((byte)'\n');
    }

    /// <summary>
    /// Every comment in the file, in order: its kind (a line, a block, or a documentation comment), its text and
    /// its span — and for a documentation comment, each `cref` it names with the symbol it resolved to.
    /// </summary>
    private void WriteComments(Utf8JsonWriter json, SyntaxNode root, SemanticModel model)
    {
        json.WriteStartArray("comments");

        foreach (var trivia in root.DescendantTrivia(descendIntoTrivia: false).Where(IsComment))
        {
            json.WriteStartObject();
            json.WriteString("kind", CommentKind(trivia));
            json.WriteString("text", trivia.ToFullString());
            json.WriteNumber("start", bytes[trivia.FullSpan.Start]);
            json.WriteNumber("end", bytes[trivia.FullSpan.End]);

            if (trivia.GetStructure() is DocumentationCommentTriviaSyntax documentation)
            {
                json.WriteStartArray("crefs");

                foreach (var cref in documentation.DescendantNodes().OfType<CrefSyntax>().Where(cref => cref.Parent is not CrefSyntax))
                {
                    json.WriteStartObject();
                    json.WriteString("text", cref.ToString());

                    var info = model.GetSymbolInfo(cref);
                    var symbol = info.Symbol ?? info.CandidateSymbols.FirstOrDefault();
                    var owner = symbol is null ? Owner(cref, model) : null;

                    if (symbol is not null)
                    {
                        json.WriteString("symbol", symbol.ToDisplayString(Declared));
                    }

                    if (owner is not null)
                    {
                        json.WriteString("owner", owner.ToDisplayString(Qualified));
                    }

                    json.WriteBoolean("ownedHere", owner?.Locations.Any(location => location.IsInSource) == true);
                    json.WriteBoolean("blind", symbol is null && IsBlind(model));

                    json.WriteEndObject();
                }

                json.WriteEndArray();
            }

            json.WriteEndObject();
        }

        json.WriteEndArray();
    }

    private static bool IsComment(SyntaxTrivia trivia) => trivia.Kind() is SyntaxKind.SingleLineCommentTrivia
        or SyntaxKind.MultiLineCommentTrivia
        or SyntaxKind.SingleLineDocumentationCommentTrivia
        or SyntaxKind.MultiLineDocumentationCommentTrivia;

    private static string CommentKind(SyntaxTrivia trivia) => trivia.Kind() switch
    {
        SyntaxKind.SingleLineCommentTrivia => "line",
        SyntaxKind.MultiLineCommentTrivia => "block",
        _ => "doc",
    };

    private void WriteNode(Utf8JsonWriter json, SyntaxNode node, SemanticModel model)
    {
        json.WriteStartObject();
        json.WriteString("kind", node.Kind().ToString());
        json.WriteString("role", Role(node));
        json.WriteNumber("start", bytes[node.SpanStart]);
        json.WriteNumber("end", bytes[node.Span.End]);

        WriteName(json, node);
        WriteText(json, node);
        WriteModifiers(json, node);
        WriteFacts(json, node, model);

        var children = node.ChildNodes().ToList();

        if (children.Count > 0)
        {
            json.WriteStartArray("children");

            foreach (var child in children)
            {
                WriteNode(json, child, model);
            }

            json.WriteEndArray();
        }

        json.WriteEndObject();
    }

    /// <summary>
    /// Where each character position of <paramref name="text"/> falls in the file's bytes — Roslyn counts
    /// UTF-16 units from after the byte-order mark, a PHP reader counts bytes from the file's first, and
    /// the two part at the mark and at the first character outside ASCII.
    /// </summary>
    private static int[] ByteOffsets(string text, int mark)
    {
        var offsets = new int[text.Length + 1];
        var total = mark;

        for (var i = 0; i < text.Length; i++)
        {
            offsets[i] = total;
            total += char.IsHighSurrogate(text[i]) ? 4 : char.IsLowSurrogate(text[i]) ? 0 : text[i] < 0x80 ? 1 : text[i] < 0x800 ? 2 : 3;
        }

        offsets[text.Length] = total;

        return offsets;
    }

    /// <summary>The length of the UTF-8 byte-order mark <paramref name="path"/> opens with, which the parsed text drops.</summary>
    private static int MarkLength(string path)
    {
        using var file = File.OpenRead(path);
        Span<byte> head = stackalloc byte[3];

        return file.Read(head) == 3 && head.SequenceEqual(Encoding.UTF8.Preamble) ? 3 : 0;
    }

    /// <summary>
    /// What part the node plays — a statement, an expression, a member or type declaration, a type, or
    /// anything else (a parameter, an argument, a clause) — as Roslyn's own class hierarchy says it.
    /// </summary>
    private static string Role(SyntaxNode node) => node switch
    {
        StatementSyntax => "statement",
        MemberDeclarationSyntax => "member",
        TypeSyntax type when SyntaxFacts.IsInTypeOnlyContext(type) => "type",
        ExpressionSyntax => "expression",
        _ => "other",
    };

    /// <summary>The name a declaration or an identifier carries.</summary>
    private static void WriteName(Utf8JsonWriter json, SyntaxNode node)
    {
        var name = node switch
        {
            BaseTypeDeclarationSyntax type => type.Identifier.ValueText,
            DelegateDeclarationSyntax @delegate => @delegate.Identifier.ValueText,
            MethodDeclarationSyntax method => method.Identifier.ValueText,
            ConstructorDeclarationSyntax constructor => constructor.Identifier.ValueText,
            PropertyDeclarationSyntax property => property.Identifier.ValueText,
            EventDeclarationSyntax @event => @event.Identifier.ValueText,
            EnumMemberDeclarationSyntax member => member.Identifier.ValueText,
            LocalFunctionStatementSyntax local => local.Identifier.ValueText,
            ParameterSyntax parameter => parameter.Identifier.ValueText,
            VariableDeclaratorSyntax variable => variable.Identifier.ValueText,
            SimpleNameSyntax simple => simple.Identifier.ValueText,
            ForEachStatementSyntax loop => loop.Identifier.ValueText,
            CatchDeclarationSyntax @catch => @catch.Identifier.ValueText,
            SingleVariableDesignationSyntax designation => designation.Identifier.ValueText,
            TupleElementSyntax element => element.Identifier.ValueText,
            PredefinedTypeSyntax predefined => predefined.Keyword.ValueText,
            _ => null,
        };

        if (!string.IsNullOrEmpty(name))
        {
            json.WriteString("name", name);
        }
    }

    /// <summary>What a literal or an operator is written as — its value is a fact of the source.</summary>
    private static void WriteText(Utf8JsonWriter json, SyntaxNode node)
    {
        switch (node)
        {
            case LiteralExpressionSyntax literal:
                json.WriteString("text", literal.Token.ValueText);
                break;
            case InterpolatedStringTextSyntax text:
                json.WriteString("text", text.TextToken.ValueText);
                break;
            case BinaryExpressionSyntax binary:
                json.WriteString("operator", binary.OperatorToken.Text);
                break;
            case AssignmentExpressionSyntax assignment:
                json.WriteString("operator", assignment.OperatorToken.Text);
                break;
            case PrefixUnaryExpressionSyntax prefix:
                json.WriteString("operator", prefix.OperatorToken.Text);
                break;
            case PostfixUnaryExpressionSyntax postfix:
                json.WriteString("operator", postfix.OperatorToken.Text);
                break;
        }
    }

    private static void WriteModifiers(Utf8JsonWriter json, SyntaxNode node)
    {
        var modifiers = node switch
        {
            MemberDeclarationSyntax member => member.Modifiers,
            LocalFunctionStatementSyntax local => local.Modifiers,
            ParameterSyntax parameter => parameter.Modifiers,
            _ => default,
        };

        if (modifiers.Count == 0)
        {
            return;
        }

        json.WriteStartArray("modifiers");

        foreach (var modifier in modifiers)
        {
            json.WriteStringValue(modifier.Text);
        }

        json.WriteEndArray();
    }

    /// <summary>What the compiler resolved about the node, when it resolved anything.</summary>
    /// <summary>
    /// Has <paramref name="expression"/> a value the compiler fixes — folded; a name bound to an enum
    /// member or a <c>const</c>, as the right side of <c>x is Status.Paid</c> is, though it stands where
    /// a type could; or <c>typeof</c> a concrete type. <c>typeof(T)</c> over a type parameter varies.
    /// </summary>
    private static bool IsConstant(ExpressionSyntax expression, SemanticModel model) =>
        model.GetConstantValue(expression).HasValue
        || model.GetSymbolInfo(expression).Symbol is IFieldSymbol { HasConstantValue: true }
        || (expression is TypeOfExpressionSyntax typeOf && model.GetTypeInfo(typeOf.Type).Type is not (null or ITypeParameterSymbol or IErrorTypeSymbol));

    /// <summary>
    /// Does what <paramref name="operand"/> names — a field, property, local, parameter, or a method's
    /// return — declare a type that admits null? What a <c>!</c> on it silences, read from the
    /// declaration, since the operand of a <c>!</c> reports the state after it.
    /// </summary>
    private static bool IsDeclaredNullable(ExpressionSyntax operand, SemanticModel model)
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

    /// <summary>
    /// A type as the compiler resolved it: its name, whether it is annotated nullable, and — for a generic or an
    /// array — the named types inside it, however deep, so a reader never takes the name apart.
    /// </summary>
    private static void WriteType(Utf8JsonWriter json, ITypeSymbol type, bool nullable)
    {
        json.WriteString("type", type.ToDisplayString(Qualified));
        json.WriteBoolean("nullable", nullable);

        var inner = InnerTypes(type).Select(named => named.ToDisplayString(Qualified)).Distinct().ToList();

        if (inner.Count > 0)
        {
            json.WriteStartArray("inner");

            foreach (var name in inner)
            {
                json.WriteStringValue(name);
            }

            json.WriteEndArray();
        }
    }

    /// <summary>The named types inside <paramref name="type"/> — its type arguments and element types, at every depth.</summary>
    private static IEnumerable<INamedTypeSymbol> InnerTypes(ITypeSymbol type)
    {
        var parts = type switch
        {
            INamedTypeSymbol named => named.TypeArguments,
            IArrayTypeSymbol array => [array.ElementType],
            _ => [],
        };

        foreach (var part in parts)
        {
            if (part is INamedTypeSymbol namedPart)
            {
                yield return namedPart.WithNullableAnnotation(NullableAnnotation.NotAnnotated) as INamedTypeSymbol ?? namedPart;
            }

            foreach (var deeper in InnerTypes(part))
            {
                yield return deeper;
            }
        }
    }

    private void WriteFacts(Utf8JsonWriter json, SyntaxNode node, SemanticModel model)
    {
        if (node is ExpressionSyntax expression && !SyntaxFacts.IsInTypeOnlyContext(expression))
        {
            var info = model.GetTypeInfo(expression);

            if (info.Type is not null and not IErrorTypeSymbol)
            {
                WriteType(json, info.Type, info.Type.NullableAnnotation == NullableAnnotation.Annotated);
            }

        }

        if (node is TypeSyntax named && SyntaxFacts.IsInTypeOnlyContext(named) && named.Parent is not TypeSyntax && model.GetTypeInfo(named).Type is { } namedType and not IErrorTypeSymbol)
        {
            WriteType(json, namedType, namedType.NullableAnnotation == NullableAnnotation.Annotated);
        }

        if (node is ParameterSyntax declaration && model.GetDeclaredSymbol(declaration) is IParameterSymbol declaredParameter)
        {
            WriteType(json, declaredParameter.Type, declaredParameter.Type.NullableAnnotation == NullableAnnotation.Annotated);
        }

        if (node is CatchDeclarationSyntax caught && model.GetTypeInfo(caught.Type).Type is { } exception and not IErrorTypeSymbol)
        {
            json.WriteString("type", exception.ToDisplayString(Qualified));
            json.WriteBoolean("nullable", false);
        }

        if (node is PostfixUnaryExpressionSyntax { RawKind: (int)SyntaxKind.SuppressNullableWarningExpression } forgiven && IsDeclaredNullable(forgiven.Operand, model))
        {
            json.WriteBoolean("forgivesNull", true);
        }

        if (node is ExpressionSyntax step && step.Parent is ForStatementSyntax loop && loop.Incrementors.Contains(step))
        {
            json.WriteBoolean("step", true);
        }

        if (node is ExpressionSyntax value && IsConstant(value, model))
        {
            json.WriteBoolean("constant", true);
        }

        if (node is InvocationExpressionSyntax or ObjectCreationExpressionSyntax or ImplicitObjectCreationExpressionSyntax)
        {
            calls++;

            if (model.GetSymbolInfo(node).Symbol is IMethodSymbol target)
            {
                resolved++;
                json.WriteStartObject("target");
                json.WriteString("type", target.ContainingType.ToDisplayString(Qualified));
                json.WriteString("name", target.Name);
                json.WriteStartArray("parameters");

                foreach (var parameter in target.Parameters)
                {
                    json.WriteStringValue(parameter.Type.ToDisplayString(Qualified));
                }

                json.WriteEndArray();
                json.WriteEndObject();
            }
        }

        if (node is MemberDeclarationSyntax member && model.GetDeclaredSymbol(member) is { } declared)
        {
            json.WriteString("symbol", declared.ToDisplayString(Declared));

            if (declared.IsOverride || ImplementsInterfaceMember(declared))
            {
                json.WriteBoolean("inherited", true);
            }
        }
    }

    private static bool ImplementsInterfaceMember(ISymbol member) =>
        member.ContainingType is { } type
        && type.AllInterfaces.Any(@interface => @interface.GetMembers().Any(candidate =>
            SymbolEqualityComparer.Default.Equals(type.FindImplementationForInterfaceMember(candidate), member)));
}
