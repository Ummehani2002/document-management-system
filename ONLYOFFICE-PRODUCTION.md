# OnlyOffice for production (edit Excel/Word → Save to DMS)

Laravel Cloud runs the DMS app. **OnlyOffice Document Server must run on a separate machine** (VM or Docker host) with a public HTTPS URL. The browser loads the editor from that host; the host must also reach your DMS URL to download files and POST saves.

```
Browser  →  DMS (Laravel Cloud)     edit page
Browser  →  OnlyOffice server       spreadsheet UI
OnlyOffice → DMS                    fetch file + save callback
DMS → R2                            overwrite Excel in storage
```

---

## 1. Create an OnlyOffice server (recommended: small VM)

Use a VM with **at least 4 GB RAM** (8 GB better), Ubuntu 22.04+, Docker installed.

### 1.1 Copy compose file to the VM

On the VM:

```bash
mkdir -p ~/dms-onlyoffice && cd ~/dms-onlyoffice
```

Create `docker-compose.yml` (production):

```yaml
services:
  onlyoffice:
    image: onlyoffice/documentserver:latest
    container_name: dms-onlyoffice
    ports:
      - "80:80"
    environment:
      - JWT_ENABLED=true
      - JWT_SECRET=CHANGE_ME_TO_A_LONG_RANDOM_STRING
    restart: unless-stopped
```

Generate a secret (same value goes into Laravel Cloud):

```bash
openssl rand -hex 32
```

Put that value in `JWT_SECRET` above.

```bash
docker compose up -d
```

Wait 1–2 minutes, then:

```bash
curl http://127.0.0.1/healthcheck
# should print: true
```

### 1.2 Put HTTPS in front (required for production)

Point a DNS name, e.g. `office.yourdomain.com`, to the VM.

Example with **Caddy** (auto HTTPS):

```bash
# install caddy, then Caddyfile:
office.yourdomain.com {
  reverse_proxy 127.0.0.1:80
}
```

Or use nginx + Let’s Encrypt. Users and the DMS must use:

`https://office.yourdomain.com`

---

## 2. Laravel Cloud environment variables

In **Laravel Cloud → Environment → Environment variables**, set:

| Variable | Example | Notes |
|----------|---------|--------|
| `APP_URL` | `https://your-dms.laravel.cloud` | Your live DMS URL |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | `https://office.yourdomain.com` | Public OnlyOffice URL (no trailing slash) |
| `ONLYOFFICE_APP_URL` | `https://your-dms.laravel.cloud` | Usually same as `APP_URL` |
| `ONLYOFFICE_JWT_SECRET` | *(same as VM `JWT_SECRET`)* | Required if JWT is enabled on the Document Server |

Redeploy the environment after saving.

### Local Docker (dev only)

```env
ONLYOFFICE_DOCUMENT_SERVER_URL=http://localhost:8082
ONLYOFFICE_APP_URL=http://host.docker.internal:17500
# JWT_ENABLED=false in docker-compose.onlyoffice.yml → leave JWT secret empty
```

```bash
docker compose -f docker-compose.onlyoffice.yml up -d
```

---

## 3. Firewall / network checklist

- [ ] Browser can open `https://office.yourdomain.com/healthcheck` → `true`
- [ ] Browser can load `https://office.yourdomain.com/web-apps/apps/api/documents/api.js`
- [ ] OnlyOffice server can reach `https://your-dms.laravel.cloud/up` (outbound HTTPS)
- [ ] DMS can reach `https://office.yourdomain.com/healthcheck` (outbound from Laravel Cloud)
- [ ] DNS for `office.yourdomain.com` points to the VM
- [ ] JWT secret matches on Document Server and Laravel Cloud

---

## 4. Verify from Laravel Cloud

In Laravel Cloud → **Commands**:

```bash
php artisan onlyoffice:status
```

You want:

- Healthcheck: OK  
- Editor API script: OK  

If healthcheck fails, Laravel Cloud cannot talk to OnlyOffice (wrong URL, firewall, or Document Server down).

---

## 5. How users edit Excel so it saves in DMS

1. Search → **Edit in DMS** (not the Office Online “Edit a copy”)
2. Wait for the editor to load
3. Edit cells
4. Click **Save to DMS**
5. Wait for green “Saved to DMS”
6. Close and reopen from Search to confirm

If OnlyOffice is down, use **Download → edit on PC → Upload edited file → Replace in DMS**.

---

## 6. Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| Editor page says OnlyOffice not reachable | `ONLYOFFICE_DOCUMENT_SERVER_URL` wrong or VM down |
| Editor opens, Save never finishes | OnlyOffice cannot POST to `ONLYOFFICE_APP_URL` / callback |
| Save fails in logs: download failed | DMS cannot fetch OnlyOffice cache URL (URL rewrite / firewall) |
| JWT / callback error | `ONLYOFFICE_JWT_SECRET` mismatch |
| Still opening `view.officeapps.live.com` | User opened file via old Open link before deploy; use **Edit in DMS** |

App logs to check:

```text
OnlyOffice document overwritten in DMS
OnlyOffice callback save failed
```

---

## 7. After deploy smoke test

1. Upload a small `test.xlsx` to a project  
2. **Edit in DMS** → change a cell → **Save to DMS**  
3. Confirm green success  
4. Download the same file from Search — change should be there  

Done: browser edit + save writes into DMS (R2), not OneDrive.
