<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: No custom fromModel methods in Data classes - Laravel Data handles this automatically.
 */
class NoCustomFromModelProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Do not create custom fromModel methods in Data classes - use Data::from($model) instead';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never create custom fromModel() methods in Spatie Laravel Data classes.

Laravel Data automatically handles model-to-data transformation through
its built-in factory system. Using Data::from($model) will automatically
find the correct factory method and transform the model properly.

Bad:
    class ProductData extends Data
    {
        public static function fromModel(Product $product): self
        {
            return new self(
                id: $product->id,
                name: $product->name,
            );
        }
    }

Good:
    class ProductData extends Data
    {
        public function __construct(
            public readonly string $id,
            public readonly string $name,
        ) {}
    }

    // Usage: ProductData::from($product)
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Only check Data classes
        if (!str_contains($filePath, '/Data/') && !str_contains($filePath, '\\Data\\')) {
            return $this->righteous();
        }

        $sins = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            // Look for "fromModel" method definitions in Data classes
            if (preg_match('/public static function fromModel\(/', $line)) {
                $sins[] = $this->sinAt(
                    $lineNum + 1,
                    'Custom fromModel() method in Data class',
                    trim($line),
                    'Use Data::from($model) instead - Laravel Data handles model transformation automatically'
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
