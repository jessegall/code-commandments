package php

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/internal/shop"
)

func TestTheCallGraphFindsTheCallersPhpFinds(t *testing.T) {
	index := IndexOf(shop.Codebase(t))
	shop.Parity(t, "callers", func(_ shop.Answer, method engine.Match) any {
		callers := [][]any{}
		for _, call := range index.CallersOf(EnclosingClassName(method), method.Name()) {
			callers = append(callers, []any{strings.TrimPrefix(call.File(), shop.Root+"/"), call.Node().Span.Start, call.Kind()})
		}

		return callers
	})
}
