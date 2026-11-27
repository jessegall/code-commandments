using System.Globalization;
using System.Text.Json;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis.CSharp.Syntax;
using Microsoft.CodeAnalysis.Text;

namespace CodeCommandments.Bridge;

public sealed partial class ContractWriter
{
    /// <summary>
    /// One C# file translated into the version 5 generic tree (contract/CONTRACT.md): one neutral kind per operation,
    /// its parts under named roles, its facts, and byte spans into the file. Compiler facts come from Roslyn.
    /// </summary>
    private sealed class Translator(ContractWriter run, Readings readings, SyntaxTree tree, SemanticModel model)
    {
        private static readonly Dictionary<string, string> Operators = new()
        {
            ["+"] = "add", ["-"] = "subtract", ["*"] = "multiply", ["/"] = "divide", ["%"] = "modulo", ["&"] = "bit-and",
            ["|"] = "bit-or", ["^"] = "bit-xor", ["<<"] = "shift-left", [">>"] = "shift-right", [">>>"] = "shift-right",
        };

        private static readonly Dictionary<SyntaxKind, string> Comparisons = new()
        {
            [SyntaxKind.EqualsExpression] = "equal", [SyntaxKind.NotEqualsExpression] = "not-equal",
            [SyntaxKind.LessThanExpression] = "less", [SyntaxKind.LessThanOrEqualExpression] = "less-or-equal",
            [SyntaxKind.GreaterThanExpression] = "greater", [SyntaxKind.GreaterThanOrEqualExpression] = "greater-or-equal",
        };

        private readonly SourceText text = tree.GetText();

        private readonly int[] bytes = Readings.ByteOffsets(tree.GetText().ToString(), Readings.MarkLength(tree.FilePath));

        /// <summary>The receivers of the null-conditional accesses being translated: where each one's binding starts, and its subject.</summary>
        private readonly Stack<(int Binding, TreeNode Receiver)> receivers = new();

        // Positions -------------------------------------------------------------------------------------------------

        private TreeNode New(string kind, TextSpan span)
        {
            if (receivers.Count > 0 && span.Start == receivers.Peek().Binding)
            {
                var receiver = receivers.Peek().Receiver;

                return new TreeNode(kind, receiver.Start, bytes[span.End], receiver.Line);
            }

            return new TreeNode(kind, bytes[span.Start], bytes[span.End], text.Lines.GetLineFromPosition(span.Start).LineNumber + 1);
        }

        private TreeNode New(string kind, SyntaxNode node) => New(kind, node.Span);

        private TreeNode New(string kind, SyntaxToken token) => New(kind, token.Span);

        /// <summary>The local, parameter and range variable each binding written so far declares, for its reads to link to.</summary>
        private readonly Dictionary<ISymbol, TreeNode> bound = new(SymbolEqualityComparer.Default);

        private readonly Dictionary<ITypeSymbol, int> typeIndexes = new(SymbolEqualityComparer.IncludeNullability);

        /// <summary>The file's types table: each type the compiler gave a node, once, which the node's resolvedType indexes.</summary>
        public List<Dictionary<string, object>> Types { get; } = [];

        private TreeNode Typed(TreeNode node, ITypeSymbol? type)
        {
            if (type is null or IErrorTypeSymbol)
            {
                return node;
            }

            if (!typeIndexes.TryGetValue(type, out var index))
            {
                index = Types.Count;
                typeIndexes[type] = index;
                Types.Add(Described(type, 2));
            }

            return node.Fact("resolvedType", index);
        }

        /// <summary>A type as the contract's Types table describes it, its arguments and elements described to the depth given.</summary>
        private static Dictionary<string, object> Described(ITypeSymbol type, int depth)
        {
            var described = new Dictionary<string, object> { ["text"] = type.ToDisplayString(Readings.Qualified) };
            IEnumerable<ITypeSymbol> arguments = [];
            switch (type)
            {
                case IErrorTypeSymbol error:
                    described["kind"] = "opaque";
                    described["text"] = error.Name is { Length: > 0 } name ? name : "?";
                    break;
                case ITypeParameterSymbol parameter:
                    described["kind"] = "parameter";
                    described["name"] = parameter.Name;
                    break;
                case IArrayTypeSymbol array:
                    described["kind"] = "array";
                    arguments = [array.ElementType];
                    break;
                case IPointerTypeSymbol pointer:
                    described["kind"] = "pointer";
                    arguments = [pointer.PointedAtType];
                    break;
                case IDynamicTypeSymbol:
                    described["kind"] = "keyword";
                    described["name"] = "dynamic";
                    break;
                case INamedTypeSymbol { IsTupleType: true } tuple when depth > 0:
                    described["kind"] = "tuple";
                    described["members"] = tuple.TupleElements.Select(element => Described(element.Type, depth - 1)).ToList();
                    break;
                case INamedTypeSymbol { IsTupleType: true }:
                    described["kind"] = "opaque";
                    break;
                case INamedTypeSymbol { SpecialType: not SpecialType.None } special when type.ToDisplayString() is { } keyword && SyntaxFacts.GetKeywordKind(keyword.TrimEnd('?')) != SyntaxKind.None:
                    described["kind"] = "keyword";
                    described["name"] = keyword.TrimEnd('?');
                    break;
                case INamedTypeSymbol named:
                    described["kind"] = "named";
                    described["name"] = named.ConstructedFrom.ToDisplayString(Readings.Qualified.WithGenericsOptions(SymbolDisplayGenericsOptions.None)).TrimEnd('?');
                    arguments = named.TypeArguments;
                    break;
                default:
                    described["kind"] = "opaque";
                    break;
            }

            if (depth > 0 && arguments.Any())
            {
                described["args"] = arguments.Select(argument => (object)Described(argument, depth - 1)).ToList();
                if (type is IArrayTypeSymbol or IPointerTypeSymbol)
                {
                    described["element"] = Described(arguments.First(), depth - 1);
                }
            }

            if (type.IsValueType)
            {
                described["nullable"] = type.OriginalDefinition.SpecialType == SpecialType.System_Nullable_T;
            }
            else if (type.NullableAnnotation != NullableAnnotation.None)
            {
                described["nullable"] = type.NullableAnnotation == NullableAnnotation.Annotated;
            }

            described["valueType"] = type.IsValueType;
            described["origin"] = "compiler";

            return described;
        }

        private TreeNode Binding(SyntaxToken identifier)
        {
            var node = New("binding", identifier).Fact("name", identifier.ValueText);
            var declared = identifier.Parent is { } declaration ? model.GetDeclaredSymbol(declaration) : null;
            if (declared is ILocalSymbol or IParameterSymbol or IRangeVariableSymbol)
            {
                bound[declared] = node;
            }

            return Typed(node, declared switch
            {
                // A `var` local is nullable-annotated whatever it holds: its type is its initializer's.
                ILocalSymbol local when identifier.Parent is VariableDeclaratorSyntax { Initializer: { } initial, Parent: VariableDeclarationSyntax { Type.IsVar: true } }
                    => model.GetTypeInfo(initial.Value).Type ?? local.Type,
                ILocalSymbol local => local.Type,
                IParameterSymbol parameter => parameter.Type,
                IFieldSymbol field => field.Type,
                IPropertySymbol property => property.Type,
                _ => null,
            });
        }

        private static string Spelled(ISymbol symbol) => symbol.OriginalDefinition.ToDisplayString(Readings.Declared);

        private string Remembered(INamedTypeSymbol type)
        {
            run.Remember(type);

            return type.OriginalDefinition.ToDisplayString(Readings.Qualified);
        }

        // The file --------------------------------------------------------------------------------------------------

        public TreeNode File(CompilationUnitSyntax unit)
        {
            var whole = bytes[text.Length];
            var root = new TreeNode("file", 0, whole, 1);
            var modules = new List<TreeNode>();

            if (unit.Members.OfType<FileScopedNamespaceDeclarationSyntax>().FirstOrDefault() is { } scoped)
            {
                var module = new TreeNode("module", 0, whole, 1).Fact("name", scoped.Name.ToString());
                modules.Add(Module(module, unit.Usings.Concat(scoped.Usings), unit.Members.Where(member => member != scoped).Concat(scoped.Members)));
            }
            else if (!unit.Members.OfType<NamespaceDeclarationSyntax>().Any())
            {
                modules.Add(Module(new TreeNode("module", 0, whole, 1), unit.Usings, unit.Members));
            }
            else
            {
                var outside = unit.Members.Where(member => member is not NamespaceDeclarationSyntax).ToList();

                if (unit.Usings.Count > 0 || outside.Count > 0)
                {
                    var spans = unit.Usings.Select(directive => directive.Span).Concat(outside.Select(member => member.Span)).ToList();
                    var module = New("module", TextSpan.FromBounds(spans.Min(span => span.Start), spans.Max(span => span.End)));
                    modules.Add(Module(module, unit.Usings, outside));
                }

                foreach (var space in unit.Members.OfType<NamespaceDeclarationSyntax>())
                {
                    Namespaces(space, "", modules);
                }
            }

            return root.HoldsAll("modules", modules);
        }

        private void Namespaces(NamespaceDeclarationSyntax space, string outer, List<TreeNode> modules)
        {
            var name = outer + space.Name;
            modules.Add(Module(New("module", space).Fact("name", name), space.Usings, space.Members.Where(member => member is not NamespaceDeclarationSyntax)));

            foreach (var inner in space.Members.OfType<NamespaceDeclarationSyntax>())
            {
                Namespaces(inner, name + ".", modules);
            }
        }

        private TreeNode Module(TreeNode module, IEnumerable<UsingDirectiveSyntax> usings, IEnumerable<MemberDeclarationSyntax> members)
        {
            var written = members.ToList();
            module.HoldsAll("imports", usings.Select(Import));
            module.HoldsAll("declarations", written.Where(member => member is not GlobalStatementSyntax).SelectMany(Member));
            module.HoldsAll("statements", written.OfType<GlobalStatementSyntax>().Select(global => Statement(global.Statement)));

            return module;
        }

        private TreeNode Import(UsingDirectiveSyntax directive)
        {
            var written = directive.NamespaceOrType.ToString();
            var import = New("import", directive).Fact("source", written);

            if (directive.Alias is { } alias)
            {
                var target = model.GetDeclaredSymbol(directive) is IAliasSymbol { Target: not IErrorTypeSymbol } named ? named.Target.ToDisplayString(Readings.Qualified) : null;
                var binding = New("import-binding", TextSpan.FromBounds(alias.SpanStart, directive.NamespaceOrType.Span.End))
                    .Fact("name", written)
                    .Fact("refers", target)
                    .Holds("alias", Binding(alias.Name.Identifier));

                return import.Fact("form", "names").HoldsAll("bindings", [binding]);
            }

            return import.Fact("form", "wildcard").Fact("refers", Refers(directive.NamespaceOrType));
        }

        // Declarations ----------------------------------------------------------------------------------------------

        private IEnumerable<TreeNode> Member(MemberDeclarationSyntax member) => member switch
        {
            BaseTypeDeclarationSyntax type => [Type(type)],
            MethodDeclarationSyntax method => [Function(method, "method", method.Identifier.ValueText, method.TypeParameterList, method.ParameterList, method.ReturnType, method.Body, method.ExpressionBody, method.Modifiers, method.AttributeLists)],
            ConstructorDeclarationSyntax constructor => [Function(constructor, "constructor", constructor.Identifier.ValueText, null, constructor.ParameterList, null, constructor.Body, constructor.ExpressionBody, constructor.Modifiers, constructor.AttributeLists)],
            DestructorDeclarationSyntax destructor => [Function(destructor, "method", "~" + destructor.Identifier.ValueText, null, destructor.ParameterList, null, destructor.Body, destructor.ExpressionBody, destructor.Modifiers, destructor.AttributeLists)],
            OperatorDeclarationSyntax @operator => [Function(@operator, "method", "operator " + @operator.OperatorToken.Text, null, @operator.ParameterList, @operator.ReturnType, @operator.Body, @operator.ExpressionBody, @operator.Modifiers, @operator.AttributeLists)],
            ConversionOperatorDeclarationSyntax conversion => [Function(conversion, "method", "operator " + conversion.Type, null, conversion.ParameterList, conversion.Type, conversion.Body, conversion.ExpressionBody, conversion.Modifiers, conversion.AttributeLists)],
            BaseFieldDeclarationSyntax field => [Field(field)],
            BasePropertyDeclarationSyntax property => Property(property),
            EnumMemberDeclarationSyntax @case => [EnumCase(@case)],
            GlobalStatementSyntax global => [Statement(global.Statement)],
            _ => [Unsupported(member, member.Kind().ToString())],
        };

        private TreeNode Type(BaseTypeDeclarationSyntax type)
        {
            var form = type switch
            {
                InterfaceDeclarationSyntax => "interface",
                StructDeclarationSyntax => "struct",
                EnumDeclarationSyntax => "enum",
                RecordDeclarationSyntax => "record",
                _ => "class",
            };
            var declared = model.GetDeclaredSymbol(type);
            var node = New("type", type)
                .Fact("name", type.Identifier.ValueText)
                .Fact("symbol", declared is null ? null : Spelled(declared))
                .Fact("form", form)
                .Fact("modifiers", Modifiers(type.Modifiers))
                .HoldsAll("attributes", Attributes(type.AttributeLists));
            var bases = new List<TreeNode>();
            var interfaces = new List<TreeNode>();

            foreach (var written in type.BaseList?.Types ?? default)
            {
                var implemented = type is not InterfaceDeclarationSyntax && model.GetTypeInfo(written.Type).Type is { TypeKind: TypeKind.Interface };
                (implemented ? interfaces : bases).Add(TypeExpression(written.Type));
            }

            node.HoldsAll("bases", bases).HoldsAll("interfaces", interfaces);
            var members = new List<TreeNode>();

            if (type is TypeDeclarationSyntax declaration)
            {
                node.HoldsAll("typeParameters", TypeParameters(declaration.TypeParameterList, declaration.ConstraintClauses));

                if (declaration.ParameterList is { } primary)
                {
                    var parameters = primary.Parameters.Select(Parameter).ToList();
                    members.AddRange(parameters);

                    if (type is RecordDeclarationSyntax)
                    {
                        node.Link("implicitMembers", parameters);
                    }
                }

                members.AddRange(declaration.Members.SelectMany(Member));
            }

            if (type is EnumDeclarationSyntax @enum)
            {
                members.AddRange(@enum.Members.Select(EnumCase));
            }

            return node.HoldsAll("writtenMembers", members);
        }

        private TreeNode EnumCase(EnumMemberDeclarationSyntax @case)
        {
            var declared = model.GetDeclaredSymbol(@case);

            return New("enum-case", @case)
                .Fact("name", @case.Identifier.ValueText)
                .Fact("symbol", declared is null ? null : Spelled(declared))
                .HoldsAll("attributes", Attributes(@case.AttributeLists))
                .Holds("value", @case.EqualsValue is null ? null : Expression(@case.EqualsValue.Value));
        }

        private TreeNode Field(BaseFieldDeclarationSyntax field)
        {
            var constant = field.Modifiers.Any(SyntaxKind.ConstKeyword);

            return New(constant ? "constant" : "field", field)
                .Fact("modifiers", Modifiers(field.Modifiers))
                .HoldsAll("attributes", Attributes(field.AttributeLists))
                .Holds("declaredType", TypeExpression(field.Declaration.Type))
                .HoldsAll("declarators", field.Declaration.Variables.Select(Declarator));
        }

        /// <summary>A property as a field, and each accessor written with a body as a function the field links in `hooks`.</summary>
        private IEnumerable<TreeNode> Property(BasePropertyDeclarationSyntax property)
        {
            var (identifier, initializer, arrow) = property switch
            {
                PropertyDeclarationSyntax written => (written.Identifier, written.Initializer?.Value, written.ExpressionBody),
                EventDeclarationSyntax @event => (@event.Identifier, null, null),
                IndexerDeclarationSyntax indexer => (indexer.ThisKeyword, null, indexer.ExpressionBody),
                _ => (default(SyntaxToken), null, null),
            };
            var declared = model.GetDeclaredSymbol(property);
            var declarator = New("declarator", TextSpan.FromBounds(identifier.SpanStart, initializer?.Span.End ?? identifier.Span.End))
                .Holds("binding", Binding(identifier).Fact("symbol", declared is null ? null : Spelled(declared)))
                .Holds("initializer", initializer is null ? null : Expression(initializer));
            var field = New("field", property)
                .Fact("modifiers", Modifiers(property.Modifiers))
                .HoldsAll("attributes", Attributes(property.AttributeLists))
                .Holds("declaredType", TypeExpression(property.Type))
                .HoldsAll("declarators", [declarator]);
            var hooks = new List<TreeNode>();

            if (arrow is not null)
            {
                var getter = declared is IPropertySymbol { GetMethod: { } get } ? get : null;
                hooks.Add(New("function", arrow)
                    .Fact("name", identifier.ValueText)
                    .Fact("symbol", getter is null ? null : Spelled(getter))
                    .Fact("form", "get")
                    .Fact("modifiers", Modifiers(property.Modifiers))
                    .Fact("obligations", Obligations(getter))
                    .Holds("body", Expression(arrow.Expression)));
            }

            foreach (var accessor in property.AccessorList?.Accessors ?? default)
            {
                if (accessor.Body is null && accessor.ExpressionBody is null)
                {
                    continue;
                }

                var form = accessor.Keyword.Kind() is SyntaxKind.GetKeyword ? "get" : accessor.Keyword.Kind() is SyntaxKind.SetKeyword or SyntaxKind.InitKeyword ? "set" : "method";
                hooks.Add(Function(accessor, form, identifier.ValueText, null, null, null, accessor.Body, accessor.ExpressionBody, accessor.Modifiers, accessor.AttributeLists));
            }

            if (hooks.Count > 0)
            {
                field.Link("hooks", hooks);
            }

            return [field, .. hooks];
        }

        private TreeNode Declarator(VariableDeclaratorSyntax variable)
        {
            var declared = model.GetDeclaredSymbol(variable);

            return New("declarator", variable)
                .Holds("binding", Binding(variable.Identifier).Fact("symbol", declared is IFieldSymbol or IEventSymbol ? Spelled(declared) : null))
                .Holds("initializer", variable.Initializer is null ? null : Expression(variable.Initializer.Value));
        }

        private TreeNode Function(SyntaxNode node, string form, string? name, TypeParameterListSyntax? typeParameters, BaseParameterListSyntax? parameters, TypeSyntax? returnType, BlockSyntax? body, ArrowExpressionClauseSyntax? arrow, SyntaxTokenList modifiers, SyntaxList<AttributeListSyntax> attributes)
        {
            var incomplete = IsIncomplete(node);
            var declared = incomplete ? null : model.GetDeclaredSymbol(node);
            var symbol = declared switch
            {
                null => null,
                IMethodSymbol { MethodKind: MethodKind.LocalFunction } local => Spelled(local.ContainingSymbol) + "." + local.Name,
                _ => Spelled(declared),
            };

            return New("function", node)
                .Fact("name", name)
                .Fact("symbol", symbol)
                .Fact("form", form)
                .Fact("modifiers", Modifiers(modifiers))
                .Fact("async", modifiers.Any(SyntaxKind.AsyncKeyword))
                .Fact("generator", Yields(node))
                .Fact("obligations", Obligations(declared))
                .Fact("incomplete", incomplete)
                .HoldsAll("attributes", Attributes(attributes))
                .HoldsAll("typeParameters", TypeParameters(typeParameters, node switch
                {
                    MethodDeclarationSyntax method => method.ConstraintClauses,
                    LocalFunctionStatementSyntax local => local.ConstraintClauses,
                    _ => default,
                }))
                .HoldsAll("parameters", parameters?.Parameters.Select(Parameter) ?? [])
                .Holds("returnType", returnType is null ? null : TypeExpression(returnType))
                .Holds("body", FunctionBody(node, body, arrow));
        }

        private TreeNode? FunctionBody(SyntaxNode node, BlockSyntax? body, ArrowExpressionClauseSyntax? arrow) => (node, body, arrow) switch
        {
            (ConstructorDeclarationSyntax { Initializer: { } initializer }, _, _) => ConstructorBody(initializer, body, arrow),
            (_, not null, _) => Block(body),
            (_, _, { Expression.IsMissing: false }) => Expression(arrow.Expression),
            _ => null,
        };

        /// <summary>
        /// A constructor's body with its <c>: base(...)</c> or <c>: this(...)</c> call as the first statement, so what that
        /// call passes is read like any other call's arguments.
        /// </summary>
        private TreeNode ConstructorBody(ConstructorInitializerSyntax initializer, BlockSyntax? body, ArrowExpressionClauseSyntax? arrow)
        {
            var keyword = initializer.ThisOrBaseKeyword;
            var call = Target(New("call", initializer), initializer)
                .Holds("callee", New("reference", keyword).Fact("name", keyword.ValueText).Fact("form", keyword.IsKind(SyntaxKind.BaseKeyword) ? "parent" : "self"))
                .HoldsAll("arguments", initializer.ArgumentList.Arguments.Select(Argument));
            List<TreeNode> statements = [New("expression-statement", initializer).Holds("expression", call)];

            if (body is not null)
            {
                statements.AddRange(body.Statements.Select(Statement));
            }
            else if (arrow is { Expression.IsMissing: false })
            {
                statements.Add(New("expression-statement", arrow).Holds("expression", Expression(arrow.Expression)));
            }

            SyntaxNode last = (SyntaxNode?)body ?? (SyntaxNode?)arrow ?? initializer;

            return New("block", TextSpan.FromBounds(initializer.SpanStart, last.Span.End)).HoldsAll("statements", statements);
        }

        /// <summary>
        /// Whether the parser recovered the declaration from an error in its own header: a token it had to invent, or
        /// an error outside its body. An error inside a statement of its body leaves the declaration whole.
        /// </summary>
        private static bool IsIncomplete(SyntaxNode node) =>
            node.ChildTokens().Any(token => token.IsMissing)
            || node.ChildNodes().Any(child => child is not (BlockSyntax or StatementSyntax or MemberDeclarationSyntax or AccessorListSyntax) && child.ContainsDiagnostics);

        /// <summary>Whether the function's own body yields, not counting a function nested in it.</summary>
        private static bool Yields(SyntaxNode function) => function
            .DescendantNodes(descendant => descendant == function || descendant is not (LocalFunctionStatementSyntax or AnonymousFunctionExpressionSyntax))
            .OfType<YieldStatementSyntax>()
            .Any();

        /// <summary>Every declaration the member overrides or implements, and whether its signature is fixed outside the project.</summary>
        private static List<Dictionary<string, object>> Obligations(ISymbol? member) => member is null
            ? []
            : Readings.Filled(member).Select(filled => new Dictionary<string, object>
            {
                ["symbol"] = Spelled(filled),
                ["origin"] = filled.Locations.Any(location => location.IsInSource) ? "project" : "external",
            }).ToList();

        /// <summary>The modifiers as written; `async` and `const` are said by the node's own kind and facts.</summary>
        private static List<string> Modifiers(SyntaxTokenList modifiers) => modifiers
            .Where(modifier => modifier.Kind() is not (SyntaxKind.AsyncKeyword or SyntaxKind.ConstKeyword))
            .Select(modifier => modifier.Text)
            .ToList();

        private string? PositionalProperty(ParameterSyntax parameter) =>
            parameter.Parent?.Parent is RecordDeclarationSyntax && model.GetDeclaredSymbol(parameter) is IParameterSymbol declared
                && declared.ContainingType?.GetMembers(declared.Name).OfType<IPropertySymbol>().FirstOrDefault() is { } property
                ? Spelled(property)
                : null;

        private TreeNode Parameter(ParameterSyntax parameter)
        {
            var modifiers = parameter.Modifiers;
            var passing = modifiers.Any(SyntaxKind.RefKeyword) ? "reference" : modifiers.Any(SyntaxKind.OutKeyword) ? "out" : modifiers.Any(SyntaxKind.InKeyword) ? "in" : null;

            return New("parameter", parameter)
                .Fact("passing", passing)
                .Fact("collects", modifiers.Any(SyntaxKind.ParamsKeyword) ? "rest" : null)
                .HoldsAll("attributes", Attributes(parameter.AttributeLists))
                .Holds("declaredType", parameter.Type is null ? null : TypeExpression(parameter.Type))
                .Holds("binding", Binding(parameter.Identifier).Fact("symbol", PositionalProperty(parameter)))
                .Holds("default", parameter.Default is null ? null : Expression(parameter.Default.Value));
        }

        /// <summary>Type parameters, each with the types its `where` clause constrains it to: one type, or their intersection.</summary>
        private IEnumerable<TreeNode> TypeParameters(TypeParameterListSyntax? parameters, SyntaxList<TypeParameterConstraintClauseSyntax> clauses) =>
            parameters?.Parameters.Select(parameter =>
            {
                var clause = clauses.FirstOrDefault(each => each.Name.Identifier.ValueText == parameter.Identifier.ValueText);
                var constrained = clause?.Constraints.OfType<TypeConstraintSyntax>().ToList() ?? [];
                var constraint = constrained.Count switch
                {
                    0 => null,
                    1 => TypeExpression(constrained[0].Type),
                    _ => New("type-expression", constrained[0].Parent!).Fact("form", "intersection").HoldsAll("members", constrained.Select(each => TypeExpression(each.Type))),
                };

                // A constraint is written in the `where` clause after the parameter list, so a constrained type parameter
                // spans to the end of its clause and holds its constraint inside it.
                var span = clause is null ? parameter.Span : TextSpan.FromBounds(parameter.SpanStart, clause.Span.End);

                return New("type-parameter", span).Fact("name", parameter.Identifier.ValueText).Holds("constraint", constraint);
            }) ?? [];

        private IEnumerable<TreeNode> Attributes(SyntaxList<AttributeListSyntax> lists) => lists.SelectMany(list => list.Attributes).Select(attribute =>
        {
            var constructor = model.GetSymbolInfo(attribute).Symbol as IMethodSymbol;

            return New("annotation", attribute)
                .Fact("name", attribute.Name.ToString())
                .Fact("refers", constructor is null ? null : Remembered(constructor.ContainingType))
                .Holds("callee", Expression(attribute.Name))
                .HoldsAll("arguments", attribute.ArgumentList?.Arguments.Select(argument => New("argument", argument)
                    .Fact("label", argument.NameEquals?.Name.Identifier.ValueText ?? argument.NameColon?.Name.Identifier.ValueText)
                    .Holds("value", Expression(argument.Expression))) ?? []);
        });

        // Statements ------------------------------------------------------------------------------------------------

        private TreeNode Block(BlockSyntax block) => New("block", block).HoldsAll("statements", block.Statements.Select(Statement));

        private TreeNode Statement(StatementSyntax statement) => statement switch
        {
            BlockSyntax block => Block(block),
            LocalDeclarationStatementSyntax local => Variable(local, local.Declaration, local.UsingKeyword.IsKind(SyntaxKind.UsingKeyword) ? "using" : local.IsConst ? "const" : null),
            ExpressionStatementSyntax expression => New("expression-statement", expression).Holds("expression", Expression(expression.Expression)),
            IfStatementSyntax @if => New("if", @if)
                .Holds("condition", Expression(@if.Condition))
                .Holds("then", Statement(@if.Statement))
                .Holds("else", @if.Else is null ? null : Statement(@if.Else.Statement)),
            ForStatementSyntax loop => For(loop),
            ForEachStatementSyntax loop => New("loop", loop)
                .Fact("form", "for-each")
                .Fact("async", loop.AwaitKeyword.IsKind(SyntaxKind.AwaitKeyword))
                .HoldsAll("bindings", [Binding(loop.Identifier)])
                .Holds("iterable", Expression(loop.Expression))
                .Holds("body", Statement(loop.Statement)),
            ForEachVariableStatementSyntax loop => New("loop", loop)
                .Fact("form", "for-each")
                .Fact("async", loop.AwaitKeyword.IsKind(SyntaxKind.AwaitKeyword))
                .HoldsAll("bindings", Bound(loop.Variable))
                .Holds("iterable", Expression(loop.Expression))
                .Holds("body", Statement(loop.Statement)),
            WhileStatementSyntax loop => New("loop", loop).Fact("form", "while").Holds("condition", Expression(loop.Condition)).Holds("body", Statement(loop.Statement)),
            DoStatementSyntax loop => New("loop", loop).Fact("form", "do-while").Holds("body", Statement(loop.Statement)).Holds("condition", Expression(loop.Condition)),
            ReturnStatementSyntax @return => New("return", @return).HoldsAll("values", @return.Expression is null ? [] : [Expression(@return.Expression)]),
            ThrowStatementSyntax @throw => New("throw", @throw).Holds("value", @throw.Expression is null ? null : Expression(@throw.Expression)),
            BreakStatementSyntax @break => New("break", @break),
            ContinueStatementSyntax @continue => New("continue", @continue),
            TryStatementSyntax @try => New("try", @try)
                .Holds("body", Block(@try.Block))
                .HoldsAll("handlers", @try.Catches.Select(Handler))
                .Holds("finally", @try.Finally is null ? null : Block(@try.Finally.Block)),
            SwitchStatementSyntax @switch => Switch(@switch),
            UsingStatementSyntax @using => New("resource-scope", @using)
                .Fact("form", "using")
                .Fact("async", @using.AwaitKeyword.IsKind(SyntaxKind.AwaitKeyword))
                .HoldsAll("resources", @using.Declaration is not null ? @using.Declaration.Variables.Select(Declarator) : @using.Expression is not null ? [Expression(@using.Expression)] : [])
                .Holds("body", Statement(@using.Statement)),
            LockStatementSyntax @lock => New("resource-scope", @lock).Fact("form", "lock").HoldsAll("resources", [Expression(@lock.Expression)]).Holds("body", Statement(@lock.Statement)),
            LocalFunctionStatementSyntax local => Function(local, "local", local.Identifier.ValueText, local.TypeParameterList, local.ParameterList, local.ReturnType, local.Body, local.ExpressionBody, local.Modifiers, local.AttributeLists),
            YieldStatementSyntax { RawKind: (int)SyntaxKind.YieldBreakStatement } yield => New("return", yield),
            YieldStatementSyntax yield => New("expression-statement", yield).Holds("expression", New("yield", TextSpan.FromBounds(yield.YieldKeyword.SpanStart, yield.Expression!.Span.End)).Holds("value", Expression(yield.Expression))),
            EmptyStatementSyntax empty => New("no-op", empty),
            LabeledStatementSyntax label => New("label", label).Holds("binding", Binding(label.Identifier)).Holds("statement", Statement(label.Statement)),
            GotoStatementSyntax jump => New("goto", jump).Holds("destination", jump.Expression is null ? null : Expression(jump.Expression)),
            CheckedStatementSyntax @checked => Block(@checked.Block),
            UnsafeStatementSyntax @unsafe => Block(@unsafe.Block),
            _ => Unsupported(statement, statement.Kind().ToString()),
        };

        private TreeNode Variable(SyntaxNode node, VariableDeclarationSyntax declaration, string? form) => New("variable", node)
            .Fact("form", form ?? (declaration.Type.IsVar ? "var" : "typed"))
            .Holds("declaredType", declaration.Type.IsVar ? null : TypeExpression(declaration.Type))
            .HoldsAll("declarators", declaration.Variables.Select(Declarator));

        private TreeNode For(ForStatementSyntax loop)
        {
            var initializer = new List<TreeNode>();

            if (loop.Declaration is not null)
            {
                initializer.Add(Variable(loop.Declaration, loop.Declaration, null));
            }

            initializer.AddRange(loop.Initializers.Select(Expression));

            return New("loop", loop)
                .Fact("form", "for")
                .HoldsAll("initializer", initializer)
                .Holds("condition", loop.Condition is null ? null : Expression(loop.Condition))
                .HoldsAll("steps", loop.Incrementors.Select(Expression))
                .Holds("body", Statement(loop.Statement));
        }

        private TreeNode Handler(CatchClauseSyntax handler)
        {
            var node = New("handler", handler).Fact("catchForm", handler.Declaration is null ? "catch-all" : "typed");

            if (handler.Declaration is { } declaration)
            {
                node.HoldsAll("caughtTypes", [TypeExpression(declaration.Type)]);
                node.Holds("binding", declaration.Identifier.IsKind(SyntaxKind.IdentifierToken) ? Binding(declaration.Identifier) : null);
            }

            return node.Holds("filter", handler.Filter is null ? null : Expression(handler.Filter.FilterExpression)).Holds("body", Block(handler.Block));
        }

        private TreeNode Switch(SwitchStatementSyntax @switch) => New("dispatch", @switch)
            .Fact("form", "switch")
            .Holds("subject", Expression(@switch.Expression))
            .HoldsAll("arms", @switch.Sections.Select(section =>
            {
                var patterns = new List<TreeNode>();
                TreeNode? guard = null;

                foreach (var label in section.Labels)
                {
                    switch (label)
                    {
                        case CaseSwitchLabelSyntax @case:
                            patterns.Add(@case.Value is TypeSyntax named && model.GetSymbolInfo(named).Symbol is ITypeSymbol
                                ? New("pattern", named).Fact("form", "type").Holds("constrainedType", TypeExpression(named))
                                : New("pattern", @case.Value).Fact("form", "value").Holds("value", Expression(@case.Value)));
                            break;
                        case CasePatternSwitchLabelSyntax @case:
                            patterns.Add(Pattern(@case.Pattern));
                            guard = @case.WhenClause is null ? guard : Expression(@case.WhenClause.Condition);
                            break;
                    }
                }

                var statements = section.Statements.Select(Statement).ToList();
                var body = statements switch
                {
                    [{ Kind: "block" } only] => only,
                    [] => New("block", new TextSpan(section.Span.End, 0)),
                    _ => new TreeNode("block", statements[0].Start, statements[^1].End, statements[0].Line).HoldsAll("statements", statements),
                };

                return New("arm", section)
                    .Fact("default", section.Labels.Any(label => label is DefaultSwitchLabelSyntax))
                    .HoldsAll("patterns", patterns)
                    .Holds("guard", guard)
                    .Holds("body", body);
            }));

        private TreeNode Unsupported(SyntaxNode node, string construct) => New("unsupported", node)
            .Fact("construct", construct)
            .HoldsAll("parts", node.ChildNodes().OfType<ExpressionSyntax>().Select(part => part is TypeSyntax type && IsTypeOrNamespace(part) ? TypeExpression(type) : Expression(part)));

        // Expressions -----------------------------------------------------------------------------------------------

        private TreeNode Expression(ExpressionSyntax expression)
        {
            var node = model.GetSymbolInfo(expression).Symbol is INamespaceOrTypeSymbol
                ? Translated(expression)
                : Typed(Translated(expression), expression is AnonymousFunctionExpressionSyntax
                    ? model.GetTypeInfo(expression).ConvertedType
                    : FlowTyped(model.GetTypeInfo(expression)));

            if (expression is IdentifierNameSyntax or MemberAccessExpressionSyntax && model.GetConstantValue(expression) is { HasValue: true } constant)
            {
                node.Fact("constantValue", constant.Value is null
                    ? new Dictionary<string, object> { ["type"] = "null" }
                    : new Dictionary<string, object>
                    {
                        ["type"] = model.GetTypeInfo(expression).Type?.ToDisplayString() ?? "",
                        ["value"] = constant.Value is bool yes ? (yes ? "true" : "false") : Convert.ToString(constant.Value, CultureInfo.InvariantCulture) ?? "",
                    });
            }

            return node;
        }

        private TreeNode Translated(ExpressionSyntax expression) => expression switch
        {
            ParenthesizedExpressionSyntax parenthesized => Expression(parenthesized.Expression),
            IdentifierNameSyntax identifier => Reference(identifier, identifier.Identifier.ValueText),
            GenericNameSyntax generic => Reference(generic, generic.Identifier.ValueText),
            AliasQualifiedNameSyntax qualified => Reference(qualified, qualified.Name.Identifier.ValueText),
            ThisExpressionSyntax self => New("reference", self).Fact("name", "this").Fact("form", "self"),
            BaseExpressionSyntax parent => New("reference", parent).Fact("name", "base").Fact("form", "parent"),
            PredefinedTypeSyntax keyword => New("reference", keyword).Fact("name", keyword.Keyword.ValueText).Fact("form", "name"),
            QualifiedNameSyntax qualified => Access(qualified, qualified.Left, qualified.Right, "static", false),
            MemberAccessExpressionSyntax access when IsPresenceTest(access) =>
                Comparison(access, "not-identical", Expression(access.Expression), New("literal", access.Name).Fact("literalKind", "null")),
            MemberAccessExpressionSyntax access => Access(access, access.Expression, access.Name, IsTypeOrNamespace(access.Expression) ? "static" : "instance", false),
            ConditionalAccessExpressionSyntax conditional => Conditional(conditional),
            MemberBindingExpressionSyntax binding => New("access", binding)
                .Fact("form", "instance")
                .Fact("nullConditional", true)
                .Holds("subject", receivers.Peek().Receiver)
                .Holds("member", Reference(binding.Name, binding.Name.Identifier.ValueText)),
            ElementBindingExpressionSyntax binding => New("index", binding)
                .Fact("nullConditional", true)
                .Holds("subject", receivers.Peek().Receiver)
                .Holds("key", binding.ArgumentList.Arguments.Count > 0 ? Expression(binding.ArgumentList.Arguments[0].Expression) : null),
            InvocationExpressionSyntax call => Target(New("call", call), call)
                .Holds("callee", Expression(call.Expression))
                .HoldsAll("typeArguments", (call.Expression switch
                {
                    GenericNameSyntax generic => generic,
                    MemberAccessExpressionSyntax { Name: GenericNameSyntax member } => member,
                    MemberBindingExpressionSyntax { Name: GenericNameSyntax bound } => bound,
                    _ => null,
                })?.TypeArgumentList.Arguments.Select(TypeExpression) ?? [])
                .HoldsAll("arguments", call.ArgumentList.Arguments.Select(Argument)),
            ObjectCreationExpressionSyntax creation => Construction(creation, creation.Type, creation.ArgumentList, creation.Initializer),
            ImplicitObjectCreationExpressionSyntax creation => Construction(creation, null, creation.ArgumentList, creation.Initializer),
            WithExpressionSyntax copy => New("construction", copy).HoldsAll("arguments", new[] { New("argument", copy.Expression).Fact("spread", "keyed").Holds("value", Expression(copy.Expression)) }
                .Concat(copy.Initializer.Expressions.OfType<AssignmentExpressionSyntax>().Where(each => each.Left is IdentifierNameSyntax)
                    .Select(each => New("argument", each).Fact("label", ((IdentifierNameSyntax)each.Left).Identifier.ValueText).Holds("value", Expression(each.Right))))),
            AnonymousObjectCreationExpressionSyntax anonymous => New("collection", anonymous).Fact("form", "object").HoldsAll("entries", anonymous.Initializers.Select(member => New("entry", member)
                .Holds("key", member.NameEquals is null ? null : Reference(member.NameEquals.Name, member.NameEquals.Name.Identifier.ValueText))
                .Holds("value", Expression(member.Expression)))),
            ArrayCreationExpressionSyntax array when array.Initializer is not null => Collection(array, "array", array.Initializer.Expressions),
            ArrayCreationExpressionSyntax array => New("construction", array).Holds("type", TypeExpression(array.Type)),
            ImplicitArrayCreationExpressionSyntax array => Collection(array, "array", array.Initializer.Expressions),
            InitializerExpressionSyntax initializer => Collection(initializer, initializer.IsKind(SyntaxKind.ObjectInitializerExpression) ? "object" : initializer.IsKind(SyntaxKind.CollectionInitializerExpression) ? "list" : "array", initializer.Expressions),
            CollectionExpressionSyntax collection => New("collection", collection).Fact("form", "list").HoldsAll("entries", collection.Elements.Select(element => element switch
            {
                SpreadElementSyntax spread => New("entry", spread).Fact("expansion", "positional").Holds("value", Expression(spread.Expression)),
                ExpressionElementSyntax held => New("entry", held).Holds("value", Expression(held.Expression)),
                _ => Unsupported(element, element.Kind().ToString()),
            })),
            TupleExpressionSyntax tuple => New("collection", tuple).Fact("form", "tuple").HoldsAll("entries", tuple.Arguments.Select(argument => New("entry", argument).Holds("value", Expression(argument.Expression)))),
            AssignmentExpressionSyntax assignment => Assignment(assignment),
            BinaryExpressionSyntax binary => Binary(binary),
            PrefixUnaryExpressionSyntax prefix => Prefix(prefix),
            PostfixUnaryExpressionSyntax { RawKind: (int)SyntaxKind.SuppressNullableWarningExpression } forgiven => New("unary", forgiven).Fact("operator", "assert-present").Holds("operand", Typed(Expression(forgiven.Operand), DeclaredType(forgiven.Operand))),
            PostfixUnaryExpressionSyntax postfix => New("assignment", postfix)
                .Fact("mode", postfix.IsKind(SyntaxKind.PostIncrementExpression) ? "increment" : "decrement")
                .HoldsAll("targets", [Expression(postfix.Operand)]),
            ConditionalExpressionSyntax conditional => New("conditional", conditional)
                .Holds("condition", Expression(conditional.Condition))
                .Holds("consequent", Expression(conditional.WhenTrue))
                .Holds("alternative", Expression(conditional.WhenFalse)),
            LiteralExpressionSyntax literal => Literal(literal),
            InterpolatedStringExpressionSyntax interpolated => New("interpolation", interpolated).HoldsAll("parts", interpolated.Contents.Select(part => part is InterpolatedStringTextSyntax written
                ? New("text", written).Said("value", written.TextToken.ValueText)
                : Expression(((InterpolationSyntax)part).Expression))),
            AnonymousFunctionExpressionSyntax function => Lambda(function),
            AwaitExpressionSyntax await => New("await", await).Holds("operand", Expression(await.Expression)),
            ThrowExpressionSyntax @throw => New("throw", @throw).Holds("value", Expression(@throw.Expression)),
            ElementAccessExpressionSyntax index => New("index", index)
                .Holds("subject", Expression(index.Expression))
                .Holds("key", index.ArgumentList.Arguments.Count > 0 ? Expression(index.ArgumentList.Arguments[0].Expression) : null),
            CastExpressionSyntax cast => New("cast", cast).Fact("form", "checked").Holds("operand", Expression(cast.Expression)).Holds("targetType", TypeExpression(cast.Type)),
            IsPatternExpressionSyntax test => IsPattern(test),
            SwitchExpressionSyntax @switch => New("dispatch", @switch)
                .Fact("form", "match")
                .Fact("exhaustive", !model.GetDiagnostics(@switch.Span).Any(diagnostic => diagnostic.Id is "CS8509" or "CS8524" or "CS8655") ? true : null)
                .Holds("subject", Expression(@switch.GoverningExpression))
                .HoldsAll("arms", @switch.Arms.Select(arm => New("arm", arm)
                    .Fact("default", arm.Pattern is DiscardPatternSyntax && arm.WhenClause is null)
                    .HoldsAll("patterns", arm.Pattern is DiscardPatternSyntax ? [] : [Pattern(arm.Pattern)])
                    .Holds("guard", arm.WhenClause is null ? null : Expression(arm.WhenClause.Condition))
                    .Holds("body", Expression(arm.Expression)))),
            DefaultExpressionSyntax @default => New("default-value", @default).Holds("writtenType", TypeExpression(@default.Type)),
            TypeOfExpressionSyntax typeOf => New("unary", typeOf).Fact("operator", "typeof").Holds("operand", TypeExpression(typeOf.Type)),
            DeclarationExpressionSyntax { Designation: SingleVariableDesignationSyntax single } => Binding(single.Identifier),
            TypeSyntax type => TypeExpression(type),
            _ => Unsupported(expression, expression.Kind().ToString()),
        };

        private bool IsTypeOrNamespace(ExpressionSyntax expression) =>
            expression is PredefinedTypeSyntax || model.GetSymbolInfo(expression).Symbol is INamedTypeSymbol or INamespaceSymbol;

        /// <summary>Whether <paramref name="access"/> is <c>x.HasValue</c> on a <c>Nullable&lt;T&gt;</c>, which the compiler reads as <c>x != null</c>.</summary>
        private bool IsPresenceTest(MemberAccessExpressionSyntax access) =>
            model.GetSymbolInfo(access).Symbol is IPropertySymbol { Name: nameof(Nullable<int>.HasValue), ContainingType.OriginalDefinition.SpecialType: SpecialType.System_Nullable_T };

        /// <summary>A name read or written, and the declaration it names: a type, or a field, property or event of the solution.</summary>
        private TreeNode Reference(SyntaxNode name, string written)
        {
            var info = model.GetSymbolInfo(name);
            var named = info.Symbol ?? (info.CandidateReason is CandidateReason.MemberGroup ? info.CandidateSymbols.FirstOrDefault() : null);
            var refers = named switch
            {
                INamedTypeSymbol type => Remembered(type),
                IFieldSymbol or IPropertySymbol or IEventSymbol or IMethodSymbol => Spelled(named),
                _ => null,
            };

            var reference = New("reference", name).Fact("name", written).Fact("form", "name").Fact("refers", refers);
            if (named is ILocalSymbol or IParameterSymbol or IRangeVariableSymbol && bound.TryGetValue(named, out var declared))
            {
                reference.Link("binding", declared);
            }

            return reference;
        }

        private TreeNode Access(ExpressionSyntax access, ExpressionSyntax subject, SimpleNameSyntax member, string form, bool nullConditional) => New("access", access)
            .Fact("form", form)
            .Fact("nullConditional", nullConditional)
            .Holds("subject", Expression(subject))
            .Holds("member", Reference(member, member.Identifier.ValueText));

        /// <summary>`a?.b`: the access or call after `?.` with `a` as its subject, spanning from `a`.</summary>
        private TreeNode Conditional(ConditionalAccessExpressionSyntax conditional)
        {
            receivers.Push((conditional.WhenNotNull.SpanStart, Expression(conditional.Expression)));

            try
            {
                return Expression(conditional.WhenNotNull);
            }
            finally
            {
                receivers.Pop();
            }
        }

        /// <summary>The declaration a call or construction binds to, as its original definition, and the types of the parameters it fills.</summary>
        private TreeNode Target(TreeNode node, SyntaxNode call)
        {
            run.calls++;

            if (model.GetSymbolInfo(call).Symbol is not IMethodSymbol method)
            {
                return node;
            }

            run.resolved++;
            var original = (method.ReducedFrom ?? method).OriginalDefinition;
            var target = new Dictionary<string, object>
            {
                ["symbol"] = Spelled(original),
                ["type"] = original.ContainingType.ToDisplayString(Readings.Qualified),
                ["name"] = original.Name,
            };

            if (method.Parameters.All(parameter => parameter.Type.ToDisplayString(Readings.Qualified).Length > 0))
            {
                target["parameters"] = method.Parameters.Select(parameter => parameter.Type.ToDisplayString(Readings.Qualified)).ToList();
            }

            run.Remember(original.ContainingType);

            return node.Fact("target", target);
        }

        private TreeNode Construction(ExpressionSyntax creation, TypeSyntax? type, ArgumentListSyntax? arguments, InitializerExpressionSyntax? initializer) => Target(New("construction", creation), creation)
            .Holds("type", type is null ? null : TypeExpression(type))
            .HoldsAll("arguments", arguments?.Arguments.Select(Argument) ?? [])
            .Holds("initializer", initializer is null ? null : Expression(initializer));

        private TreeNode Argument(ArgumentSyntax argument) => New("argument", argument)
            .Fact("label", argument.NameColon?.Name.Identifier.ValueText)
            .Fact("passing", argument.RefKindKeyword.Kind() switch
            {
                SyntaxKind.RefKeyword => "reference",
                SyntaxKind.OutKeyword => "out",
                SyntaxKind.InKeyword => "in",
                _ => null,
            })
            .Holds("value", Expression(argument.Expression));

        private TreeNode Collection(ExpressionSyntax node, string form, IEnumerable<ExpressionSyntax> elements) => New("collection", node)
            .Fact("form", form)
            .HoldsAll("entries", elements.Select(element => element switch
            {
                AssignmentExpressionSyntax { RawKind: (int)SyntaxKind.SimpleAssignmentExpression, Left: ImplicitElementAccessSyntax { ArgumentList.Arguments.Count: 1 } indexed } keyed when form == "object" => New("entry", keyed).Holds("key", Expression(indexed.ArgumentList.Arguments[0].Expression)).Holds("value", Expression(keyed.Right)),
                AssignmentExpressionSyntax { RawKind: (int)SyntaxKind.SimpleAssignmentExpression } named when form == "object" => New("entry", named).Holds("key", Expression(named.Left)).Holds("value", Expression(named.Right)),
                InitializerExpressionSyntax { Expressions.Count: 2 } pair when element.IsKind(SyntaxKind.ComplexElementInitializerExpression) => New("entry", pair).Holds("key", Expression(pair.Expressions[0])).Holds("value", Expression(pair.Expressions[1])),
                _ => New("entry", element).Holds("value", Expression(element)),
            }));

        private TreeNode Assignment(AssignmentExpressionSyntax assignment)
        {
            var mode = assignment.Kind() switch
            {
                SyntaxKind.SimpleAssignmentExpression => "assign",
                SyntaxKind.CoalesceAssignmentExpression => "fallback",
                _ => "compound",
            };

            return New("assignment", assignment)
                .Fact("mode", mode)
                .Fact("operator", mode == "compound" ? Operators.GetValueOrDefault(assignment.OperatorToken.Text[..^1]) : null)
                .HoldsAll("targets", [Expression(assignment.Left)])
                .HoldsAll("values", [Expression(assignment.Right)]);
        }

        private TreeNode Binary(BinaryExpressionSyntax binary)
        {
            if (Comparisons.TryGetValue(binary.Kind(), out var comparison))
            {
                return Comparison(binary, comparison, Expression(binary.Left), Expression(binary.Right));
            }

            return binary.Kind() switch
            {
                SyntaxKind.LogicalAndExpression or SyntaxKind.LogicalOrExpression => New("logical", binary)
                    .Fact("operator", binary.IsKind(SyntaxKind.LogicalAndExpression) ? "and" : "or")
                    .Holds("left", Expression(binary.Left))
                    .Holds("right", Expression(binary.Right)),
                SyntaxKind.CoalesceExpression => New("fallback", binary).Fact("trigger", "null").Holds("subject", Expression(binary.Left)).Holds("replacement", Expression(binary.Right)),
                SyntaxKind.IsExpression => New("type-test", binary).Fact("form", "is").Holds("subject", Expression(binary.Left)).Holds("testedType", TypeExpression((TypeSyntax)binary.Right)),
                SyntaxKind.AsExpression => New("cast", binary).Fact("form", "safe").Holds("operand", Expression(binary.Left)).Holds("targetType", TypeExpression((TypeSyntax)binary.Right)),
                _ => New("binary", binary)
                    .Fact("operator", Operators.GetValueOrDefault(binary.OperatorToken.Text))
                    .Holds("left", Expression(binary.Left))
                    .Holds("right", Expression(binary.Right)),
            };
        }

        private TreeNode Comparison(SyntaxNode node, string comparison, TreeNode left, TreeNode right) => New("comparison", node)
            .Fact("operators", new List<string> { comparison })
            .Fact("operator", comparison)
            .HoldsAll("operands", [left, right])
            .Link("left", left)
            .Link("right", right);

        private TreeNode Prefix(PrefixUnaryExpressionSyntax prefix) => prefix.Kind() switch
        {
            SyntaxKind.PreIncrementExpression or SyntaxKind.PreDecrementExpression => New("assignment", prefix)
                .Fact("mode", prefix.IsKind(SyntaxKind.PreIncrementExpression) ? "increment" : "decrement")
                .Fact("prefix", true)
                .HoldsAll("targets", [Expression(prefix.Operand)]),
            SyntaxKind.IndexExpression => Unsupported(prefix, "index-from-end"),
            var kind => New("unary", prefix)
                .Fact("operator", kind switch
                {
                    SyntaxKind.LogicalNotExpression => "not",
                    SyntaxKind.UnaryMinusExpression => "negate",
                    SyntaxKind.UnaryPlusExpression => "plus",
                    SyntaxKind.BitwiseNotExpression => "bit-not",
                    SyntaxKind.AddressOfExpression => "reference-of",
                    _ => "dereference",
                })
                .Holds("operand", Expression(prefix.Operand)),
        };

        private TreeNode Literal(LiteralExpressionSyntax literal) => literal.Kind() switch
        {
            SyntaxKind.StringLiteralExpression or SyntaxKind.Utf8StringLiteralExpression => New("literal", literal).Fact("literalKind", "string").Said("value", literal.Token.ValueText),
            SyntaxKind.CharacterLiteralExpression => New("literal", literal).Fact("literalKind", "character").Said("value", literal.Token.ValueText),
            SyntaxKind.NumericLiteralExpression => New("literal", literal)
                .Fact("literalKind", literal.Token.Value is double or float or decimal ? "float" : "integer")
                .Said("value", Convert.ToString(literal.Token.Value, CultureInfo.InvariantCulture) ?? literal.Token.Text),
            SyntaxKind.TrueLiteralExpression or SyntaxKind.FalseLiteralExpression => New("literal", literal).Fact("literalKind", "boolean").Said("value", literal.IsKind(SyntaxKind.TrueLiteralExpression)),
            SyntaxKind.NullLiteralExpression => New("literal", literal).Fact("literalKind", "null"),
            SyntaxKind.DefaultLiteralExpression => New("default-value", literal),
            _ => Unsupported(literal, literal.Kind().ToString()),
        };

        private TreeNode Lambda(AnonymousFunctionExpressionSyntax function)
        {
            var parameters = function switch
            {
                SimpleLambdaExpressionSyntax simple => [Parameter(simple.Parameter)],
                ParenthesizedLambdaExpressionSyntax parenthesized => parenthesized.ParameterList.Parameters.Select(Parameter).ToList(),
                AnonymousMethodExpressionSyntax method => method.ParameterList?.Parameters.Select(Parameter).ToList() ?? [],
                _ => new List<TreeNode>(),
            };

            return New("function", function)
                .Fact("form", "lambda")
                .Fact("async", function.AsyncKeyword.IsKind(SyntaxKind.AsyncKeyword))
                .HoldsAll("parameters", parameters)
                .Holds("returnType", function is ParenthesizedLambdaExpressionSyntax { ReturnType: { } returns } ? TypeExpression(returns) : null)
                .Holds("body", function.Block is not null ? Block(function.Block) : function.ExpressionBody is not null ? Expression(function.ExpressionBody) : null);
        }

        /// <summary>`x is null` and `x is not null` compare with null; `x is T` and `x is T t` test the type; any other pattern is not translated yet.</summary>
        private TreeNode IsPattern(IsPatternExpressionSyntax test)
        {
            var (pattern, negated) = test.Pattern is UnaryPatternSyntax { RawKind: (int)SyntaxKind.NotPattern } not ? (not.Pattern, true) : (test.Pattern, false);

            return pattern switch
            {
                ConstantPatternSyntax { Expression: LiteralExpressionSyntax { RawKind: (int)SyntaxKind.NullLiteralExpression } nothing } =>
                    Comparison(test, negated ? "not-identical" : "identical", Expression(test.Expression), Literal(nothing)),
                DeclarationPatternSyntax declared => New("type-test", test)
                    .Fact("form", "is")
                    .Fact("negated", negated)
                    .Holds("subject", Expression(test.Expression))
                    .Holds("testedType", TypeExpression(declared.Type))
                    .Holds("binding", declared.Designation is SingleVariableDesignationSyntax single ? Binding(single.Identifier) : null),
                TypePatternSyntax typed => New("type-test", test).Fact("form", "is").Fact("negated", negated).Holds("subject", Expression(test.Expression)).Holds("testedType", TypeExpression(typed.Type)),
                ConstantPatternSyntax { Expression: TypeSyntax named } when model.GetSymbolInfo(named).Symbol is ITypeSymbol =>
                    New("type-test", test).Fact("form", "is").Fact("negated", negated).Holds("subject", Expression(test.Expression)).Holds("testedType", TypeExpression(named)),
                _ when OfConstants(pattern) => ConstantTest(test, pattern, negated),
                _ => Unsupported(test, "is-pattern"),
            };
        }

        private bool OfConstants(PatternSyntax pattern) => pattern switch
        {
            ParenthesizedPatternSyntax parenthesized => OfConstants(parenthesized.Pattern),
            ConstantPatternSyntax { Expression: TypeSyntax named } when model.GetSymbolInfo(named).Symbol is ITypeSymbol => false,
            ConstantPatternSyntax => true,
            BinaryPatternSyntax binary => OfConstants(binary.Left) && OfConstants(binary.Right),
            _ => false,
        };

        private TreeNode ConstantTest(IsPatternExpressionSyntax test, PatternSyntax pattern, bool negated) => pattern switch
        {
            ParenthesizedPatternSyntax parenthesized => ConstantTest(test, parenthesized.Pattern, negated),
            ConstantPatternSyntax constant => Comparison(test, negated ? "not-equal" : "equal", Expression(test.Expression), Expression(constant.Expression)),
            BinaryPatternSyntax binary => New("logical", test)
                .Fact("operator", binary.IsKind(SyntaxKind.OrPattern) != negated ? "or" : "and")
                .Holds("left", ConstantTest(test, binary.Left, negated))
                .Holds("right", ConstantTest(test, binary.Right, negated)),
            _ => Unsupported(test, "is-pattern"),
        };

        // Patterns --------------------------------------------------------------------------------------------------

        private TreeNode Pattern(PatternSyntax pattern) => pattern switch
        {
            ParenthesizedPatternSyntax parenthesized => Pattern(parenthesized.Pattern),
            ConstantPatternSyntax { Expression: TypeSyntax named } typed when model.GetSymbolInfo(named).Symbol is ITypeSymbol =>
                New("pattern", typed).Fact("form", "type").Holds("constrainedType", TypeExpression(named)),
            ConstantPatternSyntax constant => New("pattern", constant).Fact("form", "value").Holds("value", Expression(constant.Expression)),
            DiscardPatternSyntax discard => New("pattern", discard).Fact("form", "wildcard"),
            DeclarationPatternSyntax declared => New("pattern", declared).Fact("form", "type").Holds("constrainedType", TypeExpression(declared.Type)).HoldsAll("bindings", Bound(declared.Designation)),
            TypePatternSyntax typed => New("pattern", typed).Fact("form", "type").Holds("constrainedType", TypeExpression(typed.Type)),
            VarPatternSyntax var => New("pattern", var).Fact("form", "binding").HoldsAll("bindings", Bound(var.Designation)),
            RecursivePatternSyntax record => New("pattern", record)
                .Fact("form", "record")
                .Holds("constrainedType", record.Type is null ? null : TypeExpression(record.Type))
                .HoldsAll("subpatterns", (record.PositionalPatternClause?.Subpatterns ?? default).Concat(record.PropertyPatternClause?.Subpatterns ?? default).Select(sub => Pattern(sub.Pattern)))
                .HoldsAll("bindings", record.Designation is null ? [] : Bound(record.Designation)),
            RelationalPatternSyntax relational => New("pattern", relational)
                .Fact("form", "relational")
                .Fact("operator", relational.OperatorToken.Text switch { "<" => "less", "<=" => "less-or-equal", ">" => "greater", _ => "greater-or-equal" })
                .Holds("value", Expression(relational.Expression)),
            BinaryPatternSyntax binary => New("pattern", binary).Fact("form", binary.IsKind(SyntaxKind.AndPattern) ? "and" : "or").HoldsAll("subpatterns", [Pattern(binary.Left), Pattern(binary.Right)]),
            UnaryPatternSyntax unary => New("pattern", unary).Fact("form", "not").HoldsAll("subpatterns", [Pattern(unary.Pattern)]),
            ListPatternSyntax list => New("pattern", list).Fact("form", "sequence").HoldsAll("subpatterns", list.Patterns.Select(Pattern)).HoldsAll("bindings", list.Designation is null ? [] : Bound(list.Designation)),
            SlicePatternSyntax slice => New("pattern", slice).Fact("form", "rest").HoldsAll("subpatterns", slice.Pattern is null ? [] : [Pattern(slice.Pattern)]),
            _ => New("pattern", pattern).Fact("form", "value"),
        };

        /// <summary>The names a designation or a deconstruction binds, in order.</summary>
        private List<TreeNode> Bound(SyntaxNode designation) => designation switch
        {
            SingleVariableDesignationSyntax single => [Binding(single.Identifier)],
            ParenthesizedVariableDesignationSyntax several => several.Variables.SelectMany(Bound).ToList(),
            DeclarationExpressionSyntax declared => Bound(declared.Designation),
            TupleExpressionSyntax tuple => tuple.Arguments.SelectMany(argument => Bound(argument.Expression)).ToList(),
            IdentifierNameSyntax name => [New("binding", name).Fact("name", name.Identifier.ValueText)],
            _ => [],
        };

        // Types as written ------------------------------------------------------------------------------------------

        private ITypeSymbol? DeclaredType(ExpressionSyntax operand)
        {
            var written = operand;
            while (written is ParenthesizedExpressionSyntax parenthesized)
            {
                written = parenthesized.Expression;
            }

            return (model.GetSymbolInfo(written).Symbol switch
            {
                IFieldSymbol field => field.Type,
                IPropertySymbol property => property.Type,
                IParameterSymbol parameter => parameter.Type,
                ILocalSymbol local => local.DeclaringSyntaxReferences.FirstOrDefault()?.GetSyntax() switch
                {
                    VariableDeclaratorSyntax { Initializer: { } initializer, Parent: VariableDeclarationSyntax { Type.IsVar: true } } => DeclaredType(initializer.Value),
                    VariableDeclaratorSyntax { Parent: VariableDeclarationSyntax { Type.IsVar: false } } => local.Type,
                    _ => model.GetTypeInfo(written) is { Type: { TypeKind: not TypeKind.Error } held, Nullability.FlowState: var state }
                        ? held.WithNullableAnnotation(state == NullableFlowState.MaybeNull ? NullableAnnotation.Annotated : NullableAnnotation.NotAnnotated)
                        : local.Type,
                },
                IMethodSymbol method => method.ReturnType,
                _ => null,
            }) ?? model.GetTypeInfo(written).Type;
        }

        private static ITypeSymbol? FlowTyped(TypeInfo info) => info.Type is { IsReferenceType: true, TypeKind: not TypeKind.Error } held && info.Nullability.Annotation != NullableAnnotation.None
            ? held.WithNullableAnnotation(info.Nullability.Annotation)
            : info.Type;

        private TreeNode TypeExpression(TypeSyntax type) => Typed(WrittenType(type), type switch
        {
            { IsVar: true } => null,
            NullableTypeSyntax nullable when model.GetTypeInfo(nullable.ElementType).Type is { IsReferenceType: true, TypeKind: not TypeKind.Error } element => element.WithNullableAnnotation(NullableAnnotation.Annotated),
            _ when model.GetTypeInfo(type).Type is { IsReferenceType: true, TypeKind: not TypeKind.Error, NullableAnnotation: NullableAnnotation.None } held
                && model.GetNullableContext(type.SpanStart).AnnotationsEnabled() => held.WithNullableAnnotation(NullableAnnotation.NotAnnotated),
            _ => model.GetTypeInfo(type).Type,
        });

        private TreeNode WrittenType(TypeSyntax type) => type switch
        {
            PredefinedTypeSyntax keyword => New("type-expression", keyword).Fact("form", "keyword").Fact("name", keyword.Keyword.Text),
            NullableTypeSyntax nullable => New("type-expression", nullable).Fact("form", "nullable").Holds("element", TypeExpression(nullable.ElementType)),
            ArrayTypeSyntax array => New("type-expression", array).Fact("form", "array").Holds("element", TypeExpression(array.ElementType)),
            PointerTypeSyntax pointer => New("type-expression", pointer).Fact("form", "pointer").Holds("element", TypeExpression(pointer.ElementType)),
            RefTypeSyntax reference => TypeExpression(reference.Type),
            ScopedTypeSyntax scoped => TypeExpression(scoped.Type),
            TupleTypeSyntax tuple => New("tuple-type", tuple).HoldsAll("elements", tuple.Elements.Select(element => New("tuple-element", element)
                .Fact("label", element.Identifier.IsKind(SyntaxKind.IdentifierToken) ? element.Identifier.ValueText : null)
                .Holds("type", TypeExpression(element.Type)))),
            IdentifierNameSyntax { IsVar: true } var => New("type-expression", var).Fact("form", "keyword").Fact("name", "var"),
            NameSyntax name => New("type-expression", name)
                .Fact("form", "named")
                .Fact("name", name switch
                {
                    QualifiedNameSyntax qualified => qualified.Left + "." + qualified.Right.Identifier.ValueText,
                    SimpleNameSyntax simple => simple.Identifier.ValueText,
                    _ => name.ToString(),
                })
                .Fact("refers", Refers(name))
                .HoldsAll("typeArguments", (name is QualifiedNameSyntax { Right: GenericNameSyntax right } ? right : name as GenericNameSyntax)?.TypeArgumentList.Arguments.Select(TypeExpression) ?? []),
            FunctionPointerTypeSyntax pointer => New("type-expression", pointer).Fact("form", "function")
                .HoldsAll("parameters", pointer.ParameterList.Parameters.SkipLast(1).Select(each => New("parameter", each).Holds("declaredType", TypeExpression(each.Type))))
                .Holds("returnType", TypeExpression(pointer.ParameterList.Parameters.Last().Type)),
            _ => New("type-expression", type).Fact("form", "opaque"),
        };

        /// <summary>The type or namespace a name names; through an alias, the alias's target.</summary>
        private string? Refers(SyntaxNode name)
        {
            if (name is IdentifierNameSyntax identifier && model.GetAliasInfo(identifier) is { } alias)
            {
                return alias.Target.ToDisplayString(Readings.Qualified);
            }

            return model.GetSymbolInfo(name).Symbol switch
            {
                INamedTypeSymbol type and not IErrorTypeSymbol => Remembered(type),
                INamespaceSymbol space => space.ToDisplayString(Readings.Qualified),
                _ => null,
            };
        }

        // Comments --------------------------------------------------------------------------------------------------

        /// <summary>Every comment of the file, attached to the node it leads or trails, with what C#'s parser makes of its words.</summary>
        public List<Dictionary<string, object>> Comments(List<TreeNode> nodes)
        {
            var owners = nodes.Where(node => node.Kind is not ("file" or "module" or "block")).ToList();
            var starting = new Dictionary<int, TreeNode>();

            foreach (var node in owners)
            {
                starting.TryAdd(node.Start, node);
            }

            var found = tree.GetRoot().DescendantTrivia(descendIntoTrivia: false).Where(Readings.IsComment).ToList();
            var ends = found.ToDictionary(trivia => trivia.FullSpan.Start, Trimmed);
            var written = text.ToString();
            var comments = new List<Dictionary<string, object>>();

            foreach (var trivia in found)
            {
                var (start, end) = (trivia.FullSpan.Start, Trimmed(trivia));
                var form = Readings.CommentKind(trivia);
                var comment = new Dictionary<string, object>
                {
                    ["id"] = comments.Count,
                    ["form"] = form,
                    ["text"] = written[start..end],
                    ["span"] = new List<int> { bytes[start], bytes[end], text.Lines.GetLineFromPosition(start).LineNumber + 1 },
                };
                var line = text.Lines.GetLineFromPosition(start).Start;

                if (written[line..start].Trim().Length > 0)
                {
                    if (owners.FirstOrDefault(node => node.Start >= bytes[line] && node.Start < bytes[start]) is { } trailed)
                    {
                        comment["attached"] = trailed.Id;
                    }

                    comment["trailing"] = true;
                }
                else if (Leads(written, end, ends) is { } next && starting.TryGetValue(bytes[next], out var led))
                {
                    comment["attached"] = led.Id;
                }

                if (trivia.GetStructure() is DocumentationCommentTriviaSyntax documentation && References(documentation) is { Count: > 0 } references)
                {
                    comment["refs"] = references;
                }

                if (form != "doc")
                {
                    comment["fragment"] = Fragment(written[start..end]);
                }

                comments.Add(comment);
            }

            return comments;
        }

        /// <summary>Where the code a comment leads starts, other comments skipped; none past a blank line.</summary>
        private static int? Leads(string written, int after, Dictionary<int, int> ends)
        {
            while (true)
            {
                var breaks = 0;

                while (after < written.Length && written[after] is ' ' or '\t' or '\r' or '\n')
                {
                    breaks += written[after] == '\n' ? 1 : 0;
                    after++;
                }

                if (breaks > 1)
                {
                    return null;
                }

                if (!ends.TryGetValue(after, out var skipped))
                {
                    return after;
                }

                after = skipped;
            }
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

        /// <summary>Each `cref` as written, what it resolves to, and when it does not, the longest qualifier that does.</summary>
        private List<Dictionary<string, object>> References(DocumentationCommentTriviaSyntax documentation) => documentation
            .DescendantNodes()
            .OfType<CrefSyntax>()
            .Where(cref => cref.Parent is not CrefSyntax)
            .Select(cref =>
            {
                var info = model.GetSymbolInfo(cref);
                var symbol = info.Symbol ?? info.CandidateSymbols.FirstOrDefault();
                var owner = symbol is null ? Readings.Owner(cref, model) : null;
                var reference = new Dictionary<string, object> { ["text"] = cref.ToString() };

                if (symbol is not null)
                {
                    reference["symbol"] = symbol.ToDisplayString(Readings.Declared);
                }

                if (owner is not null)
                {
                    reference["owner"] = owner.ToDisplayString(Readings.Qualified);
                }

                if (owner?.Locations.Any(location => location.IsInSource) == true)
                {
                    reference["ownedHere"] = true;
                }

                if (symbol is null && readings.IsBlind(model))
                {
                    reference["blind"] = true;
                }

                return reference;
            })
            .ToList();

        /// <summary>What C#'s parser makes of a comment's words read as statements; the shared rule decides whether they are code.</summary>
        private Dictionary<string, object> Fragment(string comment)
        {
            var words = (comment.StartsWith("/*") ? comment[2..^2] : comment[2..]).Trim();
            var read = new Dictionary<string, object> { ["context"] = "statement", ["diagnostics"] = 0, ["statements"] = 0 };

            if (words.Length == 0)
            {
                return read;
            }

            var block = (BlockSyntax)SyntaxFactory.ParseStatement("{" + words + "\n}");
            read["diagnostics"] = block.GetDiagnostics().Count(diagnostic => diagnostic.Severity == DiagnosticSeverity.Error);
            read["statements"] = block.Statements.Count;

            if (block.Statements is [ExpressionStatementSyntax { Expression: var lone }])
            {
                // The lone expression's kind is what the translation makes of it, as for any expression of the file.
                var snippet = CSharpSyntaxTree.ParseText("class Fragment { void Read() { " + lone.ToString() + "; } }");
                if (snippet.GetRoot().DescendantNodes().OfType<ExpressionStatementSyntax>().FirstOrDefault() is { } statement)
                {
                    var compiled = CSharpCompilation.Create("fragment", [snippet]);
                    read["expressionKind"] = new Translator(run, readings, snippet, compiled.GetSemanticModel(snippet)).Expression(statement.Expression).Kind;
                }
            }

            return read;
        }
    }
}
