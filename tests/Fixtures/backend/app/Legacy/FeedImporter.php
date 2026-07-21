<?php

namespace Shop\Legacy;

final class FeedImporter
{
    public function import(string $feed): int
    {
        return strlen($feed);
    }
}
