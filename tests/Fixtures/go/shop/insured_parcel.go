package shop

// @sin DivergentTwin
func stageInsuredParcel(work ParcelWork) {
	work.Weigh()
	work.Wrap()
	work.Label()
	work.Stage()
	work.Insure()
}
