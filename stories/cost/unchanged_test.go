//go:build story

package cost

import (
	"fmt"
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

func TestJudgingACheckoutWhereNothingChangedReadsNoTree(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	if judged.Code != 0 {
		s.Fails("the judging failed: " + judged.Err)
	}

	if started := s.Cost(ledger.TreeReadsStarted); started != 0 {
		s.Fails(fmt.Sprintf("judging a checkout where nothing changed started %d reads of the whole tree, where the "+
			"tree the service already holds answers it", started))
	}

	if declined := s.Cost(ledger.JudgingsDeclined); declined != 0 {
		s.Fails(fmt.Sprintf("the service declined %d judgings, so the run read the project itself", declined))
	}
}
