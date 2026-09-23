using System.Xml.Linq;

namespace CodeCommandments.Bridge;

/// <summary>
/// A project file read as MSBuild composes it, without MSBuild: the project itself, the nearest
/// Directory.Build.props above it (and each parent one it imports in turn), and the central versions of
/// the nearest Directory.Packages.props. The project's own word wins over an imported one.
/// </summary>
public sealed class ProjectFile
{
    private readonly XDocument[] documents;

    private readonly Dictionary<string, string> central;

    private readonly string directory;

    private readonly string name;

    /// <summary>The file each of <see cref="documents"/> was read from, in the same order.</summary>
    private readonly string[] paths;

    private ProjectFile(string csproj)
    {
        directory = Path.GetDirectoryName(csproj)!;
        name = Path.GetFileNameWithoutExtension(csproj);
        paths = [csproj, .. BuildProps(directory)];
        documents = paths.Select(Load).ToArray();
        central = CentralVersions(Nearest(directory, "Directory.Packages.props"));
        Sdk = (documents[0].Root?.Attribute("Sdk")?.Value ?? "").Split('/')[0];
    }

    /// <summary>The SDK the project names, without a version — `Microsoft.NET.Sdk.Web`.</summary>
    public string Sdk { get; }

    public static ProjectFile Read(string csproj) => new(csproj);

    /// <summary>The framework the project targets — the first a multi-targeting one names, never a property it could not expand.</summary>
    public string TargetFramework() =>
        documents
            .SelectMany(document => document.Descendants().Where(element => element.Name.LocalName is "TargetFramework" or "TargetFrameworks"))
            .SelectMany(element => element.Value.Split(';', StringSplitOptions.TrimEntries | StringSplitOptions.RemoveEmptyEntries))
            .FirstOrDefault(framework => !framework.Contains('$'))
        ?? "";

    /// <summary>Whether the SDK generates its implicit global usings for the project.</summary>
    public bool ImplicitUsings() =>
        documents
            .SelectMany(document => document.Descendants().Where(element => element.Name.LocalName == "ImplicitUsings"))
            .Select(element => element.Value.Trim())
            .FirstOrDefault() is "enable" or "true";

    /// <summary>The namespaces the project adds as global usings with <c>&lt;Using Include&gt;</c>.</summary>
    public IEnumerable<string> Usings() => Included("Using").Select(reference => reference.Id);

    /// <summary>
    /// The folder MSBuild writes the project's restore output and generated sources to: <c>obj/</c> beside
    /// it, or <c>obj/&lt;project&gt;</c> under the solution's artifacts folder when it uses that layout.
    /// </summary>
    public string Intermediate() => Artifacts() is { } artifacts ? Path.Combine(artifacts, "obj", name) : Path.Combine(directory, "obj");

    /// <summary>
    /// The artifacts folder the solution declares — <c>ArtifactsPath</c>, read relative to the file that
    /// sets it, or the folder beside the nearest Directory.Build.props when only <c>UseArtifactsOutput</c>
    /// is on. None when the layout is not in use, or the path names a property this reader cannot expand.
    /// </summary>
    private string? Artifacts()
    {
        for (var index = 0; index < documents.Length; index++)
        {
            var declared = documents[index].Descendants().FirstOrDefault(element => element.Name.LocalName == "ArtifactsPath")?.Value.Trim();

            if (declared is not null)
            {
                var from = Path.GetDirectoryName(paths[index])!;
                var expanded = declared.Replace("$(MSBuildThisFileDirectory)", from + Path.DirectorySeparatorChar).Replace('\\', Path.DirectorySeparatorChar);

                return expanded.Contains('$') ? null : Path.GetFullPath(expanded, from);
            }
        }

        var enabled = documents.SelectMany(document => document.Descendants()).Any(element => element.Name.LocalName == "UseArtifactsOutput" && element.Value.Trim() == "true");

        return enabled ? Path.Combine(Path.GetDirectoryName(paths.Length > 1 ? paths[1] : paths[0])!, "artifacts") : null;
    }

    /// <summary>The projects this one references, as full paths.</summary>
    public IEnumerable<string> ProjectReferences() =>
        Included("ProjectReference").Select(reference => Path.GetFullPath(Path.Combine(directory, reference.Id.Replace('\\', Path.DirectorySeparatorChar))));

    /// <summary>The shared frameworks the project references by name.</summary>
    public IEnumerable<string> FrameworkReferences() => Included("FrameworkReference").Select(reference => reference.Id);

    /// <summary>The packages the project references, each with the version it asked for or the central one.</summary>
    public IEnumerable<(string Id, string Version)> PackageReferences() =>
        Included("PackageReference")
            .Select(reference => (reference.Id, Version: reference.Version ?? central.GetValueOrDefault(reference.Id)))
            .Where(reference => reference.Version is not null)
            .Select(reference => (reference.Id, reference.Version!));

    private IEnumerable<(string Id, string? Version)> Included(string item) =>
        documents
            .SelectMany(document => document.Descendants().Where(element => element.Name.LocalName == item))
            .Select(element => (Id: element.Attribute("Include")?.Value, Version: Version(element)))
            .Where(reference => reference.Id is not null)
            .Select(reference => (reference.Id!, reference.Version));

    private static string? Version(XElement element) =>
        element.Attribute("Version")?.Value
        ?? element.Attribute("VersionOverride")?.Value
        ?? element.Elements().FirstOrDefault(child => child.Name.LocalName == "Version")?.Value;

    /// <summary>
    /// The Directory.Build.props MSBuild imports for a project in <paramref name="directory"/>: the nearest
    /// above it, then the next above that for as long as each one imports its parent.
    /// </summary>
    private static IEnumerable<string> BuildProps(string directory)
    {
        var props = Nearest(directory, "Directory.Build.props");

        while (props is not null)
        {
            yield return props;

            props = ImportsItsParent(props) ? Nearest(Path.GetDirectoryName(Path.GetDirectoryName(props)!)!, "Directory.Build.props") : null;
        }
    }

    private static bool ImportsItsParent(string props) =>
        Load(props).Descendants().Any(element => element.Name.LocalName == "Import"
            && (element.Attribute("Project")?.Value ?? "").Contains("Directory.Build.props", StringComparison.OrdinalIgnoreCase));

    /// <summary>The file named <paramref name="name"/> in <paramref name="directory"/> or the nearest directory above it.</summary>
    private static string? Nearest(string? directory, string name)
    {
        for (var current = directory; current is not null; current = Path.GetDirectoryName(current))
        {
            var candidate = Path.Combine(current, name);

            if (File.Exists(candidate))
            {
                return candidate;
            }
        }

        return null;
    }

    private static Dictionary<string, string> CentralVersions(string? props)
    {
        var versions = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);

        if (props is null)
        {
            return versions;
        }

        foreach (var element in Load(props).Descendants().Where(element => element.Name.LocalName == "PackageVersion"))
        {
            if (element.Attribute("Include")?.Value is { } id && element.Attribute("Version")?.Value is { } version)
            {
                versions.TryAdd(id, version);
            }
        }

        return versions;
    }

    private static XDocument Load(string path) => XDocument.Load(path);
}
