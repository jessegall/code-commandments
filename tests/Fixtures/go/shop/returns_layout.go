package shop

import (
	"strconv"
	"strings"
)

func returnsCard(item Return, counter string) []byte {
	amount := strconv.Itoa(item.Cents)
	// @sin AssembledTemplate
	slip := strings.Join([]string{"RETURNS COPY", counter, "Reason", item.Reason, "Cents", amount}, "\n")
	return []byte(slip + "\n")
}
