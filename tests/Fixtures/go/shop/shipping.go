package shop

// Parcel is a box leaving the warehouse.
type Parcel struct {
	Zone   int
	Grams  int
	Labels []string
}

// Postage prices a parcel by the zone it travels to.
func Postage(parcel Parcel) int {
	cents := 0
	// @sin SubjectLadder
	if parcel.Zone == 1 {
		cents = 450
	} else if parcel.Zone == 2 {
		cents = 700
	} else if parcel.Zone == 3 {
		cents = 1150
	} else if parcel.Zone == 4 {
		cents = 1900
	} else {
		cents = 2500
	}
	if parcel.Grams > 2000 {
		cents += parcel.Grams / 10
	}

	return cents
}

// Label names a parcel heavy or light on its last label.
func Label(parcel Parcel) Parcel {
	// @sin DuplicatedBranchTail
	if parcel.Grams > 2000 {
		parcel.Labels = append(parcel.Labels, "heavy")
		parcel.Zone = normalZone(parcel.Zone)
	} else if parcel.Grams > 500 {
		parcel.Labels = append(parcel.Labels, "medium")
		parcel.Zone = normalZone(parcel.Zone)
	} else {
		parcel.Zone = normalZone(parcel.Zone)
	}

	return parcel
}

// @fixed DuplicatedBranchTail

// LabelOnce labels by weight and settles the zone once, after the branches.
func LabelOnce(parcel Parcel) Parcel {
	if parcel.Grams > 2000 {
		parcel.Labels = append(parcel.Labels, "heavy")
	} else if parcel.Grams > 500 {
		parcel.Labels = append(parcel.Labels, "medium")
	}
	parcel.Zone = normalZone(parcel.Zone)

	return parcel
}

func normalZone(zone int) int {
	if zone < 1 {
		return 1
	}

	return zone
}

// Sorted counts the parcels a belt can take, skipping the heavy ones.
func Sorted(parcels []Parcel) int {
	taken := 0
	for _, parcel := range parcels {
		if parcel.Grams > 30000 {
			continue
			// @sin UnreachableStatement
			taken--
		}
		taken++
	}

	return taken
}

// @righteous DuplicatedBranchTail

// Weigh has no else, so the zone line after the if is not a tail every branch repeats.
func Weigh(parcel Parcel) Parcel {
	if parcel.Grams > 2000 {
		parcel.Labels = append(parcel.Labels, "heavy")
	}
	parcel.Zone = normalZone(parcel.Zone)

	return parcel
}
