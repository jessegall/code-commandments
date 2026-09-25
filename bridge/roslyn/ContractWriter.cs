using System.Collections;
using System.Collections.Concurrent;
using System.Reflection;
using System.Text;
using System.Text.Encodings.Web;
using System.Text.Json;
using System.Text.Json.Nodes;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis.CSharp.Syntax;

namespace CodeCommandments.Bridge;

/// <summary>
/// Writes a run as the generic tree (contract/CONTRACT.md): a header, a line per file, the outside
/// declarations the files reach, and a trailer. It reads the compiler exactly as <see cref="TreeWriter"/>
/// does; only the shape differs.
/// </summary>
public sealed class ContractWriter(Project project, IReadOnlyList<string> roots, IReadOnlySet<string>? written = null)
{
    public const int Version = 2;

    /// <summary>How a line is written: as deep as the version-7 writer's <see cref="Utf8JsonWriter"/> goes, since a long chain of expressions nests past the serializer's own 64.</summary>
    private static readonly JsonSerializerOptions Json = new() { Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping, MaxDepth = 1000 };

    private readonly Project project = project;

    private readonly TreeWriter readings = new(project);

    private readonly Dictionary<string, INamedTypeSymbol> outside = new(StringComparer.Ordinal);

    private int calls;

    private int resolved;

    public void Write(Stream output)
    {
        Line(output, "header", new JsonObject
        {
            ["contract"] = "tree",
            ["version"] = Version,
            ["language"] = "csharp",
            ["bridge"] = new JsonObject { ["name"] = "roslyn-bridge", ["version"] = TreeWriter.Version.ToString() },
            ["roots"] = new JsonArray(roots.Select(root => (JsonNode)Real(root)).ToArray()),
        });

        var files = 0;

        foreach (var tree in project.Trees.Where(tree => File.Exists(tree.FilePath)))
        {
            var context = written is { Count: > 0 } && !written.Contains(tree.FilePath);
            Line(output, "file", new FileWriter(this, tree, project.Model(tree)).File(context));
            files++;
        }

        Line(output, "program", new JsonObject { ["symbols"] = OutsideSymbols() });
        Line(output, "trailer", new JsonObject
        {
            ["files"] = files,
            ["resolution"] = new JsonObject { ["calls"] = calls, ["resolved"] = resolved },
        });
    }

    private static void Line(Stream output, string key, JsonObject value)
    {
        var line = new JsonObject { [key] = value }.ToJsonString(Json);
        output.Write(Encoding.UTF8.GetBytes(line + "\n"));
    }

    /// <summary><paramref name="path"/> absolute, with symbolic links resolved.</summary>
    private static string Real(string path)
    {
        var full = Path.GetFullPath(path);
        var info = new FileInfo(full);
        var target = info.Exists ? info.ResolveLinkTarget(true) : new DirectoryInfo(full).ResolveLinkTarget(true);

        return target?.FullName ?? full;
    }

    /// <summary>Keep <paramref name="type"/> and its ancestors for the program line when they are declared outside the scan.</summary>
    private void Remember(INamedTypeSymbol type)
    {
        var original = type.OriginalDefinition;

        if (original.Locations.Any(location => location.IsInSource))
        {
            return;
        }

        if (!outside.TryAdd(original.ToDisplayString(TreeWriter.Qualified), original))
        {
            return;
        }

        if (original.BaseType is { } parent)
        {
            Remember(parent);
        }

        foreach (var face in original.Interfaces)
        {
            Remember(face);
        }
    }

    private JsonArray OutsideSymbols()
    {
        var symbols = new JsonArray();

        foreach (var (id, type) in outside.OrderBy(entry => entry.Key, StringComparer.Ordinal))
        {
            var symbol = new JsonObject
            {
                ["symbol"] = id,
                ["kind"] = type.TypeKind switch { TypeKind.Interface => "interface", TypeKind.Enum => "enum", TypeKind.Struct => "struct", _ => "class" },
                ["name"] = type.Name,
            };

            if (type.BaseType is { } parent)
            {
                symbol["extends"] = new JsonArray(parent.OriginalDefinition.ToDisplayString(TreeWriter.Qualified));
            }

            if (type.Interfaces.Length > 0)
            {
                symbol["implements"] = new JsonArray(type.Interfaces.Select(face => (JsonNode)face.OriginalDefinition.ToDisplayString(TreeWriter.Qualified)).ToArray());
            }

            symbols.Add(symbol);
        }

        return symbols;
    }

    /// <summary>One file's line: its nodes numbered in pre-order, then its comments attached to them.</summary>
    private sealed class FileWriter(ContractWriter run, SyntaxTree tree, SemanticModel model)
    {
        private readonly string text = tree.GetText().ToString();

        private readonly int[] bytes = TreeWriter.ByteOffsets(tree.GetText().ToString(), TreeWriter.MarkLength(tree.FilePath));

        private readonly List<(int Start, int End, int Id)> spans = [];

        /// <summary>The outermost node starting at each byte offset: the first a pre-order walk meets there.</summary>
        private readonly Dictionary<int, int> startingAt = [];

        private byte[]? source;

        private int next;

        public JsonObject File(bool context)
        {
            var file = new JsonObject
            {
                ["path"] = Real(tree.FilePath),
                ["language"] = "csharp",
                ["errors"] = tree.GetDiagnostics().Count(diagnostic => diagnostic.Severity == DiagnosticSeverity.Error),
            };

            if (context)
            {
                file["context"] = true;
            }

            if (run.project.IsTest(tree))
            {
                file["test"] = true;
            }

            file["resolver"] = new JsonObject { ["tool"] = "roslyn", ["ran"] = true };
            file["root"] = Node(tree.GetRoot(), null);
            file["comments"] = Comments();

            return file;
        }

        private JsonObject Node(SyntaxNode node, string? field)
        {
            var id = next++;
            var (start, end) = (bytes[node.SpanStart], bytes[node.Span.End]);
            spans.Add((start, end, id));
            startingAt.TryAdd(start, id);

            var written = new JsonObject { ["id"] = id, ["kind"] = node.Kind().ToString(), ["role"] = Role(node) };
            var neutral = Neutral(node);

            if (neutral.Count > 0)
            {
                written["is"] = new JsonArray(neutral.Select(value => (JsonNode)value).ToArray());
            }

            written["span"] = new JsonArray(start, end, tree.GetLineSpan(node.Span).StartLinePosition.Line + 1);

            if (field is not null)
            {
                written["field"] = field;
            }

            Facts(node, written);

            var fields = FieldsOf(node);
            var children = node.ChildNodes().Select(child => (JsonNode)Node(child, fields[child])).ToArray();

            if (children.Length > 0)
            {
                written["children"] = new JsonArray(children);
            }

            return written;
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

        private static readonly ConcurrentDictionary<Type, PropertyInfo[]> Properties = new();

        /// <summary>The properties of a syntax class that can hold a child, in declaration order, read once per class.</summary>
        private static PropertyInfo[] PropertiesOf(Type syntax) => Properties.GetOrAdd(syntax, type => type
            .GetProperties(BindingFlags.Public | BindingFlags.Instance)
            .Where(property => property.GetIndexParameters().Length == 0 && property.Name != "Parent")
            .Where(property => typeof(SyntaxNode).IsAssignableFrom(property.PropertyType) || (property.PropertyType.IsGenericType && property.PropertyType.Name.Contains("SyntaxList")))
            .ToArray());

        private void Facts(SyntaxNode node, JsonObject written)
        {
            if (Name(node) is { Length: > 0 } name)
            {
                written["name"] = name;
            }

            Literal(node, written);

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
                written["operator"] = op;
            }

            var modifiers = node switch
            {
                MemberDeclarationSyntax member => member.Modifiers,
                ParameterSyntax parameter => parameter.Modifiers,
                LocalFunctionStatementSyntax local => local.Modifiers,
                _ => default,
            };

            if (modifiers.Count > 0)
            {
                written["modifiers"] = new JsonArray(modifiers.Select(token => (JsonNode)token.Text).ToArray());
            }

            var flags = Flags(node);

            if (flags.Count > 0)
            {
                written["flags"] = new JsonArray(flags.Select(flag => (JsonNode)flag).ToArray());
            }

            Declared(node, written);
            Resolved(node, written);
            Target(node, written);
            Refers(node, written);

            if (node is PostfixUnaryExpressionSyntax { RawKind: (int)SyntaxKind.SuppressNullableWarningExpression } forgiven && TreeWriter.IsDeclaredNullable(forgiven.Operand, model))
            {
                written["extras"] = new JsonObject { ["csharp"] = new JsonObject { ["forgivesNull"] = true } };
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

        private static void Literal(SyntaxNode node, JsonObject written)
        {
            switch (node)
            {
                case InterpolatedStringExpressionSyntax:
                    written["literal"] = "interpolated";
                    break;
                case InterpolatedStringTextSyntax part:
                    written["value"] = part.TextToken.ValueText;
                    break;
                case LiteralExpressionSyntax literal when literal.IsKind(SyntaxKind.StringLiteralExpression) || literal.IsKind(SyntaxKind.Utf8StringLiteralExpression) || literal.IsKind(SyntaxKind.CharacterLiteralExpression):
                    written["literal"] = "string";
                    written["value"] = literal.Token.ValueText;
                    break;
                case LiteralExpressionSyntax literal when literal.IsKind(SyntaxKind.TrueLiteralExpression) || literal.IsKind(SyntaxKind.FalseLiteralExpression):
                    written["literal"] = "bool";
                    written["value"] = literal.IsKind(SyntaxKind.TrueLiteralExpression);
                    break;
                case LiteralExpressionSyntax literal when literal.IsKind(SyntaxKind.NullLiteralExpression) || literal.IsKind(SyntaxKind.DefaultLiteralExpression):
                    written["literal"] = "null";
                    written["value"] = null;
                    break;
                case LiteralExpressionSyntax literal when literal.IsKind(SyntaxKind.NumericLiteralExpression):
                    written["literal"] = literal.Token.Value is double or float or decimal ? "float" : "int";
                    written["value"] = Convert.ToString(literal.Token.Value, System.Globalization.CultureInfo.InvariantCulture);
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
        private void Declared(SyntaxNode node, JsonObject written)
        {
            var declared = node is BaseNamespaceDeclarationSyntax or CompilationUnitSyntax ? null : model.GetDeclaredSymbol(node);

            if (declared is not null)
            {
                written["symbol"] = declared.OriginalDefinition.ToDisplayString(TreeWriter.Declared);

                if (node is MemberDeclarationSyntax && (declared.IsOverride || TreeWriter.ImplementsInterfaceMember(declared)))
                {
                    written["inherited"] = true;
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
                SetType(written, "declared", type, "written");
            }

            if (declared is IMethodSymbol { MethodKind: MethodKind.Ordinary or MethodKind.LocalFunction, ReturnsVoid: false } method)
            {
                SetType(written, "returns", method.ReturnType, "written");
            }
        }

        /// <summary>The type the compiler gives an expression, and whether its value is fixed at compile time.</summary>
        private void Resolved(SyntaxNode node, JsonObject written)
        {
            if (node is not ExpressionSyntax expression || SyntaxFacts.IsInTypeOnlyContext(expression))
            {
                return;
            }

            if (model.GetTypeInfo(expression).Type is { } type and not IErrorTypeSymbol)
            {
                SetType(written, "resolved", type, "compiler");
            }

            if (TreeWriter.IsConstant(expression, model))
            {
                written["constant"] = true;
            }
        }

        /// <summary>The declaration a call or construction reaches, as its original definition.</summary>
        private void Target(SyntaxNode node, JsonObject written)
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
            written["target"] = new JsonObject
            {
                ["symbol"] = original.ToDisplayString(TreeWriter.Declared),
                ["type"] = original.ContainingType.ToDisplayString(TreeWriter.Qualified),
                ["name"] = original.Name,
            };
            run.Remember(original.ContainingType);
        }

        /// <summary>The type a name in a type position names.</summary>
        private void Refers(SyntaxNode node, JsonObject written)
        {
            if (node is not (SimpleNameSyntax or QualifiedNameSyntax) || !SyntaxFacts.IsInTypeOnlyContext((TypeSyntax)node))
            {
                return;
            }

            if (model.GetSymbolInfo(node).Symbol is INamedTypeSymbol named)
            {
                written["refers"] = named.OriginalDefinition.ToDisplayString(TreeWriter.Qualified);
                run.Remember(named);
            }
        }

        /// <summary>Writes <paramref name="type"/> under <paramref name="key"/>, when it can be spelled.</summary>
        private void SetType(JsonObject node, string key, ITypeSymbol type, string origin)
        {
            if (Type(type, origin) is { } written)
            {
                node[key] = written;
            }
        }

        /// <summary>
        /// The type as the contract writes it; none when any part of it has no spelling at all, as an element the
        /// compiler could not type leaves a tuple — absent, never guessed.
        /// </summary>
        private JsonObject? Type(ITypeSymbol type, string origin)
        {
            var text = type.ToDisplayString(TreeWriter.Qualified);

            if (text.Length == 0)
            {
                return null;
            }

            var written = new JsonObject { ["text"] = text };

            switch (type)
            {
                case IErrorTypeSymbol:
                    written["kind"] = "opaque";
                    break;
                case IArrayTypeSymbol array:
                    written["kind"] = "array";
                    if (Type(array.ElementType, origin) is not { } element)
                    {
                        return null;
                    }

                    written["args"] = new JsonArray(element);
                    break;
                case ITypeParameterSymbol parameter:
                    written["kind"] = "parameter";
                    written["name"] = parameter.Name;
                    break;
                case INamedTypeSymbol { IsTupleType: true } tuple:
                    written["kind"] = "tuple";
                    var members = tuple.TupleElements.Select(element => Type(element.Type, origin)).ToList();

                    if (members.Contains(null))
                    {
                        return null;
                    }

                    written["members"] = new JsonArray(members.Select(member => (JsonNode)member!).ToArray());
                    break;
                case INamedTypeSymbol named:
                    written["kind"] = "named";
                    written["name"] = named.OriginalDefinition.ToDisplayString(TreeWriter.Qualified.WithGenericsOptions(SymbolDisplayGenericsOptions.None));

                    if (named.TypeArguments.Length > 0)
                    {
                        var arguments = named.TypeArguments.Select(argument => Type(argument, origin)).ToList();

                        if (arguments.Contains(null))
                        {
                            return null;
                        }

                        written["args"] = new JsonArray(arguments.Select(argument => (JsonNode)argument!).ToArray());
                    }

                    run.Remember(named);
                    break;
                default:
                    written["kind"] = "opaque";
                    break;
            }

            if (type.NullableAnnotation == NullableAnnotation.Annotated || type is INamedTypeSymbol { OriginalDefinition.SpecialType: SpecialType.System_Nullable_T })
            {
                written["nullable"] = true;
            }

            if (type.IsValueType && type is not ITypeParameterSymbol)
            {
                written["valueType"] = true;
            }

            written["origin"] = origin;

            return written;
        }

        /// <summary>Every comment in the file, in order, attached to the node it leads or trails.</summary>
        private JsonArray Comments()
        {
            var found = tree.GetRoot().DescendantTrivia(descendIntoTrivia: false).Where(TreeWriter.IsComment).ToList();
            var commentEnds = new Dictionary<int, int>();

            foreach (var trivia in found)
            {
                commentEnds.TryAdd(bytes[trivia.FullSpan.Start], bytes[Trimmed(trivia)]);
            }
            var comments = new JsonArray();

            foreach (var trivia in found)
            {
                var (start, end) = (bytes[trivia.FullSpan.Start], bytes[Trimmed(trivia)]);
                var comment = new JsonObject
                {
                    ["id"] = comments.Count,
                    ["kind"] = TreeWriter.CommentKind(trivia),
                    ["text"] = text[trivia.FullSpan.Start..Trimmed(trivia)],
                    ["span"] = new JsonArray(start, end, tree.GetLineSpan(trivia.Span).StartLinePosition.Line + 1),
                };

                Attach(comment, start, end, commentEnds);

                if (trivia.GetStructure() is DocumentationCommentTriviaSyntax documentation)
                {
                    var refs = Refs(documentation);

                    if (refs.Count > 0)
                    {
                        comment["refs"] = refs;
                    }
                }

                if (TreeWriter.IsCode(trivia))
                {
                    comment["extras"] = new JsonObject { ["csharp"] = new JsonObject { ["code"] = true } };
                }

                comments.Add(comment);
            }

            return comments;
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
        private JsonArray Refs(DocumentationCommentTriviaSyntax documentation)
        {
            var refs = new JsonArray();

            foreach (var cref in documentation.DescendantNodes().OfType<CrefSyntax>().Where(cref => cref.Parent is not CrefSyntax))
            {
                var reference = new JsonObject { ["text"] = cref.ToString() };
                var info = model.GetSymbolInfo(cref);
                var symbol = info.Symbol ?? info.CandidateSymbols.FirstOrDefault();
                var owner = symbol is null ? TreeWriter.Owner(cref, model) : null;

                if (symbol is not null)
                {
                    reference["symbol"] = symbol.ToDisplayString(TreeWriter.Declared);
                }

                if (owner is not null)
                {
                    reference["owner"] = owner.ToDisplayString(TreeWriter.Qualified);
                }

                if (owner?.Locations.Any(location => location.IsInSource) == true)
                {
                    reference["ownedHere"] = true;
                }

                if (symbol is null && run.readings.IsBlind(model))
                {
                    reference["blind"] = true;
                }

                refs.Add(reference);
            }

            return refs;
        }

        /// <summary>
        /// A trailing comment belongs to the outermost node ending on its line before it; a leading one to the
        /// outermost node starting at the first token after it, other comments skipped.
        /// </summary>
        private void Attach(JsonObject comment, int start, int end, Dictionary<int, int> commentEnds)
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
                    comment["attached"] = owner.Id;
                }

                comment["trailing"] = true;

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
                comment["attached"] = next;
            }
        }
    }
}
