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
// roslyn-bridge --declares — what this bridge can emit, as one JSON object: the kinds it names a node by and the
// slots a child fills. It is the one ask besides a tree, and a census of recorded streams cannot answer it.
//
// --diagnose               — the compiler's most common errors on stderr first: why a call did not resolve.
//
// A failure in any of them ends the bridge with one line on stderr and exit code 1. It never reaches the runtime's
// abort, which a bridge running as a container's first process never returns from.
try
{
    return Run(args, new Workspace());
}
catch (Exception failure)
{
    Console.Error.WriteLine($"roslyn-bridge: {failure.Message}");

    return 1;
}

static int Run(string[] args, Workspace workspace)
{
    // The one ask besides a tree: what this bridge can emit, which a census of recorded streams cannot say.
    if (args.Contains("--declares"))
    {
        using var declared = Console.OpenStandardOutput();
        Declaration.Write(declared);

        return 0;
    }

    var cached = args.FirstOrDefault(arg => arg.StartsWith("--cache=", StringComparison.Ordinal))?["--cache=".Length..];

    if (args.Contains("--serve"))
    {
        Parent.LeaveWithIt();
        using var output = Console.OpenStandardOutput();
        Serve(Console.In, output, workspace, cached);

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
            Serve(reader, stream, workspace, cached);
        }
    }

    var roots = args.Where(arg => !arg.StartsWith("--")).Select(Root.Of).ToList();

    if (roots.Count == 0)
    {
        Console.Error.WriteLine("usage: roslyn-bridge [--diagnose] <path>... | roslyn-bridge --serve | roslyn-bridge --listen <port> | roslyn-bridge --declares");

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
        new ContractWriter(roots, null, Checkout.Cache(cached, roots)).Write(tree, workspace);
    }

    return 0;
}

// Answers every request line from input with its lines on output, until input ends.
static void Serve(TextReader input, Stream output, Workspace workspace, string? cached)
{
    while (input.ReadLine() is { } line)
    {
        var request = Request.Of(line);
        new ContractWriter(request.Paths, request.Written, Checkout.Cache(cached, request.Paths)).Write(output, workspace);
        output.Flush();
    }
}

// The run that started a serving bridge: a run killed outright does not always close the bridge's input, and a bridge
// left like that would compile for no one, so it looks once a second whether it was taken in by the system's first
// process, which is how its parent being gone is seen, and ends then.
internal static class Parent
{
    public static void LeaveWithIt()
    {
        if (OperatingSystem.IsWindows())
        {
            return;
        }

        var parent = getppid();
        new Thread(() =>
        {
            while (getppid() == parent)
            {
                Thread.Sleep(1000);
            }

            Environment.Exit(0);
        }) { IsBackground = true }.Start();
    }

    [System.Runtime.InteropServices.DllImport("libc")]
    private static extern int getppid();
}

// The checkout a run reads: the folder holding the repository of its first path, which every kept line is relative to.
internal static class Checkout
{
    public static TreeCache? Cache(string? folder, IReadOnlyList<string> roots) =>
        folder is { Length: > 0 } && roots.Count > 0 ? new TreeCache(folder, Of(roots[0])) : null;

    private static string Of(string path)
    {
        var full = new FileInfo(Path.GetFullPath(path));
        var start = Directory.Exists(full.FullName) ? new DirectoryInfo(full.FullName) : full.Directory!;

        return RepositoryAround(start)?.FullName ?? start.FullName;
    }

    private static DirectoryInfo? RepositoryAround(DirectoryInfo? folder) =>
        folder is null || Path.Exists(Path.Combine(folder.FullName, ".git")) ? folder : RepositoryAround(folder.Parent);
}

// A request line: the paths to compile, and the files among them to write.
internal sealed record Request(List<string> Paths, HashSet<string> Written)
{
    public static Request Of(string line)
    {
        var request = JsonDocument.Parse(line).RootElement;
        var paths = request.GetProperty("paths").EnumerateArray().Select(PathOf).Select(Root.Of).ToList();
        var written = request.TryGetProperty("write", out var write)
            ? write.EnumerateArray().Select(path => Path.GetFullPath(PathOf(path))).ToHashSet()
            : [];

        return new Request(paths, written);
    }

    private static string PathOf(JsonElement path) => path.GetString() ?? throw MalformedRequest.ForPath(path);
}

// A path a run reads from: a file or a folder that is there, so nothing is written for a run that cannot be read.
internal static class Root
{
    public static string Of(string path)
    {
        if (!File.Exists(path) && !Directory.Exists(path))
        {
            throw MissingRoot.At(path);
        }

        return path;
    }
}

// A request line that names something other than a path where it must name one.
internal sealed class MalformedRequest(string message) : Exception(message)
{
    public static MalformedRequest ForPath(JsonElement given) => new($"a request names {given.ValueKind} where a path belongs");
}

// A path to read that names no file or folder this bridge can see.
internal sealed class MissingRoot(string message) : Exception(message)
{
    public static MissingRoot At(string path) => new($"there is no file or folder at {path} to read");
}
