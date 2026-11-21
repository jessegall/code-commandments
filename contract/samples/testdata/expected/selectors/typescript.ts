import { Ledger } from "./ledger";

export class Account {
  private ledger: Ledger | null = null;

  settle(amounts: number[]): number {
    let total = 0;
    for (const amount of amounts) {
      if (amount > 0 && amount < 100) {
        total += amount;
        continue;
      }
      break;
    }
    try {
      this.ledger?.record(total);
    } catch (error) {
      throw new Error("not recorded");
    }
    return total;
  }
}
