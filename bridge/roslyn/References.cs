using System.Runtime.InteropServices;
using System.Text.Json;
using Microsoft.CodeAnalysis;

namespace CodeCommandments.Bridge;

/// <summary>
/// What a project reaches — its target framework, the shared frameworks it compiles against by name, and
/// the package assemblies it references — found, never built or restored. A restored project is read
/// from its project.assets.json (transitive packages included) for its first framework only; an
/// unrestored one from its project file and the NuGet cache.
/// </summary>
public sealed record Reach(string Tfm, IReadOnlyList<string> Frameworks, IReadOnlyList<string> Packages);

/// <summary>
/// The assemblies a compilation loads: shared frameworks from the installed reference packs for ONE
/// target framework, and package assemblies; the runtime's own assemblies only when nothing names
/// System.Runtime.
/// </summary>
public static class References
{
    public static Reach Of(string csproj)
    {
        var project = ProjectFile.Read(csproj);
        var assets = Path.Combine(project.Intermediate(), "project.assets.json");

        return File.Exists(assets) ? FromAssets(assets) : FromProjectFile(project);
    }

    /// <summary>
    /// What a project whose reach is <paramref name="own"/> compiles against, joined by what the projects
    /// it references reach: their shared frameworks by name, resolved for this project's framework, and
    /// their packages. Each assembly is loaded once, the project's own first.
    /// </summary>
    public static IReadOnlyList<MetadataReference> Load(Reach own, IEnumerable<Reach> reached)
    {
        var all = reached.Prepend(own).ToList();
        var frameworks = all.SelectMany(reach => reach.Frameworks).Distinct(StringComparer.OrdinalIgnoreCase);

        return Load([..frameworks.SelectMany(framework => FrameworkPack(framework, own.Tfm)), ..all.SelectMany(reach => reach.Packages)]);
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

    /// <summary>
    /// What a restored project reaches, for the first framework it restored — a runtime-specific target
    /// (<c>net8.0/linux-x64</c>) and a second framework of a multi-targeting project left aside, so one
    /// compilation never mixes two frameworks' assemblies.
    /// </summary>
    private static Reach FromAssets(string path)
    {
        using var assets = JsonDocument.Parse(File.ReadAllText(path));
        var root = assets.RootElement;
        var folders = root.GetProperty("packageFolders").EnumerateObject().Select(folder => folder.Name).ToList();
        var libraries = root.GetProperty("libraries");
        var target = root.GetProperty("targets").EnumerateObject().FirstOrDefault(candidate => !candidate.Name.Contains('/'));

        if (target.Value.ValueKind != JsonValueKind.Object)
        {
            return new Reach("", ["Microsoft.NETCore.App"], []);
        }

        var packages = new List<string>();

        foreach (var package in target.Value.EnumerateObject())
        {
            if (!package.Value.TryGetProperty("compile", out var compile) || !libraries.TryGetProperty(package.Name, out var library) || !library.TryGetProperty("path", out var folder))
            {
                continue;
            }

            foreach (var file in compile.EnumerateObject().Select(entry => entry.Name).Where(name => name.EndsWith(".dll", StringComparison.OrdinalIgnoreCase)))
            {
                var found = folders.Select(cache => Path.Combine(cache, folder.GetString()!, file)).FirstOrDefault(File.Exists);

                if (found is not null)
                {
                    packages.Add(found);
                }
            }
        }

        return new Reach(target.Name, Frameworks(root, target.Name).ToList(), packages);
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

    /// <summary>What an unrestored project reaches: its SDK's frameworks, and its direct packages from the NuGet cache.</summary>
    private static Reach FromProjectFile(ProjectFile project)
    {
        var tfm = project.TargetFramework();
        var frameworks = new List<string> { "Microsoft.NETCore.App" };

        if (project.Sdk.Equals("Microsoft.NET.Sdk.Web", StringComparison.OrdinalIgnoreCase))
        {
            frameworks.Add("Microsoft.AspNetCore.App");
        }

        frameworks.AddRange(project.FrameworkReferences());

        return new Reach(tfm, frameworks.Distinct(StringComparer.OrdinalIgnoreCase).ToList(), project.PackageReferences().SelectMany(package => CachedPackage(package.Id, package.Version, tfm)).ToList());
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
