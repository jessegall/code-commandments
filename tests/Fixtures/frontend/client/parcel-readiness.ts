type Steps = {
    weigh(): void
    wrap(): void
    label(): void
    stage(): void
    insure(): void
}

// @sin DivergentTwin
export function dispatchParcel(job: Steps): void {
    job.weigh()
    job.wrap()
    job.label()
    job.stage()
}

// @sin DivergentTwin
export function dispatchInsuredParcel(job: Steps): void {
    job.weigh()
    job.wrap()
    job.label()
    job.stage()
    job.insure()
}

// @fixed DivergentTwin
export function sendInsuredParcel(job: Steps): void {
    dispatchInsuredParcel(job)
}
