# Deploy DMS to Laravel Cloud — Step-by-step (GitHub to live)

Follow these steps in order, from your GitHub repo to a live deployment.

---

## Step 1: Put the project on GitHub

1. Create a new repository on GitHub (e.g. `document-management-system`).
2. In your project folder, run:

```bash
git init
git add .
git commit -m "Laravel Cloud ready"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/document-management-system.git
git push -u origin main
```

(If the repo already exists and is connected, just push: `git push origin main`.)

---

## Step 2: Sign up / log in to Laravel Cloud

1. Go to **[cloud.laravel.com](https://cloud.laravel.com)**.
2. Sign in or create an account.
3. Add a payment method if required by your plan.

---

## Step 3: Create a new application and connect GitHub

1. On the Laravel Cloud dashboard, click **+ New application**.
2. Click **Continue with GitHub**.
3. In the new tab, sign in to GitHub if needed, then:
   - Choose the **user or organization** that owns the repo.
   - Under **Repository access**, select **Only select repositories** and pick your `document-management-system` repo (or grant access to all).
   - Click **Install & Authorize** (or **Save**).
4. You’re back on Laravel Cloud. Select your **repository** from the list.
5. **Application name**: e.g. `document-management-system`.
6. **Region**: Choose one (e.g. US East, EU West).
7. Click **Create Application**.

You’ll land on your new app’s default environment (e.g. `main` or `production`).

---

## Step 4: Add a MySQL database

1. On the environment page, open the **Infrastructure** / **Infrastructure canvas** view.
2. Click **Add database**.
3. Choose **Create new cluster** (or use an existing one in the **same region**).
4. **Type**: Laravel MySQL.
5. **Cluster name**: e.g. `dms-db`.
6. **Instance size**: e.g. Flex 512 MB or 1 GB to start.
7. **Storage**: e.g. 5 GB.
8. **Region**: Must match the app (same as in Step 3).
9. **Database name**: e.g. `dms` (this is the schema name).
10. Confirm / **Create**. When asked to attach to this environment, attach it.
11. **Redeploy** the environment (Deploy button or Save & Deploy) so the app gets `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` automatically.

---

## Step 5: Add Object Storage (for PDF files)

1. Still in the **Infrastructure canvas**, click **Add bucket** (or **Add object storage**).
2. **Create new bucket**.
3. **Bucket type**: Laravel Object Storage.
4. **Bucket name**: e.g. `dms-documents`.
5. **Disk name**: e.g. `s3` (this is the Laravel disk name).  
   - Enable **Use as default disk** so the app uses this for uploads/downloads.
6. **File visibility**: **Private** (documents are served via the app’s download route).
7. Attach the bucket to this environment and confirm.
8. **Redeploy** again so the app gets `FILESYSTEM_DISK`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`, etc.

---

## Step 6: Add a queue worker (for OCR)

1. In the **Infrastructure canvas**, click your **App** compute cluster (the box that runs the web app).
2. Find **Background processes** and click **New background process**.
3. Select **Queue worker**.
4. **Command**: `php artisan queue:work`
5. **Processes**: e.g. `1` (increase later if needed).
6. Save.
7. **Redeploy** so the worker starts.

---

## Step 7: Set environment variables

1. Open the environment **Settings** (or **Environment variables**).
2. Add or confirm:

| Name       | Value        |
|-----------|--------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false`    |
| `APP_URL` | Your app URL (see Step 9; you can set this after first deploy) |

Do **not** set `DB_*` or `AWS_*` / `FILESYSTEM_DISK` yourself if you added the database and bucket in the dashboard — Laravel Cloud injects those. You can set `APP_URL` after the first deploy when you know the exact URL.

---

## Step 8: Set build and deploy commands

1. In the environment, go to **Settings** → **Deployments** (or **Build & deploy**).
2. **Build commands** — use something like:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize
```

3. **Deploy commands** — use:

```bash
php artisan migrate --force
```

Do **not** add `php artisan queue:restart` or `php artisan storage:link`.

---

## Step 9: Deploy

1. Click **Deploy** on the environment.
2. Wait for the build and deploy to finish (watch the deployment log).
3. After a successful deploy, note your app URL (e.g. `https://document-management-system-main-xxxx.laravel.cloud`). Set **Settings** → **Environment variables** → `APP_URL` to this URL if you didn’t set it before, then redeploy once.
4. (Optional) Run seed once: open the **Commands** tab, run:

```bash
php artisan db:seed --force
```

---

## Step 10: Verify

1. Open the app URL in the browser.
2. Log in (or register) and try:
   - Creating an Entity and Project (if empty).
   - Uploading a PDF (it goes to Object Storage).
   - Searching (MySQL full-text).
   - Downloading / opening a PDF (served from the app).
3. OCR runs in the background; after a short delay, search by text from the first page should work.

---

## Checklist (GitHub → Deployed)

- [ ] **Step 1** — Code pushed to GitHub.
- [ ] **Step 2** — Laravel Cloud account ready.
- [ ] **Step 3** — App created and GitHub repo connected; branch selected.
- [ ] **Step 4** — MySQL database added and attached; environment redeployed.
- [ ] **Step 5** — Object Storage bucket added (default disk); environment redeployed.
- [ ] **Step 6** — Queue worker added; environment redeployed.
- [ ] **Step 7** — `APP_ENV`, `APP_DEBUG`, `APP_URL` set.
- [ ] **Step 8** — Build and deploy commands set.
- [ ] **Step 9** — First deploy successful; optional seed run.
- [ ] **Step 10** — Login, upload, search, and download work.

---

## Troubleshooting

- **Deploy fails (e.g. framework version)**  
  Run locally: `composer update laravel/framework` then push and redeploy.

- **502 or blank page**  
  Check **Logs** for the environment; fix any migration or env errors and redeploy.

- **“File not found” on download**  
  Ensure the Object Storage bucket is attached and set as default, then redeploy.

- **OCR never runs**  
  Ensure the queue worker is added and the environment was redeployed after adding it.

- **Database errors**  
  Ensure the MySQL database is in the **same region** as the app and attached to the environment.

More: [Laravel Cloud docs](https://cloud.laravel.com/docs).
