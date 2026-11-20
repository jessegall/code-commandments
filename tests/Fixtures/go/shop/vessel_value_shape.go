package shop

var vesselCount int

func vesselTick() {
	// @sin MutableStaticState
	vesselCount = vesselCount + 1
}

func vesselLine(
	// @sin ArrayBag
	fields map[string]string,
) string {
	return fields["name"] + fields["region"]
}

func vesselKeys(
	// @sin ArrayBagParameter
	fields map[string]string,
) int {
	if len(fields) == 0 {
		return 0
	}
	count := 0
	for _, name := range fields {
		if len(name) > 3 {
			count++
		}
	}
	return count
}

func vesselNameKeys() int {
	first := vesselKeys(map[string]string{"given": "Ada", "family": "King"})
	second := vesselKeys(map[string]string{"given": "Mary", "family": "Somerville"})
	return first + second
}

func vesselDefault(candidates <-chan string, region string) map[string]string {
	contact, exists := <-candidates
	if !exists {
		return nil
	}
	// @sin ArrayReturnBag
	return map[string]string{"contact": contact, "region": region, "manifest": region + "/" + contact}
}

type VesselShapeFields struct {
	name     string
	city     string
	postcode string
	stops    []string
	slot     int
}

// @sin FeatureEnvy
func vesselDescription(fields VesselShapeFields) string {
	display := fields.name + fields.city + fields.postcode
	for _, stop := range fields.stops {
		display += " / " + stop
	}
	return display
}

// @sin KeyedLookupEnvy
func vesselCarrier(manifest VesselShapeFields, departures []VesselDeparture) VesselDeparture {
	return departures[manifest.slot]
}

type VesselDeparture struct {
	Port      string
	Passenger bool
}

type VesselShapePrinter struct{}
type VesselShapeValidator struct{}

// @sin DataClump
func (VesselShapePrinter) Print(street string, district string, code string) string {
	return street + district + code
}

// @sin DataClump
func (VesselShapeValidator) Check(street string, district string, code string) bool {
	return street != district && len(code) > 1
}

type VesselShapeState struct {
	open   bool
	signed bool
}

// @sin CoupledFields
func (s *VesselShapeState) Accept(open bool, signed bool) bool {
	s.open = open
	s.signed = signed
	return s.open && s.signed
}

// @sin BareStatePredicate
func (s *VesselShapeState) Opens() bool {
	return s.open
}

// @sin NarratedCommand
func (s *VesselShapeState) Dispatches() {
	s.open = false
}

// @sin ScratchStateRestore
func (s *VesselShapeState) Probe(value bool) bool {
	saved := s.open
	s.open = value
	seen := s.open
	s.open = saved
	return seen
}

type VesselShapeValue struct {
	label string
	zone  string
}

func (v VesselShapeValue) Equals(other VesselShapeValue) bool {
	return v.label == other.label
}

// @sin MutableValueObject
func (v *VesselShapeValue) Move(zone string) {
	v.zone = zone
}

type VesselShapeShelf struct {
	manifest map[string][]string
	tags     []string
	open     chan<- string
}

func (s VesselShapeShelf) AllTags() []string {
	// @sin HandedOutCollection
	return s.tags
}

// @fixed HandedOutCollection
func (s VesselShapeShelf) TagSnapshot() []string {
	return append([]string(nil), s.tags...)
}

func (s VesselShapeShelf) Announce(port string) {
	for _, voyage := range s.manifest[port] {
		s.open <- voyage
	}
}
