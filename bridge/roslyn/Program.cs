using System.Text.Json;
using CodeCommandments.Bridge;

// roslyn-bridge <path>...  — parses every C# file under the given roots (or the files named), compiles
// them together without building the project, and writes the trees and what the compiler knows about
// them to stdout as JSON lines: the version, a line per file, then the resolution (CONTRACT.md).
//
// roslyn-bridge --serve    — the same, kept warm: one request per line on stdin, {"paths": [...]}, each
// answered with those lines, reusing loaded references and unchanged trees between requests.
// "write": [...] limits the answer to those files; the rest are still compiled, for their types.
var workspace = new Workspace();

if (args.Contains("--serve"))
{
    using var output = Console.OpenStandardOutput();

    while (Console.In.ReadLine() is { } line)
    {
        var request = JsonDocument.Parse(line).RootElement;
        var paths = request.GetProperty("paths").EnumerateArray().Select(path => path.GetString()!).ToList();
        var written = request.TryGetProperty("write", out var write) ? write.EnumerateArray().Select(path => Path.GetFullPath(path.GetString()!)).ToHashSet() : [];
        new TreeWriter(workspace.Read(paths), written).Write(output);
        output.Flush();
    }

    return 0;
}

var roots = args.Where(arg => !arg.StartsWith("--")).ToList();

if (roots.Count == 0)
{
    Console.Error.WriteLine("usage: roslyn-bridge <path>... | roslyn-bridge --serve");
    return 2;
}

var project = workspace.Read(roots);

// --diagnose: the compiler's most common errors, on stderr — why a call did not resolve.
if (args.Contains("--diagnose"))
{
    foreach (var group in project.Compilation.GetDiagnostics()
                 .Where(diagnostic => diagnostic.Severity == Microsoft.CodeAnalysis.DiagnosticSeverity.Error)
                 .GroupBy(diagnostic => diagnostic.Id + " " + diagnostic.GetMessage())
                 .OrderByDescending(group => group.Count())
                 .Take(12))
    {
        Console.Error.WriteLine($"{group.Count(),6}  {group.Key}");
    }
}

using (var output = Console.OpenStandardOutput())
{
    new TreeWriter(project).Write(output);
}

return 0;
