package backend

import (
	"slices"
	"testing"
)

func TestAssembledTemplateDistinguishesStanzasFromComputedLists(t *testing.T) {
	codebase := phpProject(t, `<?php
final class ChartHelp {
    public function layout(array $charts): string {
        $lines = [];
        foreach ($charts as $name => $chart) {
            $lines[] = "{$name} - {$chart->summary}";
            $lines[] = "  Example: {$chart->syntax}";
            $lines[] = '  Supports: ' . implode(', ', $chart->fields);
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    public function listing(array $charts): string {
        $lines = [];
        foreach ($charts as $name => $chart) {
            $lines[] = "{$name} - {$chart->summary}";
        }
        return implode("\n", $lines);
    }

    public function replace(array $charts): string {
        $lines = ['Title', 'Fields', 'Summary'];
        $lines = array_map(fn ($chart) => $chart->summary, $charts);
        return implode("\n", $lines);
    }

    public function inline(string $note): string {
        return join(PHP_EOL, ['Chart', $note, 'End']);
    }
}
`)
	var methods []string
	for _, match := range (AssembledTemplateDetector{}).Find(codebase) {
		methods = append(methods, match.EnclosingFunction().Name())
	}
	if want := []string{"layout", "inline"}; !slices.Equal(methods, want) {
		t.Fatalf("reported methods %v, want %v", methods, want)
	}
}
