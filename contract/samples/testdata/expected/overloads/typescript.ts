export function parse(text: string): number;
export function parse(text: number): number;
export function parse(text: string | number): number {
  return Number(text);
}

parse("1");
