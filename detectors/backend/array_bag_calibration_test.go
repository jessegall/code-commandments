package backend

import (
	"slices"
	"testing"
)

func TestArrayBagReadsNullableFieldsAndRespectsWireDecoding(t *testing.T) {
	codebase := phpProject(t, `<?php
namespace Spatie\LaravelData {
    abstract class Data {}
}
namespace Illuminate\Http {
    class Request {
        public function input(): array { return []; }
    }
}
namespace Illuminate\Database\Eloquent\Factories {
    abstract class Factory {
        public function state(callable $callback): static { return $this; }
    }
}
namespace Illuminate\Contracts\Database\Eloquent {
    interface CastsAttributes {
        public function get($model, string $key, mixed $value, array $attributes);
    }
}
namespace Examples {
    use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
    use Illuminate\Database\Eloquent\Factories\Factory;
    use Illuminate\Http\Request;
    use Spatie\LaravelData\Data;

    final class RemoteDetail extends Data {}
    final class WebhookDetail extends Data {}

    final class Adapter {
        public function read(Request $request): Data {
            $payload = $request->input();
            $payload = $this->canonicalize($payload);
            if ($this->usesWebhookShape($payload)) {
                return WebhookDetail::from($payload);
            }
            return RemoteDetail::from($payload);
        }

        private function canonicalize(array $payload): array {
            $payload['image'] = $payload['images'][0] ?? null;
            unset($payload['images']);
            return $payload;
        }

        private function usesWebhookShape(array $payload): bool {
            return isset($payload['wireId']);
        }

        public function image(array | null $image): string {
            return $image['src'] ?? '';
        }

        public function user(array | string | null $user): string {
            return is_string($user) ? $user : ($user['name'] ?? '');
        }

        public function cast(): object {
            return new class implements CastsAttributes {
                public function get($model, string $key, mixed $value, array $attributes): mixed {
                    return $attributes['id'];
                }
            };
        }
    }

    final class RowFactory extends Factory {
        public function archived(): static {
            return $this->state(fn (array $row) => ['source' => $row['id']]);
        }
    }
}
`)
	var reads []string
	for _, match := range (ArrayBagDetector{}).Find(codebase) {
		reads = append(reads, match.Written())
	}
	if want := []string{"$image['src']"}; !slices.Equal(reads, want) {
		t.Fatalf("reported reads %v, want %v", reads, want)
	}
}
