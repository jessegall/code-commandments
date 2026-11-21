package engine

func HasTemplateLines(lines []Match) bool {
	fixed := 0
	for _, line := range lines {
		if line.LiteralKind() == "string" {
			fixed++
		}
	}
	return HasTemplateLayout(len(lines), fixed)
}

func HasTemplateLayout(lines, written int) bool {
	return lines >= 3 && written >= 2
}
