<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\VendorPath;
use PHPUnit\Framework\TestCase;

class VendorPathTest extends TestCase
{
    public function test_composer_optimized_autoloader_app_path_is_not_vendor(): void
    {
        // The exact shape Composer's optimized autoloader returns for an APP
        // class — it contains a literal `/vendor/` but resolves to the project
        // root. This is the bug that silenced StringsThatShouldBeEnums.
        $this->assertFalse(
            VendorPath::isVendor('/Users/me/project/vendor/composer/../../src/Pipeline/Ports/DataPort.php')
        );
    }

    public function test_real_vendor_path_is_vendor(): void
    {
        $this->assertTrue(
            VendorPath::isVendor('/Users/me/project/vendor/jessegall/php-types/src/T_String.php')
        );
    }

    public function test_composer_optimized_autoloader_vendor_path_is_vendor(): void
    {
        // A genuine third-party class via the optimized autoloader.
        $this->assertTrue(
            VendorPath::isVendor('/Users/me/project/vendor/composer/../jessegall/php-types/src/T_String.php')
        );
    }

    public function test_plain_app_path_is_not_vendor(): void
    {
        $this->assertFalse(VendorPath::isVendor('/Users/me/project/src/Foo.php'));
    }

    public function test_windows_separators_are_normalised(): void
    {
        $this->assertFalse(VendorPath::isVendor('C:\\project\\vendor\\composer\\..\\..\\src\\Foo.php'));
        $this->assertTrue(VendorPath::isVendor('C:\\project\\vendor\\acme\\pkg\\src\\Bar.php'));
    }

    public function test_directory_named_like_vendor_is_not_a_false_positive(): void
    {
        // `/myvendor/` must not match `/vendor/`.
        $this->assertFalse(VendorPath::isVendor('/Users/me/myvendor/src/Foo.php'));
    }
}
