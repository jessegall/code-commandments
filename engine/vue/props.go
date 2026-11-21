package vue

func (c Component) Elements() []Element {
	var elements []Element
	for _, node := range c.Template().Descendants() {
		if element := Of(node); element.IsElement() {
			elements = append(elements, element)
		}
	}

	return elements
}
