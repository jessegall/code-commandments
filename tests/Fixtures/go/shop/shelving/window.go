package shelving

type Window struct {
	opens, closes int
}

func Between(opens, closes int) Window {
	return Window{opens: min(opens, closes), closes: max(opens, closes)}
}

func (w Window) Covers(hour int) bool {
	return hour >= w.opens && hour < w.closes
}

func (w Window) Hours() int {
	return w.closes - w.opens
}
