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

### 3.1 Start creating an application

1. Open **[cloud.laravel.com](https://cloud.laravel.com)** and make sure you’re logged in.
2. On the main dashboard (or your organization’s page), find and click the **+ New application** button (often top-right or in the main content area).
3. You’ll see options to connect a Git provider: **GitHub**, **GitLab**, or **Bitbucket**. Click **Continue with GitHub**.

### 3.2 Authorize Laravel Cloud in GitHub

1. A **new browser tab or window** opens and goes to **GitHub** (github.com).
2. If you’re not signed in to GitHub, sign in with the account that owns (or can access) your `document-management-system` repository.
3. GitHub will show an **“Install Laravel Cloud”** or **“Authorize Laravel”** screen. On it you’ll see:
   - **Laravel Cloud** requesting access to your GitHub account.
   - **Repository access**:
     - **All repositories** – Laravel Cloud can see and use every repo in this account/org.
     - **Only select repositories** – choose which repos Laravel Cloud can use (recommended).
4. If you chose **Only select repositories**:
   - Click the **Select repositories** dropdown.
   - Find and select **document-management-system** (or the exact name of your repo).
   - You can select more than one if you plan to deploy multiple apps.
5. Scroll down and click **Install & Authorize** (or **Install** / **Authorize**, depending on the button text). This grants Laravel Cloud permission to read your repo and trigger deployments.
6. After you authorize, the tab may close automatically or show a success message. Return to the **Laravel Cloud** tab.

### 3.3 Select the repository and create the app

1. Back on **Laravel Cloud**, you should see a list of **repositories** GitHub has allowed access to (or a “Select a repository” step).
2. Find **document-management-system** in the list and **click it** to select it. If you don’t see it, go back to GitHub and confirm you selected that repo in “Only select repositories”, then refresh Laravel Cloud.
3. Fill in the application details:
   - **Application name**  
     Enter a short name, e.g. `document-management-system` or `dms`. This is used in the Laravel Cloud dashboard and in the default URL (e.g. `document-management-system-main-xxxx.laravel.cloud`). Use lowercase, numbers, and hyphens only to avoid issues.
   - **Region**  
     Choose the region where the app and database will run (e.g. **US East**, **EU West**, **Asia Pacific**). Pick one close to your users. You can’t change it later without creating a new environment; the database must be in the **same region** as the app.
4. Double-check:
   - The correct **repository** is selected.
   - **Application name** and **Region** are what you want.
5. Click **Create Application** (or **Create**).

### 3.4 After creation

1. Laravel Cloud will create the application and a **default environment** (often named after the branch, e.g. `main` or `production`).
2. You’ll be taken to that environment’s **overview** or **Environment** page. From here you can:
   - Add a **MySQL database** (Step 4).
   - Add **Object Storage** (Step 5).
   - Add a **queue worker** (Step 6).
   - Set **environment variables** and **build/deploy commands** (Steps 7–8).
   - Run the first **Deploy** (Step 9).

If you don’t see your repo in the list after authorizing, re-run the GitHub app installation and ensure **document-management-system** is in “Only select repositories”, then try Step 3 again.

---

**Short version (reference):**

1. On the Laravel Cloud dashboard, click **+ New application**.
2. Click **Continue with GitHub**.
3. In the new tab, sign in to GitHub if needed, then:
   - Choose the **user or organization** that owns the repo.
   - Under **Repository access**, select **Only select repositories** and pick your `document-management-system` repo (or grant access to all).
   - Click **Install & Authorize** (or **Save**).
4. You're back on Laravel Cloud. Select your **repository** from the list.
5. **Application name**: e.g. `document-management-system`.
6. **Region**: Choose one (e.g. US East, EU West).
7. Click **Create Application**.

You'll land on your new app's default environment (e.g. `main` or `production`).

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
6. **File visibility**: **Private** (documents are served via the app's download route).
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

Do **not** set `COMPOSER_DEV_MODE` in Environment variables (it can cause "command not found" when the platform sources `.env`). Use `COMPOSER_DEV_MODE=0 composer install` in the build commands instead (see Step 8).  
Do **not** set `DB_*` or `AWS_*` / `FILESYSTEM_DISK` yourself if you added the database and bucket in the dashboard — Laravel Cloud injects those. You can set `APP_URL` after the first deploy when you know the exact URL.

---

## Step 8: Set build and deploy commands

1. In the environment, go to **Settings** → **Deployments** (or **Build & deploy**).
2. **Build commands** — use exactly these lines (one command per line; do **not** add `COMPOSER_DEV_MODE` to the Environment variables — it can break the build if the platform sources `.env`):

```bash
COMPOSER_DEV_MODE=0 composer install
npm ci
npm run build
php artisan optimize
```

Use **plain `composer install`** only if the line above fails. Do **not** put `COMPOSER_DEV_MODE` in the **Environment variables** panel if you see errors like `.env: line X: COMPOSER_DEV_MODE: command not found`; set it inline in the build command instead.

4. **Deploy commands** — use:

```bash
php artisan migrate --force
```

Do **not** add `php artisan queue:restart` or `php artisan storage:link`.

---

## Step 9: Deploy

1. Click **Deploy** on the environment.
2. Wait for the build and deploy to finish (watch the deployment log).
3. After a successful deploy, note your app URL (e.g. `https://document-management-system-main-xxxx.laravel.cloud`). Set **Settings** → **Environment variables** → `APP_URL` to this URL if you didn't set it before, then redeploy once.
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
- [ ] **Step 8** — Build and deploy commands set (**without** `--optimize-autoloader`).
- [ ] **Step 9** — First deploy successful; optional seed run.
- [ ] **Step 10** — Login, upload, search, and download work.

---

## Troubleshooting

### "The `--optimize-autoloader` option does not exist" or "The `--no-dev` option does not exist"

Laravel Cloud’s Composer (2.7+) no longer supports these flags. In **Build commands** use:
```bash
COMPOSER_DEV_MODE=0 composer install
```
(no `--no-dev`, no `--optimize-autoloader`). Do **not** add `COMPOSER_DEV_MODE` in the Environment variables panel (see below). Then **Redeploy**.

### ".env: line X: COMPOSER_DEV_MODE: command not found" or "Command install is not defined"

1. **Remove `COMPOSER_DEV_MODE` from Environment variables** in Laravel Cloud (Settings → Environment variables). Having it there can make the platform write it into `.env` in a way that gets sourced as a script and breaks the build.
2. **Build commands** must be exactly (each line is one command; first line must be `COMPOSER_DEV_MODE=0 composer install`, not just `install`):
   ```bash
   COMPOSER_DEV_MODE=0 composer install
   npm ci
   npm run build
   php artisan optimize
   ```
3. If you still see "install is not defined", the first line may have been pasted wrong — type `COMPOSER_DEV_MODE=0 composer install` with no extra spaces or line breaks in the middle. Then **Redeploy**.

### "Failed deploy from main" — find the real error

1. On the **Deployments** tab, **click one of the failed deployments**.
2. Open the **build log** and **deploy log** and scroll to the bottom for the error.
3. Use the fixes below based on what you see.

### Common deploy failures and fixes

| If the log says… | Do this |
|------------------|--------|
| **Laravel framework version not supported** | Run locally: `composer update laravel/framework` then commit, push, and redeploy. |
| **npm ERR!** or **npm ci** fails | In build commands, try `npm install` instead of `npm ci`, or ensure `package-lock.json` is committed. |
| **SQLSTATE** / database / **database.sqlite** | Add and attach a **MySQL** database (Step 4). Then redeploy. |
| **npm run build** fails (Vite / Node) | In **Settings** → **General**, set Node to 20 or 22. Redeploy. |
| **Deploy command failed** (migrate) | Ensure the database is attached. Or run `php artisan migrate --force` from **Commands** after build. |
| **Table 'documents' already exists** (or other table) | The DB was already migrated (e.g. previous deploy). The app migrations are now idempotent (they skip if the table exists). Commit the change, push, and **Redeploy**. Or run only new migrations from **Commands**: `php artisan migrate --force` (safe to re-run). |

### Other issues

- **502 or blank page** — Check **Logs**; fix migrations or env and redeploy.
- **"File not found" on download** — Ensure Object Storage bucket is attached and default; redeploy.
- **OCR never runs** — Ensure queue worker is added and environment redeployed.
- **Database errors** — MySQL must be in the **same region** as the app and attached.

More: [Laravel Cloud docs](https://cloud.laravel.com/docs).
