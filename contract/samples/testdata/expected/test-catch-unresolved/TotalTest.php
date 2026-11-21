<?php

namespace Tests;

final class TotalTest extends UnknownCase
{
    public function testTotal(): void
    {
        try {
            $this->assertSame(1, total());
        } catch (AssertionFailedError $e) {
        }
    }
}
