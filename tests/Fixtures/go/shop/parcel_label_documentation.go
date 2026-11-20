package shop

import "strings"

// @sin BloatedDocblock
// ParcelLabel is the address placed on a shipping carton.
//
// The processing office may fold a separate copy into the packing slip.
type ParcelLabel interface {
	AddressLines() []string
	PostalCode() string
}

// @righteous BloatedDocblock
// ParcelLabelDraft keeps the address as entered at the counter.
type ParcelLabelDraft struct{ Destination []string }

// @sin DanglingDocReference
// formatParcelLabel uses the line order given by [MissingLabelPrinter].
func formatParcelLabel(label ParcelLabel, country string) string {
	lines := label.AddressLines()
	if country != "" {
		lines = append(append([]string(nil), lines...), country)
	}
	return strings.Join(lines, "\n")
}

// @fixed DanglingDocReference
// addressOnParcel renders the destination provided by [ParcelLabel].
func addressOnParcel(label ParcelLabel) string {
	return strings.Join(label.AddressLines(), "\n")
}

func replaceParcelLabel(boxes []ParcelLabelDraft, postalCodes <-chan string) int {
	// @sin ArchaeologyComment
	// This was refactored after the counter closed.
	for index := range boxes {
		// @sin NegativeSpaceComment
		// The scheduling lookup is not performed on purpose.
		code, ok := <-postalCodes
		if !ok {
			return index
		}
		boxes[index].Destination = append(boxes[index].Destination, code)
	}
	// @sin RestatedComment
	// Return len boxes.
	return len(boxes)
}
