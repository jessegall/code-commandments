// A different SHAPE of the same defence: not a returned chain but one buried in a condition and in
// a call argument, on a field the poller declares as always present.

interface Channel {
    id: string
    push(payload: string): void
}

export class StockPoller {
    private readonly channel: Channel = { id: '', push: () => {} }

    // @sin FalselyOptionalField
    private attempts?: number = 0

    private paused?: boolean

    poll(payload: string): void {
        // @sin DefendedCertainField
        if (this.channel?.id === '') {
            return
        }

        this.channel.push(payload)
    }

    resume(): void {
        // @righteous DefendedCertainField
        if (this.paused?.valueOf()) {
            this.channel.push('resume')
        }
    }
}
