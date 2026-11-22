<?php

namespace Samples;

final class Walk
{
    public function entries(string $folder): int
    {
        // @sin FolderWalkOutsideTheFunnelDetector
        return count(scandir($folder));
    }

    public function iterated(string $folder): int
    {
        // @sin FolderWalkOutsideTheFunnelDetector
        return iterator_count(new \RecursiveDirectoryIterator($folder));
    }

    public function child(string $folder, string $name): string
    {
        // @righteous FolderWalkOutsideTheFunnelDetector
        return $folder.'/'.basename($name);
    }
}
