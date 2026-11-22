export class OpeningWindow {
    private constructor(private readonly opens: number, private readonly closes: number) {}

    static between(opens: number, closes: number): OpeningWindow {
        return opens <= closes ? new OpeningWindow(opens, closes) : new OpeningWindow(closes, opens)
    }

    covers(hour: number): boolean {
        return hour >= this.opens && hour < this.closes
    }

    hours(): number {
        return this.closes - this.opens
    }
}
