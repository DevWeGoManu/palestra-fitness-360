import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const files = ['.htaccess'];

for (const file of files) {
  const source = resolve(root, 'public', file);
  const target = resolve(root, 'dist', file);
  mkdirSync(dirname(target), { recursive: true });
  copyFileSync(source, target);
}
