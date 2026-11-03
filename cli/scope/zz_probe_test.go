package scope

import (
	"testing"
	"time"
)

func TestProbeNestedUnder(t *testing.T) {
	start := time.Now()
	found := nestedUnder("../..", nil)
	t.Logf("nestedUnder found %d repositories in %s", len(found), time.Since(start))
}
