using System.Text.Json;
using CodeCommandments.Bridge;

// roslyn-bridge <path>...  — parses every C# file under the given roots (or the files named), compiles
// them together without building the project, and writes the trees and what the compiler knows about
// them to stdout as JSON lines: the version, a line per file, then the resolution (CONTRACT.md).
//
// roslyn-bridge --serve    — the same, kept warm: one request per line on stdin, {"paths": [...]}, each
// answered with those lines, reusing loaded references and unchanged trees between requests.
// "write": [...] limits the answer to those files; the rest are still compiled, for their types.
//
// --tree                   — either of the above, written as the generic tree (contract/CONTRACT.md) instead:
// files outside "write" are written too, marked as context.
var workspace = new Workspace();
var contract = args.Contains("--tree");

if (args.Contains("--serve"))
{
    using var output = Console.OpenStandardOutput();

    while (Console.In.ReadLine() is { } line)
    {
        var request = JsonDocument.Parse(line).RootElement;
        var paths = request.GetProperty("paths").EnumerateArray().Select(path => path.GetString()!).ToList();
        var written = request.TryGetProperty("write", out var write) ? write.EnumerateArray().Select(path => Path.GetFullPath(path.GetString()!)).ToHashSet() : [];
        if (contract)
        {
            new ContractWriter(workspace.Read(paths), paths, written).Write(output);
        }
        else
        {
            new TreeWriter(workspace.Read(paths), written).Write(output);
        }

        output.Flush();
    }

    return 0;
}

var roots = args.Where(arg => !arg.StartsWith("--")).ToList();

if (roots.Count == 0)
{
    Console.Error.WriteLine("usage: roslyn-bridge [--tree] <path>... | roslyn-bridge [--tree] --serve");
    return 2;
}

var project = workspace.Read(roots);

// --diagnose: the compiler's most common errors, on stderr — why a call did not resolve.
if (args.Contains("--diagnose"))
{
    foreach (var group in project.Diagnostics()
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
    if (contract)
    {
        new ContractWriter(project, roots).Write(output);
    }
    else
    {
        new TreeWriter(project).Write(output);
    }
}

return 0;
