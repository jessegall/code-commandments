package php

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/internal/shop"
)

func TestTheTraceFollowsEveryVariableAsPhpDoes(t *testing.T) {
	shop.Parity(t, "traces", func(_ shop.Answer, variable engine.Match) any {
		journey := [][]any{}
		for _, interaction := range Trace(variable) {
			journey = append(journey, []any{interaction.Node.Node().Span.Start, interaction.Kind, interaction.IsWrite(), interaction.Kind.DeNulls()})
		}

		return journey
	})
}
