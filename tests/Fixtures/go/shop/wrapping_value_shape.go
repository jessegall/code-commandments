package shop

import "strings"

var wrappingCount []int

func wrappingTick(width int) {
	if width > 0 {
		// @sin MutableStaticState
		wrappingCount = append(wrappingCount, width)
	}
}

func wrappingTicket(
	// @sin ArrayBag
	labels map[string]string,
) []string {
	var printed []string
	for repeat := 0; repeat < len(labels["store"]); repeat++ {
		printed = append(printed, labels["material"]+" "+labels["grade"])
	}
	return printed
}

func wrappingText(
	// @sin ArrayBagParameter
	fields map[string]string,
	fixed string,
) string {
	var label strings.Builder
	label.WriteString(fixed)
	for key, field := range fields {
		label.WriteByte(' ')
		label.WriteString(key + "=" + field)
	}
	return label.String()
}

func wrappingTypes(print func(string)) {
	print(wrappingText(map[string]string{"paper": "kraft", "ink": "green"}, "parcel"))
	print(wrappingText(map[string]string{"paper": "linen", "ink": "silver"}, "gift"))
}

func wrappingTints(material string) map[string]string {
	colour := strings.ToLower(material)
	// @sin ArrayReturnBag
	return map[string]string{"ink": colour, "thread": colour + "-thread"}
}

type WrappingMaterial struct {
	rolls  []string
	tape   string
	marker string
}

// @sin FeatureEnvy
func wrappingDescription(material WrappingMaterial) string {
	caption := material.marker + ":" + material.tape
	for _, roll := range material.rolls {
		caption += " " + roll
	}
	return caption
}

// @sin KeyedLookupEnvy
func colouredTape(material WrappingMaterial, shades map[string]uint16, _ int) uint16 {
	return shades[material.tape]
}

type WrappingShapePrinter struct{}
type WrappingShapeValidator struct{}

// @sin DataClump
func (WrappingShapePrinter) Measure(height int, width int, depth int) int {
	area := height*width + width*depth + depth*height
	return area * 2
}

// @sin DataClump
func (WrappingShapeValidator) Fits(height int, width int, depth int) bool {
	return height < width+5 && depth > 0
}

// The receipt's copies, counter and store travel here too: one group across two files.
// @sin DataClump
func (WrappingShapePrinter) Slip(copies int, counter int, store string) string {
	return store + " wrapped for counter " + strings.Repeat("|", counter) + strings.Repeat("*", copies)
}

type WrappingShapeState struct {
	width   int
	checked bool
}

// @sin CoupledFields
func (state *WrappingShapeState) Accept(width int, checked bool) bool {
	state.width = width
	state.checked = checked
	if !state.checked && state.width > 0 {
		return false
	}
	return state.checked
}

// @sin BareStatePredicate
func (state WrappingShapeState) Opens() bool {
	return !state.checked || state.width < 12
}

// @sin NarratedCommand
func (state *WrappingShapeState) Packs() {
	if state.width < 1 {
		return
	}
	state.checked = true
}

// @sin ScratchStateRestore
func (state *WrappingShapeState) ProbeWrap(defaultWidth int) bool {
	current := state.width
	state.width = defaultWidth
	available := state.checked && state.width < 12
	state.width = current
	return available
}

type WrappingShapeValue struct {
	width     int
	height    int
	trimmings []int
}

func (v WrappingShapeValue) Equals(other WrappingShapeValue) bool {
	if v.width != other.width {
		return false
	}
	return v.height == other.height
}

// @sin MutableValueObject
func (v *WrappingShapeValue) ApplyMargins(margins []int) {
	for _, margin := range margins {
		v.height -= margin
	}
	v.trimmings = margins
}

type WrappingShapeShelf struct {
	tags      []int
	printed   map[int]bool
	nextLabel int
}

func (s WrappingShapeShelf) AllTags() []int {
	// @sin HandedOutCollection
	return s.tags
}

func (s *WrappingShapeShelf) MarkPrinted(labels []int) {
	for _, label := range labels {
		if s.printed[label] {
			continue
		}
		s.printed[label] = true
		s.nextLabel = max(s.nextLabel, label+1)
	}
}
