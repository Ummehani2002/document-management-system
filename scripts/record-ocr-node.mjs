import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outDir = path.join(root, 'storage', 'app');
fs.mkdirSync(outDir, { recursive: true });
const outFile = path.join(outDir, 'ocr-node-path.txt');
fs.writeFileSync(outFile, process.execPath + '\n', 'utf8');
process.stdout.write(`Recorded OCR node binary: ${process.execPath}\n`);
