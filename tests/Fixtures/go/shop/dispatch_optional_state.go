package shop

import (
	"bytes"
	"encoding/json"
)

type DispatchResult struct{ Code string }

type DispatchState struct {
	// @sin PhantomNullable
	Active    *DispatchResult
	LoadOrder []string
}

func readDispatch(data []byte) (map[string]any, error) {
	manifest := make(map[string]any)
	if err := json.NewDecoder(bytes.NewReader(data)).Decode(&manifest); err != nil {
		return nil, err
	}
	// @sin RawDecodedArrayReturn
	return manifest, nil
}

// @fixed RawDecodedArrayReturn
func decodeDispatch(data []byte) (DispatchResult, error) {
	var result DispatchResult
	err := json.Unmarshal(data, &result)
	return result, err
}

type DispatchSender interface {
	Envelope(destination string, weight int) []byte
}
type DispatchReviewer interface{ Permitted(route []string) bool }
type DispatchCarrier interface{ Legs(stops []string) []int }

// @sin CapabilityProbeHub
type DispatchProbe struct {
	candidate any
	routes    map[string][]string
}

func (pack DispatchProbe) PackEnvelope(destination string, ounces int) []byte {
	if transport, supported := pack.candidate.(DispatchSender); supported {
		return transport.Envelope(destination, ounces)
	}
	return []byte{}
}

func (pack DispatchProbe) Approved(destination string) bool {
	if inspector, ready := pack.candidate.(DispatchReviewer); ready {
		return inspector.Permitted(pack.routes[destination])
	}
	return false
}

func (pack DispatchProbe) DispatchLegs(staging []string) []int {
	if itinerary, known := pack.candidate.(DispatchCarrier); known {
		return itinerary.Legs(staging)
	}
	return []int{}
}
