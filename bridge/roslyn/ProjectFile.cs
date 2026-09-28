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

    private readonly string name;

    /// <summary>The file each of <see cref="documents"/> was read from, in the same order.</summary>
    private readonly string[] paths;

    /// <summary>The SDK the project names, without a version — `Microsoft.NET.Sdk.Web` — or none for a project of the
    /// old format, which names no SDK.</summary>
    public string? Sdk { get; }

    /// <summary>The folder the project file sits in, its full path.</summary>
    public string Folder { get; }

    private ProjectFile(string csproj)
    {
        Folder = Path.GetFullPath(Path.Combine(csproj, ".."));
        name = Path.GetFileNameWithoutExtension(csproj);
        paths = [csproj, .. BuildProps(Folder)];
        documents = paths.Select(Load).ToArray();
        central = CentralVersions(Nearest(Folder, "Directory.Packages.props"));
        Sdk = documents[0].Root?.Attribute("Sdk")?.Value.Split('/')[0];
    }

    public static ProjectFile Read(string csproj) => new(csproj);

    /// <summary>Is the project a web project — built on `Microsoft.NET.Sdk.Web`, whose framework and usings it brings?</summary>
    public bool IsWeb() => string.Equals(Sdk, "Microsoft.NET.Sdk.Web", StringComparison.OrdinalIgnoreCase);

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

    /// <summary>
    /// Is this a test project — <c>IsTestProject</c> set, as the test SDK sets it, or the test SDK
    /// referenced?
    /// </summary>
    public bool IsTestProject() =>
        documents.SelectMany(document => document.Descendants()).Any(element => element.Name.LocalName == "IsTestProject" && element.Value.Trim() == "true")
        || Included("PackageReference").Any(reference => reference.Id.Equals("Microsoft.NET.Test.Sdk", StringComparison.OrdinalIgnoreCase));

    /// <summary>The namespaces the project adds as global usings with <c>&lt;Using Include&gt;</c>.</summary>
    public IEnumerable<string> Usings() => Included("Using").Select(reference => reference.Id);

    /// <summary>
    /// The folder MSBuild writes the project's restore output and generated sources to: <c>obj/</c> beside
    /// it, or <c>obj/&lt;project&gt;</c> under the solution's artifacts folder when it uses that layout.
    /// </summary>
    public string Intermediate() => Artifacts() is { } artifacts ? Path.Combine(artifacts, "obj", name) : Path.Combine(Folder, "obj");

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
                var from = Path.GetFullPath(Path.Combine(paths[index], ".."));
                var expanded = declared.Replace("$(MSBuildThisFileDirectory)", from + Path.DirectorySeparatorChar).Replace('\\', Path.DirectorySeparatorChar);

                return expanded.Contains('$') ? null : Path.GetFullPath(expanded, from);
            }
        }

        var enabled = documents.SelectMany(document => document.Descendants()).Any(element => element.Name.LocalName == "UseArtifactsOutput" && element.Value.Trim() == "true");

        return enabled ? Path.GetFullPath(Path.Combine(paths.Length > 1 ? paths[1] : paths[0], "..", "artifacts")) : null;
    }

    /// <summary>The projects this one references, as full paths.</summary>
    public IEnumerable<string> ProjectReferences() =>
        Included("ProjectReference").Select(reference => Path.GetFullPath(Path.Combine(Folder, reference.Id.Replace('\\', Path.DirectorySeparatorChar))));

    /// <summary>The shared frameworks the project references by name.</summary>
    public IEnumerable<string> FrameworkReferences() => Included("FrameworkReference").Select(reference => reference.Id);

    /// <summary>The packages the project references, each with the version it asked for or the central one.</summary>
    public IEnumerable<(string Id, string Version)> PackageReferences()
    {
        foreach (var reference in Included("PackageReference"))
        {
            if ((reference.Version ?? central.GetValueOrDefault(reference.Id)) is { } version)
            {
                yield return (reference.Id, version);
            }
        }
    }

    private IEnumerable<(string Id, string? Version)> Included(string item)
    {
        foreach (var element in documents.SelectMany(document => document.Descendants().Where(element => element.Name.LocalName == item)))
        {
            if (element.Attribute("Include")?.Value is { } id)
            {
                yield return (id, Version(element));
            }
        }
    }

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

            props = ImportsItsParent(props) ? Nearest(Path.GetFullPath(Path.Combine(props, "..", "..")), "Directory.Build.props") : null;
        }
    }

    private static bool ImportsItsParent(string props) =>
        Load(props).Descendants().Any(element => element.Name.LocalName == "Import"
            && (element.Attribute("Project")?.Value ?? "").Contains("Directory.Build.props", StringComparison.OrdinalIgnoreCase));

    /// <summary>The file named <paramref name="name"/> in <paramref name="directory"/> or the nearest directory above it.</summary>
    private static string? Nearest(string? directory, string name)
    {
        var current = directory;

        while (current is not null)
        {
            var candidate = Path.Combine(current, name);

            if (File.Exists(candidate))
            {
                return candidate;
            }

            current = Path.GetDirectoryName(current);
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

    private static XDocument Load(string path)
    {
        try
        {
            return XDocument.Load(path);
        }
        catch (System.Xml.XmlException error)
        {
            throw MalformedProjectFile.At(path, error);
        }
    }
}

/// <summary>A project or props file that is not well-formed XML, named by its path.</summary>
public sealed class MalformedProjectFile(string message, Exception cause) : Exception(message, cause)
{
    public static MalformedProjectFile At(string path, System.Xml.XmlException cause) => new($"{path} is not well-formed XML: {cause.Message}", cause);
}
