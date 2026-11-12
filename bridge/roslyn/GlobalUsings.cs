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

    /// <summary>
    /// The global using directives the project <paramref name="csproj"/> compiles with, as source: the file the SDK
    /// <paramref name="generated"/>, which the Go side names, or else derived from the project file.
    /// </summary>
    public static string Of(string csproj, string? generated) =>
        generated is not null ? File.ReadAllText(generated) : Derived(csproj);

    private static string Derived(string csproj)
    {
        var project = ProjectFile.Read(csproj);
        var namespaces = new List<string>();

        if (project.ImplicitUsings())
        {
            namespaces.AddRange(Sdk);
            namespaces.AddRange(project.IsWeb() ? Web : []);
        }

        namespaces.AddRange(project.Usings());

        return string.Join("\n", namespaces.Distinct().Select(space => $"global using global::{space};"));
    }
}
