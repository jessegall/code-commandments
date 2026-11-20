package interfaceoverlap

type ReceiptDelivery interface {
	SendReceipt([]byte, string) error
	SentReceipt(string) (bool, error)
}

// @sin InterfaceOverlap
type RemoteReceiptDelivery interface {
	SentReceipt(reference string) (found bool, err error)
	SendReceipt(body []byte, reference string) error
}

// @righteous InterfaceOverlap
type LocalReceiptDelivery interface {
	SendReceipt(string, string) error
	SentReceipt(string) (bool, error)
}
