import { start } from './helper' /* @contract kind/file */ /* @contract kind/module */ /* @contract role/file/modules -> themodule */ /* @label themodule module */ /* @contract role/module/imports -> helperimport */ /* @label helperimport import */ /* @contract role/module/declarations -> [mainfn, appclass] */ /* @contract role/module/statements -> startcall */

// @label mainfn function
export function main(): void {}

// @label appclass type
export class App {}

// @label startcall expression-statement
start()
