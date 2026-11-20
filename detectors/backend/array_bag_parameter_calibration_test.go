package backend

import (
	"slices"
	"testing"
)

func TestArrayBagParameterFollowsReturnedRecordsAndKeepsOpenMaps(t *testing.T) {
	codebase := phpProject(t, `<?php
final class QueryBuilder {
    public function query(string | array $key, mixed $value = null): self {
        if (is_array($key)) {
            foreach ($key as $name => $item) {
                $this->query($name, $item);
            }
        }
        return $this;
    }
}
final class Endpoint {
    public function __construct(private QueryBuilder $builder) {}

    public function list(array $options): QueryBuilder {
        return $this->builder->query(array_merge(['limit' => 1], $options));
    }

    public function search(): QueryBuilder {
        return $this->list(['sku' => 'a', 'limit' => 4]);
    }
}
final class ConflictIndex {
    public function check(): void {
        $index = $this->build();
        $this->find($index);
        $this->report($this->build());
        $this->reportRows($this->rows());
    }

    private function build(): array {
        return ['values' => [], 'updates' => [], 'creates' => []];
    }

    private function find(array $index): void { $this->sink($index['values']); }

    private function report(array $index): void { $this->sink($index['creates']); }

    private function rows(): array {
        return array_map(function (int $id): array {
            return ['id' => $id, 'name' => 'example'];
        }, [1, 2]);
    }

    private function reportRows(array $rows): void { $this->sink($rows); }

    private function sink(mixed $value): void {}
}
`)
	var methods []string
	for _, match := range (ArrayBagParameterDetector{}).Find(codebase) {
		methods = append(methods, match.DeclaringFunction().Name())
	}
	slices.Sort(methods)
	if want := []string{"find", "report"}; !slices.Equal(methods, want) {
		t.Fatalf("reported methods %v, want %v", methods, want)
	}
}
