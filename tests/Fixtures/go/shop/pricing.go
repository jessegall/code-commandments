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

// Banded is what each band costs the tier named, by band.
func (p *Pricing) Banded(name string, bands []string) map[string]int {
	costs := map[string]int{}
	for _, tier := range p.tiers {
		// @sin RedundantElse
		if tier.Name != name {
			continue
		} else {
			for _, band := range bands {
				costs[band] = tier.Bands[band] - tier.Discount
			}
		}
	}

	return costs
}

// Hours adds up the hours each band was worked, counting only the bands a tier charges for.
func (p *Pricing) Hours(tier Tier, hours map[string]int) int {
	worked := 0
	for band, count := range hours {
		// @sin LoopWrappedInIf
		if tier.Bands[band] > 0 {
			worked += count
			delete(hours, band)
		}
	}

	return worked
}
