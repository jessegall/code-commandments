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
public sealed class TreeWriter(Project project)
{
    public const int Version = 1;

    public void Write(Stream output)
    {
        using var json = new Utf8JsonWriter(output);

        json.WriteStartObject();
        json.WriteNumber("version", Version);
        json.WriteStartArray("files");

        foreach (var tree in project.Trees)
        {
            var model = project.Compilation.GetSemanticModel(tree);

            json.WriteStartObject();
            json.WriteString("path", tree.FilePath);
            json.WriteNumber("errors", tree.GetDiagnostics().Count(diagnostic => diagnostic.Severity == DiagnosticSeverity.Error));
            json.WritePropertyName("root");
            WriteNode(json, tree.GetRoot(), model);
            json.WriteEndObject();
        }

        json.WriteEndArray();
        json.WriteEndObject();
    }

    private static void WriteNode(Utf8JsonWriter json, SyntaxNode node, SemanticModel model)
    {
        json.WriteStartObject();
        json.WriteString("kind", node.Kind().ToString());
        json.WriteNumber("start", node.SpanStart);
        json.WriteNumber("end", node.Span.End);

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
    private static void WriteFacts(Utf8JsonWriter json, SyntaxNode node, SemanticModel model)
    {
        if (node is ExpressionSyntax expression && node is not TypeSyntax)
        {
            var type = model.GetTypeInfo(expression).Type;

            if (type is not null and not IErrorTypeSymbol)
            {
                json.WriteString("type", type.ToDisplayString(SymbolDisplayFormat.FullyQualifiedFormat));
                json.WriteBoolean("nullable", type.NullableAnnotation == NullableAnnotation.Annotated);
            }
        }

        if (node is InvocationExpressionSyntax or ObjectCreationExpressionSyntax or ImplicitObjectCreationExpressionSyntax)
        {
            if (model.GetSymbolInfo(node).Symbol is IMethodSymbol target)
            {
                json.WriteStartObject("target");
                json.WriteString("type", target.ContainingType.ToDisplayString(SymbolDisplayFormat.FullyQualifiedFormat));
                json.WriteString("name", target.Name);
                json.WriteStartArray("parameters");

                foreach (var parameter in target.Parameters)
                {
                    json.WriteStringValue(parameter.Type.ToDisplayString(SymbolDisplayFormat.FullyQualifiedFormat));
                }

                json.WriteEndArray();
                json.WriteEndObject();
            }
        }

        if (node is MemberDeclarationSyntax member && model.GetDeclaredSymbol(member) is { } declared)
        {
            json.WriteString("symbol", declared.ToDisplayString(SymbolDisplayFormat.FullyQualifiedFormat));

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
