// Writes C# files as one generic tree stream (contract/CONTRACT.md), the way a C# bridge will.
// Usage: emit-csharp <project-folder> (<file> <path-in-stream>)...
using System.Collections;
using System.Reflection;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis.CSharp.Syntax;

var folder = args[0];
var pairs = args.Skip(1).Chunk(2).Select(pair => (File: Path.GetFullPath(pair[0]), Shown: pair[1])).ToList();
var options = new CSharpParseOptions(LanguageVersion.Latest, DocumentationMode.Diagnose);
var globalUsings = CSharpSyntaxTree.ParseText(
    "global using System; global using System.Collections.Generic; global using System.IO; global using System.Linq; global using System.Threading; global using System.Threading.Tasks;",
    options);
var trees = Directory.GetFiles(folder, "*.cs", SearchOption.AllDirectories)
    .Where(path => !path.Contains("/obj/") && !path.Contains("/bin/"))
    .Select(path => CSharpSyntaxTree.ParseText(File.ReadAllText(path), options, Path.GetFullPath(path)))
    .Append(globalUsings)
    .ToList();
var references = ((string)AppContext.GetData("TRUSTED_PLATFORM_ASSEMBLIES")!).Split(Path.PathSeparator)
    .Select(path => MetadataReference.CreateFromFile(path));
var compilation = CSharpCompilation.Create("Shop", trees, references,
    new CSharpCompilationOptions(OutputKind.DynamicallyLinkedLibrary, nullableContextOptions: NullableContextOptions.Enable));
var outside = new Dictionary<string, INamedTypeSymbol>();
var (calls, resolved) = (0, 0);

Emit(new JsonObject
{
    ["header"] = new JsonObject
    {
        ["contract"] = "tree", ["version"] = 1, ["language"] = "csharp",
        ["bridge"] = new JsonObject { ["name"] = "contract/samples/emit/csharp", ["version"] = "1" },
        ["roots"] = new JsonArray(pairs.Select(pair => (JsonNode)pair.Shown!).ToArray()),
    },
});
foreach (var (file, shown) in pairs)
{
    var tree = trees.Single(candidate => candidate.FilePath == file);
    var writer = new TreeWriter(tree, compilation.GetSemanticModel(tree), outside);
    var root = writer.Node(tree.GetRoot(), null);
    Emit(new JsonObject
    {
        ["file"] = new JsonObject
        {
            ["path"] = shown, ["language"] = "csharp", ["errors"] = tree.GetDiagnostics().Count(d => d.Severity == DiagnosticSeverity.Error),
            ["resolver"] = new JsonObject { ["tool"] = "roslyn", ["ran"] = true },
            ["root"] = root, ["comments"] = writer.Comments(),
        },
    });
    (calls, resolved) = (calls + writer.Calls, resolved + writer.Resolved);
}
Emit(new JsonObject { ["program"] = new JsonObject { ["symbols"] = TreeWriter.OutsideSymbols(outside) } });
Emit(new JsonObject
{
    ["trailer"] = new JsonObject
    {
        ["files"] = pairs.Count,
        ["resolution"] = new JsonObject { ["calls"] = calls, ["resolved"] = resolved },
    },
});

static void Emit(JsonObject line) =>
    Console.WriteLine(line.ToJsonString(new JsonSerializerOptions { Encoder = System.Text.Encodings.Web.JavaScriptEncoder.UnsafeRelaxedJsonEscaping }));

sealed class TreeWriter(SyntaxTree tree, SemanticModel model, Dictionary<string, INamedTypeSymbol> outside)
{
    private static readonly SymbolDisplayFormat Qualified = SymbolDisplayFormat.FullyQualifiedFormat
        .RemoveMiscellaneousOptions(SymbolDisplayMiscellaneousOptions.UseSpecialTypes)
        .AddMiscellaneousOptions(SymbolDisplayMiscellaneousOptions.IncludeNullableReferenceTypeModifier);

    private static readonly SymbolDisplayFormat Declared = Qualified
        .WithMemberOptions(SymbolDisplayMemberOptions.IncludeContainingType | SymbolDisplayMemberOptions.IncludeParameters)
        .WithParameterOptions(SymbolDisplayParameterOptions.IncludeType);

    private readonly string text = tree.GetText().ToString();
    private readonly int[] bytes = ByteOffsets(tree.GetText().ToString());
    private readonly List<(int Start, int End, int Id)> spans = [];
    private int next;
    private List<(int Start, int End)> commentSpans = [];

    public int Calls { get; private set; }

    public int Resolved { get; private set; }

    public JsonObject Node(SyntaxNode node, SyntaxNode? parent)
    {
        var id = next++;
        var (start, end) = (bytes[node.Span.Start], bytes[node.Span.End]);
        spans.Add((start, end, id));
        var out_ = new JsonObject { ["id"] = id, ["kind"] = node.Kind().ToString(), ["role"] = Role(node) };
        var neutral = Neutral(node);
        if (neutral.Count > 0) out_["is"] = new JsonArray(neutral.Select(value => (JsonNode)value!).ToArray());
        out_["span"] = new JsonArray(start, end, tree.GetLineSpan(node.Span).StartLinePosition.Line + 1);
        if (parent is not null) out_["field"] = FieldOf(parent, node);
        Facts(node, out_);
        var children = node.ChildNodes().Select(child => (JsonNode)Node(child, node)).ToArray();
        if (children.Length > 0) out_["children"] = new JsonArray(children);

        return out_;
    }

    private static string Role(SyntaxNode node) => node switch
    {
        BaseNamespaceDeclarationSyntax => "other",
        MemberDeclarationSyntax or LocalFunctionStatementSyntax or AccessorDeclarationSyntax => "member",
        StatementSyntax => "statement",
        PatternSyntax => "pattern",
        TypeSyntax type when SyntaxFacts.IsInTypeOnlyContext(type) => "type",
        ExpressionSyntax => "expression",
        _ => "other",
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
            ("comparison", node.IsKind(SyntaxKind.EqualsExpression) || node.IsKind(SyntaxKind.NotEqualsExpression) || node.IsKind(SyntaxKind.LessThanExpression) || node.IsKind(SyntaxKind.GreaterThanExpression) || node.IsKind(SyntaxKind.LessThanOrEqualExpression) || node.IsKind(SyntaxKind.GreaterThanOrEqualExpression)),
            ("literal", node is LiteralExpressionSyntax or InterpolatedStringExpressionSyntax),
            ("import", node is UsingDirectiveSyntax),
            ("catch", node is CatchClauseSyntax),
        };

        return answers.Where(answer => answer.Yes).Select(answer => answer.Name).ToList();
    }

    private static string FieldOf(SyntaxNode parent, SyntaxNode child)
    {
        foreach (var property in parent.GetType().GetProperties(BindingFlags.Public | BindingFlags.Instance))
        {
            if (property.GetIndexParameters().Length > 0) continue;
            if (typeof(SyntaxNode).IsAssignableFrom(property.PropertyType))
            {
                if (property.GetValue(parent) == child) return property.Name;
            }
            else if (property.PropertyType.IsGenericType && property.PropertyType.Name.Contains("SyntaxList"))
            {
                if (property.GetValue(parent) is IEnumerable items && items.Cast<object>().Contains(child)) return property.Name;
            }
        }

        throw new InvalidOperationException($"{child.Kind()} fills no property of {parent.Kind()}");
    }

    private void Facts(SyntaxNode node, JsonObject out_)
    {
        var name = NameOf(node);
        if (name is not null) out_["name"] = name;
        Literal(node, out_);
        var op = node switch
        {
            BinaryExpressionSyntax binary => binary.OperatorToken.Text,
            AssignmentExpressionSyntax assignment => assignment.OperatorToken.Text,
            PrefixUnaryExpressionSyntax prefix => prefix.OperatorToken.Text,
            PostfixUnaryExpressionSyntax postfix => postfix.OperatorToken.Text,
            _ => null,
        };
        if (op is not null) out_["operator"] = op;
        var modifiers = node switch
        {
            MemberDeclarationSyntax member => member.Modifiers,
            ParameterSyntax parameter => parameter.Modifiers,
            LocalFunctionStatementSyntax local => local.Modifiers,
            _ => default,
        };
        if (modifiers.Count > 0) out_["modifiers"] = new JsonArray(modifiers.Select(token => (JsonNode)token.Text!).ToArray());
        var flags = Flags(node);
        if (flags.Count > 0) out_["flags"] = new JsonArray(flags.Select(flag => (JsonNode)flag!).ToArray());
        DeclaredFacts(node, out_);
        if (node is ExpressionSyntax expression && !SyntaxFacts.IsInTypeOnlyContext(expression) && !(node.Parent is MemberAccessExpressionSyntax access && access.Name == node))
        {
            var type = model.GetTypeInfo(node).Type;
            if (type is not null and not IErrorTypeSymbol) out_["resolved"] = Type(type, "compiler");
            var constant = model.GetConstantValue(node);
            if (constant.HasValue) out_["constant"] = true;
        }
        if (node.IsKind(SyntaxKind.SuppressNullableWarningExpression) && IsDeclaredNullable(((PostfixUnaryExpressionSyntax)node).Operand))
            out_["extras"] = new JsonObject { ["csharp"] = new JsonObject { ["forgivesNull"] = true } };
        if (node is InvocationExpressionSyntax or BaseObjectCreationExpressionSyntax)
        {
            Calls++;
            if (model.GetSymbolInfo(node).Symbol is IMethodSymbol method)
            {
                Resolved++;
                var original = (method.ReducedFrom ?? method).OriginalDefinition;
                out_["target"] = new JsonObject
                {
                    ["symbol"] = original.ToDisplayString(Declared),
                    ["type"] = original.ContainingType.ToDisplayString(Qualified),
                    ["name"] = original.Name,
                };
                Remember(original.ContainingType);
            }
        }
        if (node is IdentifierNameSyntax or GenericNameSyntax or QualifiedNameSyntax && SyntaxFacts.IsInTypeOnlyContext((TypeSyntax)node))
        {
            if (model.GetSymbolInfo(node).Symbol is INamedTypeSymbol named)
            {
                out_["refers"] = named.OriginalDefinition.ToDisplayString(Qualified);
                Remember(named);
            }
        }
    }

    private void DeclaredFacts(SyntaxNode node, JsonObject out_)
    {
        var declared = model.GetDeclaredSymbol(node);
        if (declared is not null && node is not BaseNamespaceDeclarationSyntax and not CompilationUnitSyntax)
        {
            out_["symbol"] = declared.OriginalDefinition.ToDisplayString(Declared);
            if (declared.IsOverride || ImplementsInterfaceMember(declared)) out_["inherited"] = true;
        }
        var written = declared switch
        {
            IParameterSymbol parameter => parameter.Type,
            IPropertySymbol property => property.Type,
            IFieldSymbol field => field.Type,
            ILocalSymbol local => local.Type,
            _ => null,
        };
        if (node is CatchDeclarationSyntax catchDeclaration) written = model.GetTypeInfo(catchDeclaration.Type).Type;
        if (written is not null and not IErrorTypeSymbol) out_["declared"] = Type(written, "written");
        if (declared is IMethodSymbol { MethodKind: MethodKind.Ordinary or MethodKind.LocalFunction, ReturnsVoid: false } method)
            out_["returns"] = Type(method.ReturnType, "written");
    }

    /// <summary>Whether the operand's own declaration is nullable — a `T?` field, property, local, parameter or return.</summary>
    private bool IsDeclaredNullable(ExpressionSyntax operand) => model.GetSymbolInfo(operand).Symbol switch
    {
        IFieldSymbol field => field.NullableAnnotation == NullableAnnotation.Annotated,
        IPropertySymbol property => property.NullableAnnotation == NullableAnnotation.Annotated,
        ILocalSymbol local => local.NullableAnnotation == NullableAnnotation.Annotated,
        IParameterSymbol parameter => parameter.NullableAnnotation == NullableAnnotation.Annotated,
        IMethodSymbol method => method.ReturnNullableAnnotation == NullableAnnotation.Annotated,
        _ => false,
    };

    private static bool ImplementsInterfaceMember(ISymbol symbol) =>
        symbol.ContainingType is { } type && type.AllInterfaces
            .SelectMany(face => face.GetMembers())
            .Any(member => SymbolEqualityComparer.Default.Equals(type.FindImplementationForInterfaceMember(member), symbol));

    private static string? NameOf(SyntaxNode node) => node switch
    {
        BaseTypeDeclarationSyntax type => type.Identifier.Text,
        MethodDeclarationSyntax method => method.Identifier.Text,
        PropertyDeclarationSyntax property => property.Identifier.Text,
        ConstructorDeclarationSyntax constructor => constructor.Identifier.Text,
        LocalFunctionStatementSyntax local => local.Identifier.Text,
        ParameterSyntax parameter => parameter.Identifier.Text,
        VariableDeclaratorSyntax variable => variable.Identifier.Text,
        EnumMemberDeclarationSyntax member => member.Identifier.Text,
        SimpleNameSyntax simple => simple.Identifier.Text,
        PredefinedTypeSyntax predefined => predefined.Keyword.Text,
        _ => null,
    };

    private static void Literal(SyntaxNode node, JsonObject out_)
    {
        if (node is InterpolatedStringExpressionSyntax)
        {
            out_["literal"] = "interpolated";
            return;
        }
        if (node is not LiteralExpressionSyntax literal) return;
        switch (literal.Kind())
        {
            case SyntaxKind.StringLiteralExpression:
                out_["literal"] = "string"; out_["value"] = literal.Token.ValueText; break;
            case SyntaxKind.TrueLiteralExpression or SyntaxKind.FalseLiteralExpression:
                out_["literal"] = "bool"; out_["value"] = literal.IsKind(SyntaxKind.TrueLiteralExpression); break;
            case SyntaxKind.NullLiteralExpression:
                out_["literal"] = "null"; out_["value"] = null; break;
            case SyntaxKind.NumericLiteralExpression:
                var value = literal.Token.Value;
                out_["literal"] = value is double or float or decimal ? "float" : "int";
                out_["value"] = Convert.ToString(value, System.Globalization.CultureInfo.InvariantCulture);
                break;
        }
    }

    private static List<string> Flags(SyntaxNode node)
    {
        var flags = new List<string>();
        if (node is ParameterSyntax parameter)
        {
            if (parameter.Modifiers.Any(SyntaxKind.ParamsKeyword)) flags.Add("variadic");
            if (parameter.Modifiers.Any(SyntaxKind.RefKeyword)) flags.Add("by-ref");
        }
        if (node is ArgumentSyntax { NameColon: not null }) flags.Add("named");
        if (node is AnonymousFunctionExpressionSyntax { AsyncKeyword.RawKind: not 0 } || node is MethodDeclarationSyntax method && method.Modifiers.Any(SyntaxKind.AsyncKeyword)) flags.Add("async");
        if (node.Parent is ForStatementSyntax loop && loop.Incrementors.Contains(node)) flags.Add("step");

        return flags;
    }

    private JsonObject Type(ITypeSymbol type, string origin)
    {
        var out_ = new JsonObject { ["text"] = type.ToDisplayString(Qualified) };
        switch (type)
        {
            case IArrayTypeSymbol array:
                out_["kind"] = "array";
                out_["args"] = new JsonArray(Type(array.ElementType, origin));
                break;
            case ITypeParameterSymbol parameter:
                out_["kind"] = "parameter";
                out_["name"] = parameter.Name;
                break;
            case INamedTypeSymbol named:
                out_["kind"] = "named";
                out_["name"] = named.OriginalDefinition.ToDisplayString(Qualified.WithGenericsOptions(SymbolDisplayGenericsOptions.None));
                if (named.TypeArguments.Length > 0) out_["args"] = new JsonArray(named.TypeArguments.Select(argument => (JsonNode)Type(argument, origin)).ToArray());
                Remember(named);
                break;
            default:
                out_["kind"] = "opaque";
                break;
        }
        if (type.NullableAnnotation == NullableAnnotation.Annotated) out_["nullable"] = true;
        if (type.IsValueType && type is not ITypeParameterSymbol) out_["valueType"] = true;
        out_["origin"] = origin;

        return out_;
    }

    private void Remember(INamedTypeSymbol type)
    {
        var original = type.OriginalDefinition;
        if (original.Locations.Any(location => location.IsInSource)) return;
        var id = original.ToDisplayString(Qualified);
        if (!outside.TryAdd(id, original)) return;
        if (original.BaseType is { } parent) Remember(parent);
        foreach (var face in original.Interfaces) Remember(face);
    }

    public static JsonArray OutsideSymbols(Dictionary<string, INamedTypeSymbol> outside)
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
            if (type.BaseType is { } parent) symbol["extends"] = new JsonArray(parent.OriginalDefinition.ToDisplayString(Qualified));
            if (type.Interfaces.Length > 0) symbol["implements"] = new JsonArray(type.Interfaces.Select(face => (JsonNode)face.OriginalDefinition.ToDisplayString(Qualified)!).ToArray());
            symbols.Add(symbol);
        }

        return symbols;
    }

    public JsonArray Comments()
    {
        var found = tree.GetRoot().DescendantTrivia(descendIntoTrivia: false)
            .Where(trivia => trivia.IsKind(SyntaxKind.SingleLineCommentTrivia) || trivia.IsKind(SyntaxKind.MultiLineCommentTrivia)
                || trivia.IsKind(SyntaxKind.SingleLineDocumentationCommentTrivia) || trivia.IsKind(SyntaxKind.MultiLineDocumentationCommentTrivia))
            .OrderBy(trivia => trivia.FullSpan.Start);
        var comments = new JsonArray();
        commentSpans = found.Select(trivia => (bytes[trivia.FullSpan.Start], bytes[trivia.FullSpan.End])).ToList();
        foreach (var trivia in found)
        {
            var doc = trivia.IsKind(SyntaxKind.SingleLineDocumentationCommentTrivia) || trivia.IsKind(SyntaxKind.MultiLineDocumentationCommentTrivia);
            var startChar = trivia.FullSpan.Start;
            var endChar = trivia.FullSpan.End;
            while (endChar > startChar && (text[endChar - 1] == '\n' || text[endChar - 1] == '\r')) endChar--;
            if (!doc) endChar = trivia.Span.End;
            var comment = new JsonObject
            {
                ["id"] = comments.Count,
                ["kind"] = doc ? "doc" : trivia.IsKind(SyntaxKind.MultiLineCommentTrivia) ? "block" : "line",
                ["text"] = text[startChar..endChar],
                ["span"] = new JsonArray(bytes[startChar], bytes[endChar], tree.GetLineSpan(new Microsoft.CodeAnalysis.Text.TextSpan(startChar, 0)).StartLinePosition.Line + 1),
            };
            Attach(comment, bytes[startChar], bytes[endChar]);
            if (doc && trivia.GetStructure() is DocumentationCommentTriviaSyntax structure)
            {
                var refs = new JsonArray();
                foreach (var cref in structure.DescendantNodes().OfType<XmlCrefAttributeSyntax>())
                {
                    var reference = new JsonObject { ["text"] = cref.Cref.ToString() };
                    if (model.GetSymbolInfo(cref.Cref).Symbol is { } resolved) reference["symbol"] = resolved.OriginalDefinition.ToDisplayString(Declared);
                    refs.Add(reference);
                }
                if (refs.Count > 0) comment["refs"] = refs;
            }
            comments.Add(comment);
        }

        return comments;
    }

    private void Attach(JsonObject comment, int start, int end)
    {
        var source = Encoding.UTF8.GetBytes(text);
        var lineStart = Array.LastIndexOf(source, (byte)'\n', Math.Max(start - 1, 0)) + 1;
        if (Encoding.UTF8.GetString(source, lineStart, start - lineStart).Trim().Length > 0)
        {
            var owner = spans.FirstOrDefault(span => span.End <= start && span.End > lineStart);
            if (owner != default) comment["attached"] = owner.Id;
            comment["trailing"] = true;
            return;
        }
        var after = end;
        while (true)
        {
            while (after < source.Length && source[after] is (byte)' ' or (byte)'\t' or (byte)'\r' or (byte)'\n') after++;
            var skipped = commentSpans.FirstOrDefault(span => span.Start == after);
            if (skipped == default) break;
            after = skipped.End;
        }
        var next = spans.FirstOrDefault(span => span.Start == after);
        if (next != default) comment["attached"] = next.Id;
    }

    private static int[] ByteOffsets(string text)
    {
        var offsets = new int[text.Length + 1];
        var mark = 0;
        for (var index = 0; index < text.Length; index++)
        {
            offsets[index] = mark;
            mark += char.IsHighSurrogate(text[index]) ? 2 : char.IsLowSurrogate(text[index]) ? 2 : Encoding.UTF8.GetByteCount(text[index].ToString());
        }
        offsets[text.Length] = mark;

        return offsets;
    }
}
