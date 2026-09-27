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

        return File.Exists(assets) && Assets.Read(assets) is { } restored && restored.PlainTarget() is { } target
            ? FromAssets(restored, target)
            : FromProjectFile(project);
    }

    /// <summary>
    /// What a project whose reach is <paramref name="own"/> compiles against, joined by what the projects
    /// it references reach: their shared frameworks by name, resolved for this project's framework, and
    /// their packages. Each assembly is loaded once, the project's own first.
    /// </summary>
    /// <remarks>
    /// Given <paramref name="loaded"/>, an assembly another project of the run already loaded is shared rather than
    /// loaded again, so its symbols are built once for the whole run.
    /// </remarks>
    public static IReadOnlyList<MetadataReference> Load(Reach own, IEnumerable<Reach> reached, IDictionary<string, MetadataReference>? loaded = null)
    {
        var all = reached.Prepend(own).ToList();
        var frameworks = all.SelectMany(reach => reach.Frameworks).Distinct(StringComparer.OrdinalIgnoreCase);

        return Load([..frameworks.SelectMany(framework => FrameworkPack(framework, own.Tfm)), ..all.SelectMany(reach => reach.Packages)], loaded);
    }

    /// <summary>What a file that belongs to no project compiles against: the running runtime's assemblies.</summary>
    public static IReadOnlyList<MetadataReference> Loose(IDictionary<string, MetadataReference>? loaded = null) => Load([], loaded);

    /// <summary><paramref name="found"/> once each by file name, with the runtime's assemblies when they name no System.Runtime.</summary>
    private static IReadOnlyList<MetadataReference> Load(IEnumerable<string> found, IDictionary<string, MetadataReference>? loaded)
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

        return dlls.Values.Select(dll => Reference(dll, loaded)).ToList();
    }

    /// <summary>The assembly at <paramref name="dll"/>: the one <paramref name="loaded"/> already holds, or loaded now and kept there.</summary>
    private static MetadataReference Reference(string dll, IDictionary<string, MetadataReference>? loaded)
    {
        if (loaded is null)
        {
            return MetadataReference.CreateFromFile(dll);
        }

        if (!loaded.TryGetValue(dll, out var reference))
        {
            loaded[dll] = reference = MetadataReference.CreateFromFile(dll);
        }

        return reference;
    }

    /// <summary>
    /// What a restored project reaches for <paramref name="target"/>, the first framework it restored that is not a
    /// runtime-specific one (<c>net8.0/linux-x64</c>), so one compilation never mixes two frameworks' assemblies.
    /// </summary>
    private static Reach FromAssets(Assets assets, KeyValuePair<string, Dictionary<string, AssetsTarget>> target)
    {
        var packages = new List<string>();

        foreach (var (name, package) in target.Value)
        {
            if (package.Compile is null || !assets.Libraries.TryGetValue(name, out var library) || library.Path is null)
            {
                continue;
            }

            foreach (var file in package.Compile.Keys.Where(file => file.EndsWith(".dll", StringComparison.OrdinalIgnoreCase)))
            {
                var found = assets.PackageFolders.Keys.Select(cache => Path.Combine(cache, library.Path, file)).FirstOrDefault(File.Exists);

                if (found is not null)
                {
                    packages.Add(found);
                }
            }
        }

        return new Reach(target.Key, assets.FrameworksOf(target.Key).ToList(), packages);
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
        var reference = PackVersions(framework)
            .Select(version => Path.Combine(version, "ref", tfm))
            .FirstOrDefault(Directory.Exists);

        return reference is null ? [] : Directory.EnumerateFiles(reference, "*.dll");
    }

    /// <summary>Every installed version of <paramref name="framework"/>'s reference pack, the newest first.</summary>
    private static IEnumerable<string> PackVersions(string framework)
    {
        var packs = Path.Combine(DotnetRoot(), "packs", $"{framework}.Ref");

        return Directory.Exists(packs)
            ? Directory.GetDirectories(packs).OrderByDescending(version => Version.TryParse(Path.GetFileName(version).Split('-')[0], out var parsed) ? parsed : new Version(0, 0))
            : [];
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

    /// <summary>
    /// What compiles when no project names its frameworks: the running runtime's own assemblies, when it has them as
    /// files; a self-contained bridge carries them inside itself, so there the newest framework reference pack.
    /// </summary>
    private static IEnumerable<string> Runtime()
    {
        var trusted = ((string?)AppContext.GetData("TRUSTED_PLATFORM_ASSEMBLIES") ?? "")
            .Split(Path.PathSeparator, StringSplitOptions.RemoveEmptyEntries)
            .Where(File.Exists)
            .ToList();

        return trusted.Any(dll => Path.GetFileName(dll).Equals("System.Runtime.dll", StringComparison.OrdinalIgnoreCase)) ? trusted : NewestPack("Microsoft.NETCore.App");
    }

    /// <summary>The reference assemblies of <paramref name="framework"/>'s newest installed pack, for its newest framework.</summary>
    private static IEnumerable<string> NewestPack(string framework)
    {
        var reference = PackVersions(framework)
            .Select(version => Path.Combine(version, "ref"))
            .Where(Directory.Exists)
            .SelectMany(Directory.GetDirectories)
            .OrderByDescending(folder => folder, StringComparer.Ordinal)
            .FirstOrDefault();

        return reference is null ? [] : Directory.EnumerateFiles(reference, "*.dll");
    }

    /// <summary>
    /// The .NET installation whose reference packs the bridge compiles against: the one the tool found on this machine
    /// and names in <c>CODE_COMMANDMENTS_DOTNET_ROOT</c>, else the one the bridge runs on, three levels above its runtime.
    /// </summary>
    private static string DotnetRoot() =>
        Environment.GetEnvironmentVariable("CODE_COMMANDMENTS_DOTNET_ROOT") is { Length: > 0 } found
            ? found
            : Path.GetFullPath(Path.Combine(RuntimeEnvironment.GetRuntimeDirectory(), "..", "..", ".."));
}

/// <summary>
/// The part of NuGet's project.assets.json the bridge reads: the folders packages are cached in, where each library
/// lives, what each restored framework's packages compile against, and the shared frameworks each references.
/// </summary>
internal sealed record Assets(
    Dictionary<string, JsonElement> PackageFolders,
    Dictionary<string, AssetsLibrary> Libraries,
    Dictionary<string, Dictionary<string, AssetsTarget>> Targets,
    AssetsProject? Project)
{
    private static readonly JsonSerializerOptions Options = new() { PropertyNameCaseInsensitive = true };

    /// <summary>The assets file at <paramref name="path"/>, or none when it is not one.</summary>
    public static Assets? Read(string path) => JsonSerializer.Deserialize<Assets>(File.ReadAllText(path), Options);

    /// <summary>The first framework restored for no runtime in particular, or none when every one names a runtime.</summary>
    public KeyValuePair<string, Dictionary<string, AssetsTarget>>? PlainTarget() =>
        Targets.Where(target => !target.Key.Contains('/')).Select(target => (KeyValuePair<string, Dictionary<string, AssetsTarget>>?)target).FirstOrDefault();

    /// <summary>The shared frameworks <paramref name="tfm"/> compiles against: .NET's own, and the ones the project references.</summary>
    public IEnumerable<string> FrameworksOf(string tfm)
    {
        var referenced = Project?.Frameworks?.GetValueOrDefault(tfm)?.FrameworkReferences?.Keys ?? Enumerable.Empty<string>();

        return referenced.Prepend("Microsoft.NETCore.App").Distinct(StringComparer.OrdinalIgnoreCase);
    }
}

/// <summary>A library the assets file lists: a package, by the folder it is cached in; a project reference has none.</summary>
internal sealed record AssetsLibrary(string? Path);

/// <summary>A package of a restored framework: the files it compiles against, when it has any.</summary>
internal sealed record AssetsTarget(Dictionary<string, JsonElement>? Compile);

/// <summary>The project the assets were restored for: its frameworks, by name.</summary>
internal sealed record AssetsProject(Dictionary<string, AssetsFramework>? Frameworks);

/// <summary>One restored framework of the project: the shared frameworks it references, by name.</summary>
internal sealed record AssetsFramework(Dictionary<string, JsonElement>? FrameworkReferences);
