package php_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/namespaces"
)

func TestEloquentFactoryBindingDoesNotCloseNamespaceCycle(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App\Models;
use App\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model {
    use HasFactory;
    protected static function newFactory(): CustomerFactory { return CustomerFactory::new(); }
}
namespace App\Factories;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
class CustomerFactory extends Factory {
    protected $model = Customer::class;
}
`)
	if cycle := namespaces.Of(codebase).ArrowsClosingAMutualPair(); len(cycle) != 0 {
		t.Fatalf("an Eloquent model-to-factory association closed a cycle: %v", cycle)
	}
}

func TestDocParagraphsExcludeMultilineTypeTags(t *testing.T) {
	const text = `/**
 * A checkpoint holds its read position.
 *
 * @phpstan-type Fields array{
 *   offset: int,
 *
 *   tags: list<string>,
 * }
 */`
	if paragraphs := php.DocParagraphs(text); paragraphs != 1 {
		t.Fatalf("type declarations do not add prose paragraphs, found %d", paragraphs)
	}
}

func TestDocParagraphsExcludeFencedExamples(t *testing.T) {
	const example = `/**
 * A checkout card shows the current delivery address.
 *
 * ~~~blade
 * @include('delivery.address')
 *
 * @include('delivery.phone')
 * ~~~`
	if paragraphs := php.DocParagraphs(example + "\n */"); paragraphs != 1 {
		t.Fatalf("a fenced example should not add a paragraph, found %d", paragraphs)
	}
	if paragraphs := php.DocParagraphs(example + "\n * The checkout validates the address before shipping.\n */"); paragraphs != 2 {
		t.Fatalf("prose after an example should still count, found %d", paragraphs)
	}
}

