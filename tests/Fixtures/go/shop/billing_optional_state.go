package shop

import "encoding/json"

type BillingResult struct{ Memo string }

type BillingState struct {
	Active *BillingResult
}

func (state BillingState) Value() string { return state.Active.Memo }

func readBilling(invoices [][]byte, record func(int)) (any, error) {
	for number, invoice := range invoices {
		if len(invoice) == 0 {
			continue
		}
		var item any
		if err := json.Unmarshal(invoice, &item); err != nil {
			return nil, err
		}
		record(number)
		// @sin RawDecodedArrayReturn
		return item, nil
	}
	return nil, nil
}

// @fixed RawDecodedArrayReturn
func decodeBilling(data []byte) (BillingResult, error) {
	var result BillingResult
	err := json.Unmarshal(data, &result)
	return result, err
}

type BillingReady interface{ Settled() bool }
type BillingPriority interface{ Amount() int }
type BillingNote interface{ Describe() string }

// @sin CapabilityProbeHub
type BillingProbe struct{ provider any }

func (probe BillingProbe) Check() bool {
	if value, ok := probe.provider.(BillingReady); ok {
		return value.Settled()
	}
	return false
}

func (probe BillingProbe) Rank() int {
	if value, ok := probe.provider.(BillingPriority); ok {
		return value.Amount()
	}
	return 0
}

func (probe BillingProbe) Explain() string {
	if value, ok := probe.provider.(BillingNote); ok {
		return value.Describe()
	}
	return ""
}
