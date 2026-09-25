package backend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var ternaryStatement = scribeCase{rules.TernaryStatementDetector{}, TernaryStatementScribe{}}

func TestTernaryStatementUnfoldsAStatementTernaryIntoAnIfElse(t *testing.T) {
	source := `<?php

class Tree
{
    public function collect(array $children): array
    {
        $gone = [];

        foreach ($children as $child) {
            $this->holds($child->id)
                ? array_push($gone, ...$this->collect($child->kids))
                : $gone[] = $child->id;
        }

        return $gone;
    }
}`

	fixed := ternaryStatement.fixStable(t, source)

	if !strings.Contains(fixed, `            if ($this->holds($child->id)) {
                array_push($gone, ...$this->collect($child->kids));
            } else {
                $gone[] = $child->id;
            }`) {
		t.Fatalf("%s", fixed)
	}
}

func TestTernaryStatementAShortTernaryHasNoThenBranchToReEvaluate(t *testing.T) {
	source := `<?php

class Tree
{
    public function warn(?string $name): void
    {
        $name ?: $this->complain();
    }
}`

	fixed := ternaryStatement.fixStable(t, source)

	if !strings.Contains(fixed, `        if (! $name) {
            $this->complain();
        }`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `else`) {
		t.Fatalf("%s", fixed)
	}
}

func TestTernaryStatementStandsTheElseOnItsOwnLineWhenTheFileDoes(t *testing.T) {
	source := `<?php

class Tree
{
    public function collect(array $children, array $gone): array
    {
        foreach ($children as $child)
        {
            $this->holds($child->id) ? $this->keep($child) : $gone[] = $child->id;
        }

        return $gone;
    }
}`

	fixed := ternaryStatement.fixStable(t, source)

	if !strings.Contains(fixed, `            if ($this->holds($child->id))
            {
                $this->keep($child);
            }
            else
            {
                $gone[] = $child->id;
            }`) {
		t.Fatalf("%s", fixed)
	}
}

func TestTernaryStatementLeavesATernaryWhoseValueIsRead(t *testing.T) {
	source := `<?php

class Tree
{
    public function label(array $row): string
    {
        return $row['name'] ? $row['name'] : 'anonymous';
    }
}`

	if ternaryStatement.rewrote(t, source) {
		t.Fatal("expected false")
	}
}
