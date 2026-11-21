export async function* flags(first?: string, ...rest: number[]): AsyncGenerator<number> {
  const limit = 3;
  for (let i = 0; i < limit; i++) {
    yield i;
  }
  show(...rest);
  await done();
}
