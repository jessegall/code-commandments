import { Base } from './one'

export class Job extends Base {
  run(): void {} // @contract promise/obligation-across-files -> one.ts#base
}
