package shop

// Tier is what a customer is charged by.
type Tier struct {
	Name     string
	Discount int
	Bands    map[string]int
}

// Pricing holds what each tier pays for each band.
type Pricing struct {
	tiers []Tier
}

// Quote is what the bands come to for the tier named, in cents.
func (p *Pricing) Quote(name string, bands []string, hours map[string]int) int {
	total := 0
	for _, tier := range p.tiers {
		if tier.Name == name {
			for _, band := range bands {
				// @sin DeepNesting
				if rate, held := tier.Bands[band]; held {
					total += rate * hours[band]
				}
			}
		}
	}

	return total
}
