package shop

import "strings"

func deliveryCard(stops []string, signed bool) string {
	signature := "Waiting for signature"
	if signed {
		signature = "Signed by recipient"
	}
	var route strings.Builder
	for index, stop := range stops {
		if index > 0 {
			route.WriteString(" → ")
		}
		route.WriteString(stop)
	}
	// @sin AssembledTemplate
	return strings.Join([]string{"Delivery manifest", route.String(), "Signature", signature}, "\n")
}
