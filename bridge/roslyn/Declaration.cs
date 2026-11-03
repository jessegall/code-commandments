using System.Reflection;
using System.Text.Json;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;

namespace CodeCommandments.Bridge;

/// <summary>
/// What this bridge can emit, read from its own writer tables: the kind of every node it writes is Roslyn's
/// <see cref="SyntaxKind"/> of that node, and a field is the Roslyn property the child fills, which is reflection
/// over the syntax classes — the same reading the writer makes.
/// </summary>
public static class Declaration
{
    /// <summary>Writes the declaration as one JSON object.</summary>
    public static void Write(Stream output)
    {
        using var json = new Utf8JsonWriter(output);
        json.WriteStartObject();
        json.WriteString("language", "csharp");
        json.WriteString("parser", $"roslyn {typeof(SyntaxKind).Assembly.GetName().Version}");
        json.WriteString("from", "this bridge's own writer tables: every SyntaxKind it can name a node by, and every Roslyn property a child fills. A kind is a syntax KIND and a field is a property of a syntax CLASS, and one class answers many kinds, so the fields are listed for the language rather than joined to a kind the parser never joined them to");
        json.WriteStartArray("kinds");

        foreach (var kind in Kinds())
        {
            json.WriteStartObject();
            json.WriteString("kind", kind);
            json.WriteEndObject();
        }

        json.WriteEndArray();
        json.WriteStartArray("fields");

        foreach (var field in Fields())
        {
            json.WriteStringValue(field);
        }

        json.WriteEndArray();
        json.WriteEndObject();
        json.Flush();
        output.Write("\n"u8);
    }

    /// <summary>Every kind a node may be written as: the names of Roslyn's own SyntaxKind, less the markers that name a range rather than a node.</summary>
    private static IEnumerable<string> Kinds() => Enum.GetNames<SyntaxKind>()
        .Where(name => !name.StartsWith("First") && !name.StartsWith("Last"))
        .OrderBy(name => name, StringComparer.Ordinal);

    /// <summary>Every slot a child fills: the name of every property of a C# syntax class that can hold a node, which is what the writer reads a field off.</summary>
    private static IEnumerable<string> Fields() => typeof(CSharpSyntaxNode).Assembly
        .GetTypes()
        .Where(type => typeof(CSharpSyntaxNode).IsAssignableFrom(type))
        .SelectMany(type => type.GetProperties(BindingFlags.Public | BindingFlags.Instance))
        .Where(property => property.GetIndexParameters().Length == 0 && property.Name != "Parent")
        .Where(property => typeof(SyntaxNode).IsAssignableFrom(property.PropertyType) || HoldsAList(property.PropertyType))
        .Select(property => property.Name)
        .Distinct()
        .OrderBy(name => name, StringComparer.Ordinal);

    /// <summary>Is the type one of Roslyn's lists of syntax — the property shape a node's children fill?</summary>
    private static bool HoldsAList(Type type) => type.IsGenericType && type.Name.Contains("SyntaxList");
}
