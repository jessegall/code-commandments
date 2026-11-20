package backend

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/detectors/backend/spatie"
)

func TestAllNullableDataReadsDeclaredAndInheritedPublicFields(t *testing.T) {
	codebase := phpProject(t, `<?php
namespace Spatie\LaravelData {
    abstract class Data {}
}
namespace Examples {
    use Spatie\LaravelData\Data;
    final class Address extends Data {
        public ?string $label;
        public ?string $street;
    }
    final class Page extends Data {
        public bool $available = true;
        public function __construct(public ?string $label = null) {}
    }
    trait Identified {
        public string $key;
    }
    abstract class Row extends Data {
        use Identified;
    }
    final class NamedRow extends Row {
        public function __construct(public ?string $label = null) {}
    }
}
`)
	var classes []string
	for _, finding := range (spatie.AllNullableDataDetector{}).Find(codebase) {
		classes = append(classes, finding.Name())
	}
	if !slices.Equal(classes, []string{"Address"}) {
		t.Fatalf("reported classes %v", classes)
	}
}

func TestAllNullableDataRespectsNullAndOmissionContracts(t *testing.T) {
	codebase := phpProject(t, `<?php
namespace Spatie\LaravelData {
    abstract class Data {}
    final class Optional {}
}
namespace Examples {
    use Spatie\LaravelData\Data;
    use Spatie\LaravelData\Optional;

    final class ClearCustomer extends Data {
        public function __construct(public ?string $customerId) {}
    }
    final class FormContext extends Data {
        public ?string $shopId;
    }
    final class UpdateAddress extends Data {
        public string | null | Optional $street;
        public string | null | Optional $city;
    }
    final class FormDefaults extends Data {
        public function __construct(public ?string $officeId = null) {}
    }
    class SourceRate extends Data {
        public ?float $amount = null;
        public ?string $unit = null;
    }
    final class DeliveryRate extends SourceRate {}
    trait AddressFields {
        public ?string $street = null;
        public ?string $city = null;
    }
    final class Address extends Data {
        use AddressFields;
    }
}
`)
	var classes []string
	for _, finding := range (spatie.AllNullableDataDetector{}).Find(codebase) {
		classes = append(classes, finding.Name())
	}
	want := []string{"FormDefaults", "SourceRate", "Address"}
	if !slices.Equal(classes, want) {
		t.Fatalf("reported classes %v, want %v", classes, want)
	}
}
