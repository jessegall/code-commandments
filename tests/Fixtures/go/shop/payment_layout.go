package shop

import "strings"

func paymentCard(name string) string {
	// @sin AssembledTemplate
	return strings.Join([]string{"payment report:", name, "Thank you."}, "\n")
}
