<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\ExecutableFinder;

class PrepareOcrCommand extends Command
{
    protected $signature = 'documents:prepare-ocr';

    protected $description = 'Copy a Node binary into bin/ so scanned-PDF OCR works after deploy.';

    public function handle(): int
    {
        $binDir = base_path('bin');
        if (! is_dir($binDir)) {
            mkdir($binDir, 0755, true);
        }

        $target = $binDir.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'runtime-node.exe' : 'runtime-node');
        $finder = new ExecutableFinder();
        $node = $finder->find('node') ?: $finder->find('node.exe');

        if (! $node) {
            $this->warn('node was not found on PATH. tesseract.js OCR will be skipped unless OCR_NODE_BINARY is set.');

            return self::SUCCESS;
        }

        if (! copy($node, $target)) {
            $this->error('Could not copy node to '.$target);

            return self::FAILURE;
        }

        @chmod($target, 0755);
        $this->info('Copied Node to '.$target);

        $js = base_path('node_modules/tesseract.js/package.json');
        if (! is_file($js)) {
            $this->warn('tesseract.js is not installed. Run: npm install');
        }

        return self::SUCCESS;
    }
}
