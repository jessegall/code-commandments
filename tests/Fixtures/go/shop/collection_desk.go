package shop

import "strings"

// @sin DuplicateFunction
func collectionDeskLabel(title string, item Return, reference string) string {
	var label strings.Builder
	label.WriteString(strings.TrimSpace(title))
	label.WriteString(": ")
	label.WriteString(strings.ToUpper(reference))
	label.WriteString(" / ")
	label.WriteString(item.Reason)
	return label.String()
}

// @sin NearDuplicateFunction
func joinCollectionNotes(notes []string, counter string) string {
	var printed strings.Builder
	printed.WriteString(counter + " collection")
	for _, note := range notes {
		printed.WriteString(" | ")
		printed.WriteString(strings.TrimSpace(note))
	}
	return printed.String()
}
