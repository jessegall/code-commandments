using System.Text.Encodings.Web;
using System.Text.Json;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis.CSharp.Syntax;

namespace CodeCommandments.Bridge;

/// <summary>
/// Writes a run as the generic tree (contract/CONTRACT.md): a header, a line per file, the outside declarations the
/// files reach, and a trailer. It reads the compiler through <see cref="Readings"/>.
/// It streams: one project at a time, each node written straight through as it is read, nothing of a project kept
/// once its files are written but the plain text of the outside declarations it reached.
/// </summary>
public sealed partial class ContractWriter(IReadOnlyList<string> roots, IReadOnlySet<string>? written = null, IReadOnlyList<string>? support = null)
{
    public const int Version = 5;

    /// <summary>The bridge's own version, which the header names beside the contract's.</summary>
    private const string BridgeVersion = "8.0.0";

    /// <summary>How a line is written: deep enough for a long chain of expressions, which nests past 64.</summary>
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
            json.WriteString("version", BridgeVersion);
            json.WriteBoolean("reads", true);
            json.WriteEndObject();
            json.WriteStartArray("roots");

            foreach (var root in roots)
            {
                json.WriteStringValue(Real(root));
            }

            json.WriteEndArray();
            json.WriteEndObject();
        });

        workspace.Stream(roots, support ?? [], written ?? new HashSet<string>(), asked => WriteProject(output, asked));

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

    /// <summary>A line for each of the project's asked files that exists, in order, naming the other files its tree read.</summary>
    private void WriteProject(Stream output, Asked asked)
    {
        var project = asked.Read();
        var readings = new Readings(project);
        var trees = project.Trees.ToDictionary(tree => tree.FilePath, StringComparer.Ordinal);

        foreach (var file in asked.Files.Where(trees.ContainsKey))
        {
            var tree = trees[file];
            var reads = asked.Reads.Where(read => read != file).Select(Real).ToList();
            Line(output, json => WriteFile(json, readings, project, tree, project.Model(tree), reads));
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
        var folder = new DirectoryInfo(full);
        var target = info.Exists ? info.ResolveLinkTarget(true) : folder.Exists ? folder.ResolveLinkTarget(true) : null;

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

        var id = original.ToDisplayString(Readings.Qualified);

        if (outside.ContainsKey(id))
        {
            return;
        }

        outside[id] = new OutsideSymbol(
            id,
            original.TypeKind switch { TypeKind.Interface => "interface", TypeKind.Enum => "enum", TypeKind.Struct => "struct", _ => "class" },
            original.Name,
            original.BaseType?.OriginalDefinition.ToDisplayString(Readings.Qualified),
            original.Interfaces.Select(face => face.OriginalDefinition.ToDisplayString(Readings.Qualified)).ToList());

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
        public static OutsideSymbol Read(JsonElement json) => new(
            Text(json.GetProperty("symbol")),
            Text(json.GetProperty("kind")),
            Text(json.GetProperty("name")),
            json.TryGetProperty("extends", out var extends) ? extends.EnumerateArray().Select(Text).FirstOrDefault() : null,
            json.TryGetProperty("implements", out var implements) ? implements.EnumerateArray().Select(Text).ToList() : []);

        private static string Text(JsonElement json) => json.GetString() ?? throw new JsonException("a kept outside declaration names no text where one belongs");

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

    /// <summary>One file's line: its tree translated and numbered in pre-order, then its comments attached to it.</summary>
    private void WriteFile(Utf8JsonWriter json, Readings readings, Project project, SyntaxTree tree, SemanticModel model, IReadOnlyList<string> reads)
    {
        var translator = new Translator(this, readings, tree, model);
        var root = translator.File((CompilationUnitSyntax)tree.GetRoot());
        var nodes = root.Numbered();
        json.WriteStartObject("file");
        json.WriteString("path", Real(tree.FilePath));
        json.WriteString("language", "csharp");
        json.WriteNumber("errors", tree.GetDiagnostics().Count(diagnostic => diagnostic.Severity == DiagnosticSeverity.Error));

        if (tree.GetRoot().DescendantNodes().OfType<BaseNamespaceDeclarationSyntax>().FirstOrDefault() is { } space)
        {
            json.WriteString("module", space.Name.ToString());
        }

        json.WriteStartArray("reads");

        foreach (var read in reads)
        {
            json.WriteStringValue(read);
        }

        json.WriteEndArray();

        if (project.IsTest(tree))
        {
            json.WriteBoolean("test", true);
        }

        json.WriteStartObject("resolver");
        json.WriteString("tool", "roslyn");
        json.WriteBoolean("ran", true);
        json.WriteEndObject();
        json.WritePropertyName("root");
        root.Write(json);
        json.WritePropertyName("comments");
        TreeNode.Value(json, translator.Comments(nodes));
        json.WriteEndObject();
    }
}
