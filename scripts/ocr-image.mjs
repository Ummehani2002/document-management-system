import { createWorker } from 'tesseract.js';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const imagePath = process.argv[2];
if (!imagePath || !fs.existsSync(imagePath)) {
    process.stderr.write('Usage: node scripts/ocr-image.mjs <image>\n');
    process.exit(2);
}

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const cachePath = path.join(root, 'storage', 'app', 'tessdata-cache');
fs.mkdirSync(cachePath, { recursive: true });

const worker = await createWorker('eng', 1, {
    cachePath,
});

try {
    const { data } = await worker.recognize(imagePath);
    process.stdout.write(String(data?.text ?? ''));
} finally {
    await worker.terminate();
}
