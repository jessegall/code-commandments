type Steps = {
    scanCrate(): void
    unpack(): void
    checkQuantity(): void
    updateStock(): void
    notifyBuyer(): void
}

// @sin DivergentTwin
export function receiveStock(job: Steps): void {
    job.scanCrate()
    job.unpack()
    job.checkQuantity()
    job.updateStock()
}

// @sin DivergentTwin
export function receiveOrderStock(job: Steps): void {
    job.scanCrate()
    job.unpack()
    job.checkQuantity()
    job.updateStock()
    job.notifyBuyer()
}

// @righteous DivergentTwin
export function firstStep(job: Steps): void {
    void job
}
