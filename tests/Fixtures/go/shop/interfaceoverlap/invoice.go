package interfaceoverlap

type InvoiceTotals interface {
	Net(invoice int) int
	Taxes(invoice int) map[string]int
}

// @sin InterfaceOverlap
type LabeledInvoiceTotals interface {
	Taxes(number int) map[string]int
	Label(number int) string
	Net(number int) int
}

// @fixed InterfaceOverlap
type NamedInvoiceTotals interface {
	InvoiceTotals
	Label(invoice int) string
}
