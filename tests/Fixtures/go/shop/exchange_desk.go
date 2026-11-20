package shop

import "strings"

// @sin DuplicateFunction
func exchangeDeskLabel(title string, item Return, reference string) string {
	var label strings.Builder
	label.WriteString(strings.TrimSpace(title))
	label.WriteString(": ")
	label.WriteString(strings.ToUpper(reference))
	label.WriteString(" / ")
	label.WriteString(item.Reason)
	return label.String()
}

// @sin NearDuplicateFunction
func joinExchangeNotes(notices []string, desk string) string {
	var printed strings.Builder
	printed.WriteString(desk + " exchange")
	for _, notice := range notices {
		printed.WriteString(" / ")
		printed.WriteString(strings.TrimSpace(notice))
	}
	return printed.String()
}
