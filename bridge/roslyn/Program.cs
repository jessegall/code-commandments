using CodeCommandments.Bridge;

// roslyn-bridge <path>... — parses every C# file under the given roots (or the files named), compiles
// them together without building the project, and writes the trees and what the compiler knows about
// them to stdout as one JSON document. Paths that do not exist are skipped; nothing is ever written.
var paths = args.Where(arg => !arg.StartsWith("--")).ToList();

if (paths.Count == 0)
{
    Console.Error.WriteLine("usage: roslyn-bridge <path>...");
    return 2;
}

var project = Project.Read(Sources.Under(paths), paths.Select(Path.GetFullPath).ToList());

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
using var output = Console.OpenStandardOutput();
new TreeWriter(project).Write(output);

return 0;
