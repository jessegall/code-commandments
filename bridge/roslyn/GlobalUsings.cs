using System.Xml.Linq;

namespace CodeCommandments.Bridge;

/// <summary>
/// The global usings a project compiles with but does not write in its own files — what the SDK
/// generates for &lt;ImplicitUsings&gt; and &lt;Using Include&gt;. Taken from the file the SDK generated
/// when there is one; otherwise derived from the project file the way the SDK derives it.
/// </summary>
public static class GlobalUsings
{
    private static readonly string[] Sdk = ["System", "System.Collections.Generic", "System.IO", "System.Linq", "System.Net.Http", "System.Threading", "System.Threading.Tasks"];

    private static readonly string[] Web = ["System.Net.Http.Json", "Microsoft.AspNetCore.Builder", "Microsoft.AspNetCore.Hosting", "Microsoft.AspNetCore.Http", "Microsoft.AspNetCore.Routing", "Microsoft.Extensions.Configuration", "Microsoft.Extensions.DependencyInjection", "Microsoft.Extensions.Hosting", "Microsoft.Extensions.Logging"];

    /// <summary>One source per project under $roots, holding its global using directives.</summary>
    public static IEnumerable<string> Under(IEnumerable<string> roots) =>
        roots.Where(Directory.Exists)
            .SelectMany(root => Directory.EnumerateFiles(root, "*.csproj", SearchOption.AllDirectories))
            .Where(file => !file.Contains($"{Path.DirectorySeparatorChar}obj{Path.DirectorySeparatorChar}"))
            .Select(Of)
            .Where(source => source != "");

    private static string Of(string csproj)
    {
        var name = Path.GetFileNameWithoutExtension(csproj);
        var obj = Path.Combine(Path.GetDirectoryName(csproj)!, "obj");
        var generated = Directory.Exists(obj)
            ? Directory.EnumerateFiles(obj, $"{name}.GlobalUsings.g.cs", SearchOption.AllDirectories).OrderByDescending(File.GetLastWriteTimeUtc).FirstOrDefault()
            : null;

        return generated is not null ? File.ReadAllText(generated) : Derived(csproj);
    }

    private static string Derived(string csproj)
    {
        var document = XDocument.Load(csproj);
        var implicitly = document.Descendants("ImplicitUsings").Any(element => element.Value is "enable" or "true");
        var web = (document.Root?.Attribute("Sdk")?.Value ?? "").Equals("Microsoft.NET.Sdk.Web", StringComparison.OrdinalIgnoreCase);
        var namespaces = new List<string>();

        if (implicitly)
        {
            namespaces.AddRange(Sdk);
            namespaces.AddRange(web ? Web : []);
        }

        namespaces.AddRange(document.Descendants("Using").Select(element => element.Attribute("Include")?.Value).OfType<string>());

        return string.Join("\n", namespaces.Distinct().Select(space => $"global using global::{space};"));
    }
}
