# First-page PDF text and OCR setup

Search uses text from the **first page** of each PDF. Text is taken in two steps:

1. **pdftotext** (already required) – extracts text from PDFs that have a text layer.
2. **Tesseract OCR** (optional) – if the first page is image-only (e.g. scanned), the app renders the first page to an image and runs Tesseract to get searchable text.

## Required (you already have this)

- **pdftotext** – Used by Spatie PdfToText. On Windows this is often from [xpdf tools](https://www.xpdfreader.com/download.html) or Poppler. Ensure it’s on your PATH so `pdftotext` works in a terminal.

## For image-only / scanned PDFs (optional)

To search inside **scanned** or **image-only** first pages, you need:

### 1. Tesseract OCR

- **Windows:** Install from [UB-Mannheim/tesseract](https://github.com/UB-Mannheim/tesseract/wiki) and add the `tesseract.exe` folder to your system PATH.
- **macOS:** `brew install tesseract`
- **Linux:** `sudo apt install tesseract-ocr` (or equivalent)

Check: `tesseract --version`

### 2. PDF → image (one of these)

**Option A – Poppler (pdftoppm)**  
Often installed with `pdftotext`. If not:

- **Windows:** [Poppler for Windows](https://github.com/oschwartz10612/poppler-windows/releases) – add the `bin` folder to PATH.
- **macOS:** `brew install poppler`
- **Linux:** `sudo apt install poppler-utils`

Check: `pdftoppm -h`

**Option B – ImageMagick**  
Converts the first PDF page to an image.

- **Windows:** [ImageMagick](https://imagemagick.org/script/download.php#windows) – use the installer and ensure “Add to PATH” is checked.
- **macOS:** `brew install imagemagick`
- **Linux:** `sudo apt install imagemagick`

Check: `magick --version` or `convert -version`

## After installing

1. Re-run indexing for documents that had no text:
   ```bash
   php artisan documents:index-ocr --sync
   ```
2. Search again; first-page content from scanned PDFs should now be searchable.

If you see “no text extracted” for a document, check `storage/logs/laravel.log` for messages like `PdfFirstPageOcr: pdftoppm failed` or `tesseract failed` to see which tool is missing or failing.
