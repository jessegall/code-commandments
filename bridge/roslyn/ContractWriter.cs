using System.Collections;
using System.Collections.Concurrent;
using System.Reflection;
using System.Text;
using System.Text.Encodings.Web;
using System.Text.Json;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis.CSharp.Syntax;

namespace CodeCommandments.Bridge;

/// <summary>
/// Writes a run as the generic tree (contract/CONTRACT.md): a header, a line per file, the outside declarations the
/// files reach, and a trailer. It reads the compiler exactly as <see cref="TreeWriter"/> does; only the shape differs.
/// It streams: one project at a time, each node written straight through as it is read, nothing of a project kept
/// once its files are written but the plain text of the outside declarations it reached.
/// </summary>
public sealed class ContractWriter(IReadOnlyList<string> roots, IReadOnlySet<string>? written = null)
{
    public const int Version = 2;

    /// <summary>How a line is written: as deep as the version-7 writer goes, since a long chain of expressions nests past 64.</summary>
    private static readonly JsonWriterOptions Json = new() { Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping, MaxDepth = 1000 };

    /// <summary>The outside declarations the files reach, as the program line writes them.</summary>
    private readonly SortedDictionary<string, OutsideSymbol> outside = new(StringComparer.Ordinal);

    private int calls;

    private int resolved;

    private int files;

    public void Write(Stream output, Workspace workspace)
    {
        Line(output, json =>
        {
            json.WriteStartObject("header");
            json.WriteString("contract", "tree");
            json.WriteNumber("version", Version);
            json.WriteString("language", "csharp");
            json.WriteStartObject("bridge");
            json.WriteString("name", "roslyn-bridge");
            json.WriteString("version", TreeWriter.Version.ToString());
            json.WriteEndObject();
            json.WriteStartArray("roots");

            foreach (var root in roots)
            {
                json.WriteStringValue(Real(root));
            }

            json.WriteEndArray();
            json.WriteEndObject();
        });

        workspace.Stream(roots, project => WriteProject(output, project));

        Line(output, json =>
        {
            json.WriteStartObject("program");
            json.WriteStartArray("symbols");

            foreach (var symbol in outside.Values)
            {
                symbol.Write(json);
            }

            json.WriteEndArray();
            json.WriteEndObject();
        });
        Line(output, json =>
        {
            json.WriteStartObject("trailer");
            json.WriteNumber("files", files);
            json.WriteStartObject("resolution");
            json.WriteNumber("calls", calls);
            json.WriteNumber("resolved", resolved);
            json.WriteEndObject();
            json.WriteEndObject();
        });
    }

    /// <summary>A line for each of the project's files that exists.</summary>
    private void WriteProject(Stream output, Project project)
    {
        var readings = new TreeWriter(project);

        foreach (var tree in project.Trees.Where(tree => File.Exists(tree.FilePath)))
        {
            var context = written is { Count: > 0 } && !written.Contains(tree.FilePath);
            var writer = new FileWriter(this, readings, project, tree, project.Model(tree));
            Line(output, json => writer.Write(json, context));
            files++;
        }
    }

    /// <summary>One JSON object on a line of its own, its members written by <paramref name="members"/>.</summary>
    private static void Line(Stream output, Action<Utf8JsonWriter> members)
    {
        using (var json = new Utf8JsonWriter(output, Json))
        {
            json.WriteStartObject();
            members(json);
            json.WriteEndObject();
        }

        output.WriteByte((byte)'\n');
    }

    /// <summary><paramref name="path"/> absolute, with symbolic links resolved.</summary>
    private static string Real(string path)
    {
        var full = Path.GetFullPath(path);
        var info = new FileInfo(full);
        var target = info.Exists ? info.ResolveLinkTarget(true) : new DirectoryInfo(full).ResolveLinkTarget(true);

        return target?.FullName ?? full;
    }

    /// <summary>Keeps <paramref name="type"/> and its ancestors for the program line when they are declared outside the scan, as the text it is written as.</summary>
    private void Remember(INamedTypeSymbol type)
    {
        var original = type.OriginalDefinition;

        if (original.Locations.Any(location => location.IsInSource))
        {
            return;
        }

        var id = original.ToDisplayString(TreeWriter.Qualified);

        if (outside.ContainsKey(id))
        {
            return;
        }

        outside[id] = new OutsideSymbol(
            id,
            original.TypeKind switch { TypeKind.Interface => "interface", TypeKind.Enum => "enum", TypeKind.Struct => "struct", _ => "class" },
            original.Name,
            original.BaseType?.OriginalDefinition.ToDisplayString(TreeWriter.Qualified),
            original.Interfaces.Select(face => face.OriginalDefinition.ToDisplayString(TreeWriter.Qualified)).ToList());

        if (original.BaseType is { } parent)
        {
            Remember(parent);
        }

        foreach (var face in original.Interfaces)
        {
            Remember(face);
        }
    }

    /// <summary>A declaration outside the scan, as the program line writes it.</summary>
    private sealed record OutsideSymbol(string Symbol, string Kind, string Name, string? Extends, IReadOnlyList<string> Implements)
    {
        public void Write(Utf8JsonWriter json)
        {
            json.WriteStartObject();
            json.WriteString("symbol", Symbol);
            json.WriteString("kind", Kind);
            json.WriteString("name", Name);

            if (Extends is not null)
            {
                json.WriteStartArray("extends");
                json.WriteStringValue(Extends);
                json.WriteEndArray();
            }

            if (Implements.Count > 0)
            {
                json.WriteStartArray("implements");

                foreach (var face in Implements)
                {
                    json.WriteStringValue(face);
                }

                json.WriteEndArray();
            }

            json.WriteEndObject();
        }
    }

    /// <summary>One file's line: its nodes numbered in pre-order, then its comments attached to them.</summary>
    private sealed class FileWriter(ContractWriter run, TreeWriter readings, Project project, SyntaxTree tree, SemanticModel model)
    {
        private static readonly ConcurrentDictionary<Type, PropertyInfo[]> Properties = new();

        private readonly string text = tree.GetText().ToString();

        private readonly int[] bytes = TreeWriter.ByteOffsets(tree.GetText().ToString(), TreeWriter.MarkLength(tree.FilePath));

        private readonly List<(int Start, int End, int Id)> spans = [];

        /// <summary>The outermost node starting at each byte offset: the first a pre-order walk meets there.</summary>
        private readonly Dictionary<int, int> startingAt = [];

        private byte[]? source;

        private int next;

        public void Write(Utf8JsonWriter json, bool context)
        {
            json.WriteStartObject("file");
            json.WriteString("path", Real(tree.FilePath));
            json.WriteString("language", "csharp");
            json.WriteNumber("errors", tree.GetDiagnostics().Count(diagnostic => diagnostic.Severity == DiagnosticSeverity.Error));

            if (context)
            {
                json.WriteBoolean("context", true);
            }

            if (project.IsTest(tree))
            {
                json.WriteBoolean("test", true);
            }

            json.WriteStartObject("resolver");
            json.WriteString("tool", "roslyn");
            json.WriteBoolean("ran", true);
            json.WriteEndObject();
            json.WritePropertyName("root");
            Node(json, tree.GetRoot(), null);
            WriteComments(json);
            json.WriteEndObject();
        }

        private void Node(Utf8JsonWriter json, SyntaxNode node, string? field)
        {
            var id = next++;
            var (start, end) = (bytes[node.SpanStart], bytes[node.Span.End]);
            spans.Add((start, end, id));
            startingAt.TryAdd(start, id);

            json.WriteStartObject();
            json.WriteNumber("id", id);
            json.WriteString("kind", node.Kind().ToString());
            json.WriteString("role", Role(node));
            Strings(json, "is", Neutral(node));
            json.WriteStartArray("span");
            json.WriteNumberValue(start);
            json.WriteNumberValue(end);
            json.WriteNumberValue(tree.GetLineSpan(node.Span).StartLinePosition.Line + 1);
            json.WriteEndArray();

            if (field is not null)
            {
                json.WriteString("field", field);
            }

            Facts(json, node);

            var children = node.ChildNodes().ToList();

            if (children.Count > 0)
            {
                var fields = FieldsOf(node);
                json.WriteStartArray("children");

                foreach (var child in children)
                {
                    Node(json, child, fields[child]);
                }

                json.WriteEndArray();
            }

            json.WriteEndObject();
        }

        /// <summary>A list of strings under <paramref name="key"/>, left out when empty.</summary>
        private static void Strings(Utf8JsonWriter json, string key, IReadOnlyCollection<string> values)
        {
            if (values.Count == 0)
            {
                return;
            }

            json.WriteStartArray(key);

            foreach (var value in values)
            {
                json.WriteStringValue(value);
            }

            json.WriteEndArray();
        }

        /// <summary>The contract's role: a namespace is neither a member nor a statement, and a pattern is its own.</summary>
        private static string Role(SyntaxNode node) => node switch
        {
            BaseNamespaceDeclarationSyntax => "other",
            LocalFunctionStatementSyntax or AccessorDeclarationSyntax => "member",
            PatternSyntax => "pattern",
            _ => TreeWriter.Role(node),
        };

        private static List<string> Neutral(SyntaxNode node)
        {
            var answers = new (string Name, bool Yes)[]
            {
                ("function", node is BaseMethodDeclarationSyntax { Body: not null } or BaseMethodDeclarationSyntax { ExpressionBody: not null } or LocalFunctionStatementSyntax or AnonymousFunctionExpressionSyntax or AccessorDeclarationSyntax { Body: not null } or AccessorDeclarationSyntax { ExpressionBody: not null }),
                ("type-declaration", node is BaseTypeDeclarationSyntax or DelegateDeclarationSyntax),
                ("parameter", node is ParameterSyntax),
                ("block", node is BlockSyntax),
                ("branch", node is IfStatementSyntax or SwitchStatementSyntax or SwitchExpressionSyntax or ConditionalExpressionSyntax),
                ("loop", node is ForStatementSyntax or CommonForEachStatementSyntax or WhileStatementSyntax or DoStatementSyntax),
                ("return", node is ReturnStatementSyntax),
                ("throw", node is ThrowStatementSyntax or ThrowExpressionSyntax),
                ("bail-out", node is ReturnStatementSyntax or ThrowStatementSyntax or BreakStatementSyntax or ContinueStatementSyntax),
                ("expression-statement", node is ExpressionStatementSyntax),
                ("call", node is InvocationExpressionSyntax),
                ("construction", node is BaseObjectCreationExpressionSyntax),
                ("member-access", node is MemberAccessExpressionSyntax or MemberBindingExpressionSyntax),
                ("null-safe", node is ConditionalAccessExpressionSyntax),
                ("self-reference", node is ThisExpressionSyntax),
                ("identifier", node is IdentifierNameSyntax identifier && !SyntaxFacts.IsInTypeOnlyContext(identifier)),
                ("assignment", node is AssignmentExpressionSyntax),
                ("comparison", node.Kind() is SyntaxKind.EqualsExpression or SyntaxKind.NotEqualsExpression or SyntaxKind.LessThanExpression or SyntaxKind.GreaterThanExpression or SyntaxKind.LessThanOrEqualExpression or SyntaxKind.GreaterThanOrEqualExpression),
                ("literal", node is LiteralExpressionSyntax or InterpolatedStringExpressionSyntax),
                ("import", node is UsingDirectiveSyntax),
                ("catch", node is CatchClauseSyntax),
            };

            return answers.Where(answer => answer.Yes).Select(answer => answer.Name).ToList();
        }

        /// <summary>The Roslyn property of <paramref name="parent"/> each of its children fills, the first that holds it.</summary>
        private static Dictionary<SyntaxNode, string> FieldsOf(SyntaxNode parent)
        {
            var fields = new Dictionary<SyntaxNode, string>();

            foreach (var property in PropertiesOf(parent.GetType()))
            {
                switch (property.GetValue(parent))
                {
                    case SyntaxNode child:
                        fields.TryAdd(child, property.Name);
                        break;
                    case IEnumerable items when property.PropertyType.IsGenericType && property.PropertyType.Name.Contains("SyntaxList"):
                        foreach (var item in items.OfType<SyntaxNode>())
                        {
                            fields.TryAdd(item, property.Name);
                        }

                        break;
                }
            }

            foreach (var child in parent.ChildNodes().Where(child => !fields.ContainsKey(child)))
            {
                throw new InvalidOperationException($"{child.Kind()} fills no property of {parent.Kind()}");
            }

            return fields;
        }

        /// <summary>The properties of a syntax class that can hold a child, in declaration order, read once per class.</summary>
        private static PropertyInfo[] PropertiesOf(Type syntax) => Properties.GetOrAdd(syntax, type => type
            .GetProperties(BindingFlags.Public | BindingFlags.Instance)
            .Where(property => property.GetIndexParameters().Length == 0 && property.Name != "Parent")
            .Where(property => typeof(SyntaxNode).IsAssignableFrom(property.PropertyType) || (property.PropertyType.IsGenericType && property.PropertyType.Name.Contains("SyntaxList")))
            .ToArray());

        private void Facts(Utf8JsonWriter json, SyntaxNode node)
        {
            if (Name(node) is { Length: > 0 } name)
            {
                json.WriteString("name", name);
            }

            Literal(json, node);

            var op = node switch
            {
                BinaryExpressionSyntax binary => binary.OperatorToken.Text,
                AssignmentExpressionSyntax assignment => assignment.OperatorToken.Text,
                PrefixUnaryExpressionSyntax prefix => prefix.OperatorToken.Text,
                PostfixUnaryExpressionSyntax postfix => postfix.OperatorToken.Text,
                _ => null,
            };

            if (op is not null)
            {
                json.WriteString("operator", op);
            }

            var modifiers = node switch
            {
                MemberDeclarationSyntax member => member.Modifiers,
                ParameterSyntax parameter => parameter.Modifiers,
                LocalFunctionStatementSyntax local => local.Modifiers,
                _ => default,
            };

            Strings(json, "modifiers", modifiers.Select(token => token.Text).ToList());
            Strings(json, "flags", Flags(node));
            Declared(json, node);
            Resolved(json, node);
            Target(json, node);
            Refers(json, node);

            if (node is PostfixUnaryExpressionSyntax { RawKind: (int)SyntaxKind.SuppressNullableWarningExpression } forgiven && TreeWriter.IsDeclaredNullable(forgiven.Operand, model))
            {
                json.WriteStartObject("extras");
                json.WriteStartObject("csharp");
                json.WriteBoolean("forgivesNull", true);
                json.WriteEndObject();
                json.WriteEndObject();
            }
        }

        /// <summary>The name a declaration or an identifier carries, as <see cref="TreeWriter"/> reads it.</summary>
        private static string? Name(SyntaxNode node) => node switch
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
            TypeParameterSyntax parameter => parameter.Identifier.ValueText,
            PredefinedTypeSyntax predefined => predefined.Keyword.ValueText,
            _ => null,
        };

        private static void Literal(Utf8JsonWriter json, SyntaxNode node)
        {
            switch (node)
            {
                case InterpolatedStringExpressionSyntax:
                    json.WriteString("literal", "interpolated");
                    break;
                case InterpolatedStringTextSyntax part:
                    json.WriteString("value", part.TextToken.ValueText);
                    break;
                case LiteralExpressionSyntax literal when literal.IsKind(SyntaxKind.StringLiteralExpression) || literal.IsKind(SyntaxKind.Utf8StringLiteralExpression) || literal.IsKind(SyntaxKind.CharacterLiteralExpression):
                    json.WriteString("literal", "string");
                    json.WriteString("value", literal.Token.ValueText);
                    break;
                case LiteralExpressionSyntax literal when literal.IsKind(SyntaxKind.TrueLiteralExpression) || literal.IsKind(SyntaxKind.FalseLiteralExpression):
                    json.WriteString("literal", "bool");
                    json.WriteBoolean("value", literal.IsKind(SyntaxKind.TrueLiteralExpression));
                    break;
                case LiteralExpressionSyntax literal when literal.IsKind(SyntaxKind.NullLiteralExpression) || literal.IsKind(SyntaxKind.DefaultLiteralExpression):
                    json.WriteString("literal", "null");
                    json.WriteNull("value");
                    break;
                case LiteralExpressionSyntax literal when literal.IsKind(SyntaxKind.NumericLiteralExpression):
                    json.WriteString("literal", literal.Token.Value is double or float or decimal ? "float" : "int");
                    json.WriteString("value", Convert.ToString(literal.Token.Value, System.Globalization.CultureInfo.InvariantCulture));
                    break;
            }
        }

        private static List<string> Flags(SyntaxNode node)
        {
            var flags = new List<string>();

            if (node is ParameterSyntax parameter && parameter.Modifiers.Any(SyntaxKind.ParamsKeyword))
            {
                flags.Add("variadic");
            }

            if (node is ParameterSyntax byRef && byRef.Modifiers.Any(modifier => modifier.Kind() is SyntaxKind.RefKeyword or SyntaxKind.OutKeyword or SyntaxKind.InKeyword))
            {
                flags.Add("by-ref");
            }

            if (node is ArgumentSyntax { NameColon: not null })
            {
                flags.Add("named");
            }

            if (node is AnonymousFunctionExpressionSyntax { AsyncKeyword.RawKind: not 0 } || node is MethodDeclarationSyntax method && method.Modifiers.Any(SyntaxKind.AsyncKeyword) || node is LocalFunctionStatementSyntax local && local.Modifiers.Any(SyntaxKind.AsyncKeyword))
            {
                flags.Add("async");
            }

            if (node is ExpressionSyntax step && step.Parent is ForStatementSyntax loop && loop.Incrementors.Contains(step))
            {
                flags.Add("step");
            }

            if (node is NullableTypeSyntax)
            {
                flags.Add("nullable-sugar");
            }

            return flags;
        }

        /// <summary>A declaration's symbol, whether it overrides or implements, and the type and return type it declares.</summary>
        private void Declared(Utf8JsonWriter json, SyntaxNode node)
        {
            var declared = node is BaseNamespaceDeclarationSyntax or CompilationUnitSyntax ? null : model.GetDeclaredSymbol(node);

            if (declared is not null)
            {
                json.WriteString("symbol", declared.OriginalDefinition.ToDisplayString(TreeWriter.Declared));

                if (node is MemberDeclarationSyntax && (declared.IsOverride || TreeWriter.ImplementsInterfaceMember(declared)))
                {
                    json.WriteBoolean("inherited", true);
                }
            }

            var type = declared switch
            {
                IParameterSymbol parameter => parameter.Type,
                IPropertySymbol property => property.Type,
                IFieldSymbol field => field.Type,
                ILocalSymbol local => local.Type,
                IEventSymbol @event => @event.Type,
                _ => null,
            };

            if (node is CatchDeclarationSyntax caught)
            {
                type = model.GetTypeInfo(caught.Type).Type;
            }

            if (type is not null and not IErrorTypeSymbol)
            {
                Type(json, "declared", type, "written");
            }

            if (declared is IMethodSymbol { MethodKind: MethodKind.Ordinary or MethodKind.LocalFunction, ReturnsVoid: false } method)
            {
                Type(json, "returns", method.ReturnType, "written");
            }
        }

        /// <summary>The type the compiler gives an expression, and whether its value is fixed at compile time.</summary>
        private void Resolved(Utf8JsonWriter json, SyntaxNode node)
        {
            if (node is not ExpressionSyntax expression || SyntaxFacts.IsInTypeOnlyContext(expression))
            {
                return;
            }

            if (model.GetTypeInfo(expression).Type is { } type and not IErrorTypeSymbol)
            {
                Type(json, "resolved", type, "compiler");
            }

            if (TreeWriter.IsConstant(expression, model))
            {
                json.WriteBoolean("constant", true);
            }
        }

        /// <summary>The declaration a call or construction reaches, as its original definition.</summary>
        private void Target(Utf8JsonWriter json, SyntaxNode node)
        {
            if (node is not (InvocationExpressionSyntax or BaseObjectCreationExpressionSyntax))
            {
                return;
            }

            run.calls++;

            if (model.GetSymbolInfo(node).Symbol is not IMethodSymbol method)
            {
                return;
            }

            run.resolved++;
            var original = (method.ReducedFrom ?? method).OriginalDefinition;
            json.WriteStartObject("target");
            json.WriteString("symbol", original.ToDisplayString(TreeWriter.Declared));
            json.WriteString("type", original.ContainingType.ToDisplayString(TreeWriter.Qualified));
            json.WriteString("name", original.Name);
            json.WriteEndObject();
            run.Remember(original.ContainingType);
        }

        /// <summary>The type a name in a type position names.</summary>
        private void Refers(Utf8JsonWriter json, SyntaxNode node)
        {
            if (node is not (SimpleNameSyntax or QualifiedNameSyntax) || !SyntaxFacts.IsInTypeOnlyContext((TypeSyntax)node))
            {
                return;
            }

            if (model.GetSymbolInfo(node).Symbol is INamedTypeSymbol named)
            {
                json.WriteString("refers", named.OriginalDefinition.ToDisplayString(TreeWriter.Qualified));
                run.Remember(named);
            }
        }

        /// <summary>
        /// Writes <paramref name="type"/> under <paramref name="key"/> as the contract writes a type; left out when any part of
        /// it has no spelling at all, as an element the compiler could not type leaves a tuple: absent, never guessed.
        /// </summary>
        private void Type(Utf8JsonWriter json, string key, ITypeSymbol type, string origin)
        {
            if (!IsSpelled(type))
            {
                return;
            }

            json.WritePropertyName(key);
            Type(json, type, origin);
        }

        /// <summary>Does <paramref name="type"/> have a spelling, and every part of it?</summary>
        private static bool IsSpelled(ITypeSymbol type) => type.ToDisplayString(TreeWriter.Qualified).Length > 0 && type switch
        {
            IErrorTypeSymbol => true,
            IArrayTypeSymbol array => IsSpelled(array.ElementType),
            INamedTypeSymbol { IsTupleType: true } tuple => tuple.TupleElements.All(element => IsSpelled(element.Type)),
            INamedTypeSymbol named => named.TypeArguments.All(IsSpelled),
            _ => true,
        };

        private void Type(Utf8JsonWriter json, ITypeSymbol type, string origin)
        {
            json.WriteStartObject();
            json.WriteString("text", type.ToDisplayString(TreeWriter.Qualified));

            switch (type)
            {
                case IErrorTypeSymbol:
                    json.WriteString("kind", "opaque");
                    break;
                case IArrayTypeSymbol array:
                    json.WriteString("kind", "array");
                    json.WriteStartArray("args");
                    Type(json, array.ElementType, origin);
                    json.WriteEndArray();
                    break;
                case ITypeParameterSymbol parameter:
                    json.WriteString("kind", "parameter");
                    json.WriteString("name", parameter.Name);
                    break;
                case INamedTypeSymbol { IsTupleType: true } tuple:
                    json.WriteString("kind", "tuple");
                    json.WriteStartArray("members");

                    foreach (var element in tuple.TupleElements)
                    {
                        Type(json, element.Type, origin);
                    }

                    json.WriteEndArray();
                    break;
                case INamedTypeSymbol named:
                    json.WriteString("kind", "named");
                    json.WriteString("name", named.OriginalDefinition.ToDisplayString(TreeWriter.Qualified.WithGenericsOptions(SymbolDisplayGenericsOptions.None)));

                    if (named.TypeArguments.Length > 0)
                    {
                        json.WriteStartArray("args");

                        foreach (var argument in named.TypeArguments)
                        {
                            Type(json, argument, origin);
                        }

                        json.WriteEndArray();
                    }

                    run.Remember(named);
                    break;
                default:
                    json.WriteString("kind", "opaque");
                    break;
            }

            if (type.NullableAnnotation == NullableAnnotation.Annotated || type is INamedTypeSymbol { OriginalDefinition.SpecialType: SpecialType.System_Nullable_T })
            {
                json.WriteBoolean("nullable", true);
            }

            if (type.IsValueType && type is not ITypeParameterSymbol)
            {
                json.WriteBoolean("valueType", true);
            }

            json.WriteString("origin", origin);
            json.WriteEndObject();
        }

        /// <summary>Every comment in the file, in order, attached to the node it leads or trails.</summary>
        private void WriteComments(Utf8JsonWriter json)
        {
            var found = tree.GetRoot().DescendantTrivia(descendIntoTrivia: false).Where(TreeWriter.IsComment).ToList();
            var commentEnds = new Dictionary<int, int>();

            foreach (var trivia in found)
            {
                commentEnds.TryAdd(bytes[trivia.FullSpan.Start], bytes[Trimmed(trivia)]);
            }

            json.WriteStartArray("comments");

            for (var id = 0; id < found.Count; id++)
            {
                var trivia = found[id];
                var (start, end) = (bytes[trivia.FullSpan.Start], bytes[Trimmed(trivia)]);
                json.WriteStartObject();
                json.WriteNumber("id", id);
                json.WriteString("kind", TreeWriter.CommentKind(trivia));
                json.WriteString("text", text[trivia.FullSpan.Start..Trimmed(trivia)]);
                json.WriteStartArray("span");
                json.WriteNumberValue(start);
                json.WriteNumberValue(end);
                json.WriteNumberValue(tree.GetLineSpan(trivia.Span).StartLinePosition.Line + 1);
                json.WriteEndArray();
                Attach(json, start, end, commentEnds);

                if (trivia.GetStructure() is DocumentationCommentTriviaSyntax documentation)
                {
                    WriteRefs(json, documentation);
                }

                if (TreeWriter.IsCode(trivia))
                {
                    json.WriteStartObject("extras");
                    json.WriteStartObject("csharp");
                    json.WriteBoolean("code", true);
                    json.WriteEndObject();
                    json.WriteEndObject();
                }

                json.WriteEndObject();
            }

            json.WriteEndArray();
        }

        /// <summary>Where a comment's text ends: a documentation comment's trailing line break is not its own.</summary>
        private int Trimmed(SyntaxTrivia trivia)
        {
            var end = trivia.FullSpan.End;

            while (end > trivia.FullSpan.Start && text[end - 1] is '\n' or '\r')
            {
                end--;
            }

            return end;
        }

        /// <summary>Each <c>cref</c> as written, what it resolves to, and when it does not, the longest qualifier that does.</summary>
        private void WriteRefs(Utf8JsonWriter json, DocumentationCommentTriviaSyntax documentation)
        {
            var crefs = documentation.DescendantNodes().OfType<CrefSyntax>().Where(cref => cref.Parent is not CrefSyntax).ToList();

            if (crefs.Count == 0)
            {
                return;
            }

            json.WriteStartArray("refs");

            foreach (var cref in crefs)
            {
                var info = model.GetSymbolInfo(cref);
                var symbol = info.Symbol ?? info.CandidateSymbols.FirstOrDefault();
                var owner = symbol is null ? TreeWriter.Owner(cref, model) : null;
                json.WriteStartObject();
                json.WriteString("text", cref.ToString());

                if (symbol is not null)
                {
                    json.WriteString("symbol", symbol.ToDisplayString(TreeWriter.Declared));
                }

                if (owner is not null)
                {
                    json.WriteString("owner", owner.ToDisplayString(TreeWriter.Qualified));
                }

                if (owner?.Locations.Any(location => location.IsInSource) == true)
                {
                    json.WriteBoolean("ownedHere", true);
                }

                if (symbol is null && readings.IsBlind(model))
                {
                    json.WriteBoolean("blind", true);
                }

                json.WriteEndObject();
            }

            json.WriteEndArray();
        }

        /// <summary>
        /// A trailing comment belongs to the outermost node ending on its line before it; a leading one to the
        /// outermost node starting at the first token after it, other comments skipped.
        /// </summary>
        private void Attach(Utf8JsonWriter json, int start, int end, Dictionary<int, int> commentEnds)
        {
            var source = this.source ??= Encoding.UTF8.GetBytes(text);
            var mark = bytes[0];
            var local = start - mark;
            var lineStart = Array.LastIndexOf(source, (byte)'\n', Math.Max(local - 1, 0)) + 1;

            if (local > 0 && Encoding.UTF8.GetString(source, lineStart, local - lineStart).Trim().Length > 0)
            {
                var owner = spans.FirstOrDefault(span => span.End <= start && span.End > lineStart + mark);

                if (owner != default)
                {
                    json.WriteNumber("attached", owner.Id);
                }

                json.WriteBoolean("trailing", true);

                return;
            }

            var after = end;

            while (true)
            {
                while (after - mark < source.Length && source[after - mark] is (byte)' ' or (byte)'\t' or (byte)'\r' or (byte)'\n')
                {
                    after++;
                }

                if (!commentEnds.TryGetValue(after, out var skipped))
                {
                    break;
                }

                after = skipped;
            }

            if (startingAt.TryGetValue(after, out var next))
            {
                json.WriteNumber("attached", next);
            }
        }
    }
}
