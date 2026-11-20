package backend

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/detectors/backend/spatie"
)

func TestAllOptionalDataReadsFieldsAndPreservesSparseUpdates(t *testing.T) {
	codebase := phpProject(t, `<?php
namespace Spatie\LaravelData {
    abstract class Data {}
    final class Optional {}
}
namespace Examples {
    use Spatie\LaravelData\Data;
    use Spatie\LaravelData\Optional;

    final class Address extends Data {
        public string | Optional $street;
        public string | Optional $city;
    }
    trait Identified {
        public string $id;
    }
    final class Row extends Data {
        use Identified;
        public function __construct(public string | Optional $label) {}
    }
    final class Settings extends Data {
        public function __construct(
            public bool | Optional $active = new Optional(),
            public bool | Optional $visible = new Optional(),
        ) {}
    }
    final class Style extends Data {
        public function __construct(
            public string | Optional $font = new Optional(),
            public string | Optional $color = new Optional(),
        ) {}
    }
    final class Theme extends Data {
        public string | Optional $variant;
    }
    final class SettingsWriter {
        /** @param Settings $patch */
        public function apply(Data $patch): void {
            $this->store->update(function (Configuration $config) use ($patch): void {
                if (!$patch->active instanceof Optional) {
                    $config->setActive($patch->active);
                }
                if (!$patch->visible instanceof Optional) {
                    $config->setVisible($patch->visible);
                }
            });
        }

        public function render(Style $style): void {
            if (!$style->font instanceof Optional) {
                $this->setFont($style->font);
            }
            $this->setColor($style->color instanceof Optional ? 'black' : $style->color);
        }

        public function setTheme(Theme $theme): void {
            if (!$theme->variant instanceof Optional) {
                $this->setVariant($theme->variant);
            } else {
                $this->setVariant('light');
            }
        }
    }
}
`)
	var names []string
	for _, finding := range (spatie.AllOptionalDataDetector{}).Find(codebase) {
		names = append(names, finding.Name())
	}
	if want := []string{"Address", "Style", "Theme"}; !slices.Equal(names, want) {
		t.Fatalf("reported classes %v, want %v", names, want)
	}
}
