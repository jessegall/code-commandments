export type Steps = {
    openTill(): void
    countNotes(): void
    matchReceipts(): void
    closeDrawer(): void
    recordDifference(): void
}

// @sin DivergentTwin
export function reconcileTill(job: Steps): void {
    job.openTill()
    job.countNotes()
    job.matchReceipts()
    job.closeDrawer()
}

// @righteous DivergentTwin
export function firstStep(job: Steps): void {
    void job
}
