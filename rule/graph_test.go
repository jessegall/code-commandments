package rule

import (
	"sync"
	"testing"
)

func TestARunBuildsTheGraphOfOneRowListOnce(t *testing.T) {
	var rows []value
	for _, edge := range [][2]string{{"a", "b"}, {"b", "c"}, {"c", "d"}} {
		rows = append(rows, known(record{"from": known(edge[0]), "to": known(edge[1])}))
	}
	asked := evaluation{graphs: &sync.Map{}}
	before := graphsBuilt.Load()

	var reached []string
	for range 50 {
		edges, read := asked.edgesOf(known(rows))
		if !read {
			t.Fatal("the rows did not read as edges")
		}
		reached = reachedFrom(edges, known("a").String(), 0)
	}

	if built := graphsBuilt.Load() - before; built != 1 {
		t.Errorf("fifty asks over one row list built %d graphs, not one", built)
	}
	if len(reached) != 3 {
		t.Errorf("a reaches %v, not b, c and d", reached)
	}
}
