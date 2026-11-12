using System.Security.Cryptography;
using System.Text;

namespace CodeCommandments.Bridge;

/// <summary>
/// A file's line as an earlier run wrote it, kept on disk under a key of everything the line was made from: this bridge,
/// the file, whether it was written as context, and its project's key, which stands for every source of the project,
/// its global usings, what it references and the keys of the projects it reaches. The checkout's root is kept as a
/// placeholder, so a worktree holding the same sources shares the entry. An entry is touched when it is read, so the
/// sweep that prunes the folder by age leaves what is still used alone.
/// </summary>
public sealed class TreeCache(string folder, string root)
{
    /// <summary>What a kept line says in place of the checkout's root and the slash after it: a control character, which no JSON text holds raw.</summary>
    private const string Root = "\u0002";

    /// <summary>What a kept line says in place of its own file's path, so the same contents anywhere share the entry.</summary>
    private const string Own = "\u0001";

    /// <summary>How large a line may be and still be kept: a generated file's tree is not worth the disk.</summary>
    private const int Kept = 16 << 20;

    private static readonly string Identity = KeyOf(Bridge());

    private readonly string rooted = root.TrimEnd('/') + "/";

    public static string KeyOf(params string[] parts) => KeyOf((IEnumerable<string>)parts);

    public static string KeyOf(IEnumerable<string> parts)
    {
        using var hash = IncrementalHash.CreateHash(HashAlgorithmName.SHA256);

        foreach (var part in parts)
        {
            hash.AppendData(Encoding.UTF8.GetBytes(part));
            hash.AppendData([0]);
        }

        return Convert.ToHexString(hash.GetHashAndReset()).ToLowerInvariant();
    }

    public (string Meta, string Line)? Read(string key, string own)
    {
        var entry = EntryOf(key);

        try
        {
            var stored = File.ReadAllText(entry);
            var split = stored.IndexOf('\n');

            if (split < 0)
            {
                return null;
            }

            File.SetLastWriteTimeUtc(entry, DateTime.UtcNow);

            return (stored[..split], stored[(split + 1)..].Replace(Own, own, StringComparison.Ordinal).Replace(Root, rooted, StringComparison.Ordinal));
        }
        catch (IOException)
        {
            return null;
        }
        catch (UnauthorizedAccessException)
        {
            return null;
        }
    }

    public void Keep(string key, string meta, string line, string own)
    {
        if (line.Length > Kept)
        {
            return;
        }

        var entry = EntryOf(key);
        var draft = entry + "." + Environment.ProcessId;

        try
        {
            Directory.CreateDirectory(folder);
            File.WriteAllText(draft, meta + "\n" + line.Replace(own, Own, StringComparison.Ordinal).Replace(rooted, Root, StringComparison.Ordinal));
            File.Move(draft, entry, true);
        }
        catch (IOException)
        {
            File.Delete(draft);
        }
        catch (UnauthorizedAccessException)
        {
        }
    }

    private string EntryOf(string key) => Path.Combine(folder, KeyOf(Identity, key) + ".tree");

    private static IEnumerable<string> Bridge()
    {
        yield return ContractWriter.Version.ToString();

        if (Environment.ProcessPath is { } path && new FileInfo(path) is { Exists: true } info)
        {
            yield return path;
            yield return info.Length.ToString();
            yield return info.LastWriteTimeUtc.Ticks.ToString();
        }

        if (typeof(TreeCache).Assembly.Location is { Length: > 0 } assembly && new FileInfo(assembly) is { Exists: true } built)
        {
            yield return built.Length.ToString();
            yield return built.LastWriteTimeUtc.Ticks.ToString();
        }
    }
}
