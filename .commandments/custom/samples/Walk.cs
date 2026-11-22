using System.IO;

namespace Samples;

public static class Walk
{
    public static int Entries(string folder)
    {
        // @sin FolderWalkOutsideTheFunnelDetector
        return Directory.GetFiles(folder).Length;
    }

    public static string Child(string folder, string name)
    {
        // @righteous FolderWalkOutsideTheFunnelDetector
        return Path.Combine(folder, name);
    }
}
