using System.Text.Json;
using CodeCommandments.Bridge;

// roslyn-bridge <path>...  — parses every C# file under the given roots (or the files named), compiles them together
// without building the project, and writes them to stdout as the generic tree (contract/CONTRACT.md): one project at
// a time, its compilation let go once written.
//
// roslyn-bridge --serve    — the same, kept warm: one request per line on stdin, {"paths": [...]}, each answered with
// its lines, only loaded references kept between requests. "write": [...] limits the answer to those files; the rest
// are written too, marked as context.
//
// roslyn-bridge --listen <port> — --serve over a TCP socket, one connection at a time: the service a session keeps up.
//
// --diagnose               — the compiler's most common errors on stderr first: why a call did not resolve.
var workspace = new Workspace();

if (args.Contains("--serve"))
{
    using var output = Console.OpenStandardOutput();
    Serve(Console.In, output, workspace);

    return 0;
}

if (Array.IndexOf(args, "--listen") is var at and >= 0 && at + 1 < args.Length && int.TryParse(args[at + 1], out var port))
{
    var listener = new System.Net.Sockets.TcpListener(System.Net.IPAddress.Any, port);
    listener.Start();

    while (true)
    {
        using var client = listener.AcceptTcpClient();
        using var stream = client.GetStream();
        using var reader = new StreamReader(stream);
        Serve(reader, stream, workspace);
    }
}

var roots = args.Where(arg => !arg.StartsWith("--")).ToList();

if (roots.Count == 0)
{
    Console.Error.WriteLine("usage: roslyn-bridge [--diagnose] <path>... | roslyn-bridge --serve | roslyn-bridge --listen <port>");

    return 2;
}

if (args.Contains("--diagnose"))
{
    foreach (var group in workspace.Read(roots).Diagnostics()
                 .Where(diagnostic => diagnostic.Severity == Microsoft.CodeAnalysis.DiagnosticSeverity.Error)
                 .GroupBy(diagnostic => diagnostic.Id + " " + diagnostic.GetMessage())
                 .OrderByDescending(group => group.Count())
                 .Take(12))
    {
        Console.Error.WriteLine($"{group.Count(),6}  {group.Key}");
    }
}

using (var tree = Console.OpenStandardOutput())
{
    new ContractWriter(roots).Write(tree, workspace);
}

return 0;

// Answers every request line from input with its lines on output, until input ends.
static void Serve(TextReader input, Stream output, Workspace workspace)
{
    while (input.ReadLine() is { } line)
    {
        var request = Request.Of(line);
        new ContractWriter(request.Paths, request.Written).Write(output, workspace);
        output.Flush();
    }
}

// A request line: the paths to compile, and the files among them to write.
internal sealed record Request(List<string> Paths, HashSet<string> Written)
{
    public static Request Of(string line)
    {
        var request = JsonDocument.Parse(line).RootElement;
        var paths = request.GetProperty("paths").EnumerateArray().Select(PathOf).ToList();
        var written = request.TryGetProperty("write", out var write)
            ? write.EnumerateArray().Select(path => Path.GetFullPath(PathOf(path))).ToHashSet()
            : [];

        return new Request(paths, written);
    }

    private static string PathOf(JsonElement path) => path.GetString() ?? throw MalformedRequest.ForPath(path);
}

// A request line that names something other than a path where it must name one.
internal sealed class MalformedRequest(string message) : Exception(message)
{
    public static MalformedRequest ForPath(JsonElement given) => new($"a request names {given.ValueKind} where a path belongs");
}
