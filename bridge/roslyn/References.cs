using Microsoft.CodeAnalysis;

namespace CodeCommandments.Bridge;

/// <summary>The assemblies a run compiles against — never built or restored, only found.</summary>
public static class References
{
    /// <summary>The running .NET runtime's own assemblies.</summary>
    public static IEnumerable<MetadataReference> Runtime() =>
        ((string?)AppContext.GetData("TRUSTED_PLATFORM_ASSEMBLIES") ?? "")
            .Split(Path.PathSeparator, StringSplitOptions.RemoveEmptyEntries)
            .Select(path => MetadataReference.CreateFromFile(path));
}
