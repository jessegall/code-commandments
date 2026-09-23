using System.Runtime.InteropServices;
using System.Text.Json;
using Microsoft.CodeAnalysis;

namespace CodeCommandments.Bridge;

/// <summary>
/// The assemblies a run compiles against — found, never built or restored. Each project's frameworks
/// come from the installed reference packs; its packages from obj/project.assets.json when it has been
/// restored (transitive ones included), else from the NuGet cache by id and version. Only when no
/// project says what it targets does the run fall back to the runtime's own assemblies.
/// </summary>
public static class References
{
    /// <summary>What the project <paramref name="csproj"/> compiles against — the runtime's own assemblies when it says nothing that resolves.</summary>
    public static IReadOnlyList<MetadataReference> Of(string csproj)
    {
        var assets = Path.Combine(ProjectFile.Read(csproj).Intermediate(), "project.assets.json");

        return Load(File.Exists(assets) ? FromAssets(assets) : FromProjectFile(csproj));
    }

    /// <summary>What a file that belongs to no project compiles against: the running runtime's assemblies.</summary>
    public static IReadOnlyList<MetadataReference> Loose() => Load([]);

    /// <summary><paramref name="found"/> once each by file name, with the runtime's assemblies when they name no System.Runtime.</summary>
    private static IReadOnlyList<MetadataReference> Load(IEnumerable<string> found)
    {
        var dlls = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);

        foreach (var dll in found)
        {
            dlls.TryAdd(Path.GetFileName(dll), dll);
        }

        if (!dlls.Keys.Any(name => name.Equals("System.Runtime.dll", StringComparison.OrdinalIgnoreCase)))
        {
            foreach (var dll in Runtime())
            {
                dlls.TryAdd(Path.GetFileName(dll), dll);
            }
        }

        return dlls.Values.Select(dll => (MetadataReference)MetadataReference.CreateFromFile(dll)).ToList();
    }

    /// <summary>What a restored project compiles against: its frameworks and every package it resolved.</summary>
    private static IEnumerable<string> FromAssets(string path)
    {
        using var assets = JsonDocument.Parse(File.ReadAllText(path));
        var root = assets.RootElement;
        var folders = root.GetProperty("packageFolders").EnumerateObject().Select(folder => folder.Name).ToList();
        var libraries = root.GetProperty("libraries");

        foreach (var target in root.GetProperty("targets").EnumerateObject())
        {
            var tfm = target.Name.Split('/')[0];

            foreach (var framework in Frameworks(root, tfm))
            {
                foreach (var dll in FrameworkPack(framework, tfm))
                {
                    yield return dll;
                }
            }

            foreach (var package in target.Value.EnumerateObject())
            {
                if (!package.Value.TryGetProperty("compile", out var compile) || !libraries.TryGetProperty(package.Name, out var library) || !library.TryGetProperty("path", out var folder))
                {
                    continue;
                }

                foreach (var file in compile.EnumerateObject().Select(entry => entry.Name).Where(name => name.EndsWith(".dll", StringComparison.OrdinalIgnoreCase)))
                {
                    var found = folders.Select(packages => Path.Combine(packages, folder.GetString()!, file)).FirstOrDefault(File.Exists);

                    if (found is not null)
                    {
                        yield return found;
                    }
                }
            }
        }
    }

    private static IEnumerable<string> Frameworks(JsonElement root, string tfm)
    {
        var frameworks = new List<string> { "Microsoft.NETCore.App" };

        if (root.TryGetProperty("project", out var project)
            && project.TryGetProperty("frameworks", out var declared)
            && declared.TryGetProperty(tfm, out var target)
            && target.TryGetProperty("frameworkReferences", out var references))
        {
            frameworks.AddRange(references.EnumerateObject().Select(reference => reference.Name));
        }

        return frameworks.Distinct(StringComparer.OrdinalIgnoreCase);
    }

    /// <summary>What an unrestored project names: its SDK's frameworks, and its direct packages from the NuGet cache.</summary>
    private static IEnumerable<string> FromProjectFile(string csproj)
    {
        var project = ProjectFile.Read(csproj);
        var tfm = project.TargetFramework();
        var frameworks = new List<string> { "Microsoft.NETCore.App" };

        if (project.Sdk.Equals("Microsoft.NET.Sdk.Web", StringComparison.OrdinalIgnoreCase))
        {
            frameworks.Add("Microsoft.AspNetCore.App");
        }

        frameworks.AddRange(project.FrameworkReferences());

        foreach (var dll in frameworks.Distinct(StringComparer.OrdinalIgnoreCase).SelectMany(framework => FrameworkPack(framework, tfm)))
        {
            yield return dll;
        }

        foreach (var (id, version) in project.PackageReferences())
        {
            foreach (var dll in CachedPackage(id, version, tfm))
            {
                yield return dll;
            }
        }
    }

    /// <summary>A framework's reference assemblies for $tfm, from the installed reference pack.</summary>
    private static IEnumerable<string> FrameworkPack(string framework, string tfm)
    {
        var packs = Path.Combine(DotnetRoot(), "packs", $"{framework}.Ref");

        if (!Directory.Exists(packs))
        {
            return [];
        }

        var reference = Directory.GetDirectories(packs)
            .OrderByDescending(version => Version.TryParse(Path.GetFileName(version).Split('-')[0], out var parsed) ? parsed : new Version(0, 0))
            .Select(version => Path.Combine(version, "ref", tfm))
            .FirstOrDefault(Directory.Exists);

        return reference is null ? [] : Directory.EnumerateFiles(reference, "*.dll");
    }

    /// <summary>A package in the NuGet cache: the assemblies of the framework folder closest to $tfm.</summary>
    private static IEnumerable<string> CachedPackage(string id, string version, string tfm)
    {
        var home = Environment.GetEnvironmentVariable("NUGET_PACKAGES")
            ?? Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".nuget", "packages");
        var lib = Path.Combine(home, id.ToLowerInvariant(), version, "lib");

        if (!Directory.Exists(lib))
        {
            return [];
        }

        var folders = Directory.GetDirectories(lib).Select(Path.GetFileName).OfType<string>().ToList();
        var best = folders.Contains(tfm) ? tfm : folders.Where(folder => folder.StartsWith("netstandard", StringComparison.Ordinal)).OrderDescending().FirstOrDefault() ?? folders.OrderDescending().FirstOrDefault();

        return best is null ? [] : Directory.EnumerateFiles(Path.Combine(lib, best), "*.dll");
    }

    /// <summary>The running runtime's own assemblies — used only when no project names its frameworks.</summary>
    private static IEnumerable<string> Runtime() =>
        ((string?)AppContext.GetData("TRUSTED_PLATFORM_ASSEMBLIES") ?? "").Split(Path.PathSeparator, StringSplitOptions.RemoveEmptyEntries);

    /// <summary>The dotnet installation the bridge runs on: three levels above the runtime's own folder.</summary>
    private static string DotnetRoot() =>
        Path.GetFullPath(Path.Combine(RuntimeEnvironment.GetRuntimeDirectory(), "..", "..", ".."));
}
