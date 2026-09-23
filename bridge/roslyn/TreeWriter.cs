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

    public const int Version = 1;

    /// <summary>How every type and member is written: fully qualified, `System.String` never `string`, `?` kept.</summary>
    private static readonly SymbolDisplayFormat Qualified = SymbolDisplayFormat.FullyQualifiedFormat
        .RemoveMiscellaneousOptions(SymbolDisplayMiscellaneousOptions.UseSpecialTypes)
        .AddMiscellaneousOptions(SymbolDisplayMiscellaneousOptions.IncludeNullableReferenceTypeModifier);

    public void Write(Stream output)
    {
        using var json = new Utf8JsonWriter(output);

        json.WriteStartObject();
        json.WriteNumber("version", Version);
        json.WriteStartArray("files");

        foreach (var tree in project.Trees.Where(tree => written is null || written.Count == 0 || written.Contains(tree.FilePath)))
        {
            var model = project.Compilation.GetSemanticModel(tree);
            bytes = ByteOffsets(tree.GetText().ToString());

            json.WriteStartObject();
            json.WriteString("path", tree.FilePath);
            json.WriteNumber("errors", tree.GetDiagnostics().Count(diagnostic => diagnostic.Severity == DiagnosticSeverity.Error));
            json.WritePropertyName("root");
            WriteNode(json, tree.GetRoot(), model);
            json.WriteEndObject();
        }

        json.WriteEndArray();
        json.WriteStartObject("resolution");
        json.WriteNumber("calls", calls);
        json.WriteNumber("resolved", resolved);
        json.WriteEndObject();
        json.WriteEndObject();
    }

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
    /// Where each character position of $text falls in its UTF-8 encoding — Roslyn counts UTF-16 units,
    /// a PHP reader counts bytes, and the two part at the first character outside ASCII.
    /// </summary>
    private static int[] ByteOffsets(string text)
    {
        var offsets = new int[text.Length + 1];
        var total = 0;

        for (var i = 0; i < text.Length; i++)
        {
            offsets[i] = total;
            total += char.IsHighSurrogate(text[i]) ? 4 : char.IsLowSurrogate(text[i]) ? 0 : text[i] < 0x80 ? 1 : text[i] < 0x800 ? 2 : 3;
        }

        offsets[text.Length] = total;

        return offsets;
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
            LocalFunctionStatementSyntax local => local.Identifier.ValueText,
            ParameterSyntax parameter => parameter.Identifier.ValueText,
            VariableDeclaratorSyntax variable => variable.Identifier.ValueText,
            SimpleNameSyntax simple => simple.Identifier.ValueText,
            ForEachStatementSyntax loop => loop.Identifier.ValueText,
            CatchDeclarationSyntax @catch => @catch.Identifier.ValueText,
            SingleVariableDesignationSyntax designation => designation.Identifier.ValueText,
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
    private void WriteFacts(Utf8JsonWriter json, SyntaxNode node, SemanticModel model)
    {
        if (node is ExpressionSyntax expression && !SyntaxFacts.IsInTypeOnlyContext(expression))
        {
            var type = model.GetTypeInfo(expression).Type;

            if (type is not null and not IErrorTypeSymbol)
            {
                json.WriteString("type", type.ToDisplayString(Qualified));
                json.WriteBoolean("nullable", type.NullableAnnotation == NullableAnnotation.Annotated);
            }
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
            json.WriteString("symbol", declared.ToDisplayString(Qualified));

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
