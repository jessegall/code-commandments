package shop

import "strings"

func (state DispatchState) Value() string {
	return strings.TrimSpace(state.Active.Code)
}

func (state DispatchState) LoadingOrder() string {
	return strings.Join(state.LoadOrder, " → ")
}
