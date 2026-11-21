<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

final class ReaderTest extends TestCase
{
    public function testItFails(): void
    {
        try {
            $this->fail('no failure');
        } catch (\RuntimeException $e) {
        }
    }
}
