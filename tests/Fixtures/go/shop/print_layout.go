package shop

import (
	"fmt"
	"strings"
)

// @righteous AssembledTemplate
func printCategory(name string) string {
	return strings.Join([]string{"Receipt", name, "Customer"}, ": ")
}

// @fixed AssembledTemplate
func printReceiptCard(name string) string {
	return fmt.Sprintf(`Receipt:
%s
Customer copy.`, name)
}
