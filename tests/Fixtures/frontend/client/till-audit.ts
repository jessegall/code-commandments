import type { Steps } from './cash-reconcile'

// @sin DivergentTwin
export function auditTill(job: Steps): void {
    job.openTill()
    job.countNotes()
    job.matchReceipts()
    job.closeDrawer()
    job.recordDifference()
}
