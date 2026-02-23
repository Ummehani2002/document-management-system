# Microsoft Azure AD (Entra ID) login setup

Your app uses **Sign in with Microsoft** so users authenticate with their company Microsoft account and are redirected to the dashboard after verification.

## 1. Register the app in Azure

1. Go to **[Azure Portal](https://portal.azure.com)** and sign in.
2. Open **Microsoft Entra ID** (or **Azure Active Directory**).
3. Click **App registrations** → **New registration**.
4. Fill in:
   - **Name**: e.g. `Document Management System` or your company app name.
   - **Supported account types**:  
     - **Single tenant** – only your organization.  
     - **Multitenant** – if you need other Azure AD tenants to sign in.
   - **Redirect URI**:  
     - Platform: **Web**  
     - URL: `https://yourdomain.com/login/microsoft/callback`  
       (for local: `http://document-management-system.test/login/microsoft/callback` or `http://localhost:8000/login/microsoft/callback`)
5. Click **Register**.

## 2. Get Client ID and Tenant ID

- On the app’s **Overview** page, copy:
  - **Application (client) ID** → `.env` as `AZURE_CLIENT_ID`
  - **Directory (tenant) ID** → `.env` as `AZURE_TENANT_ID`  
  - For **multitenant** use `AZURE_TENANT_ID=common`.

## 3. Create a client secret

1. In the app, go to **Certificates & secrets**.
2. **New client secret** → add description → choose expiry → **Add**.
3. Copy the **Value** (not the Secret ID) into `.env` as `AZURE_CLIENT_SECRET` (you can’t see it again later).

## 4. Configure API permissions

1. Go to **API permissions** → **Add a permission**.
2. **Microsoft Graph** → **Delegated**.
3. Add:
   - `openid`
   - `email`
   - `profile`
   - `User.Read`
4. Click **Grant admin consent** if you want to consent for the whole tenant.

## 5. Set redirect URI in .env

In `.env`:

```env
APP_NAME="Your Company Name"
APP_URL=https://yourdomain.com

AZURE_CLIENT_ID=your-application-client-id
AZURE_CLIENT_SECRET=your-client-secret-value
AZURE_REDIRECT_URI="${APP_URL}/login/microsoft/callback"
AZURE_TENANT_ID=your-tenant-id
```

For **single tenant** use your **Directory (tenant) ID**.  
For **multitenant** use `AZURE_TENANT_ID=common`.

## 6. Run migration

```bash
php artisan migrate
```

This adds the `azure_id` column to the `users` table.

## Flow

1. User opens `/login` and clicks **Sign in with Microsoft**.
2. They are sent to Microsoft’s sign-in page (company branding if configured in Entra ID).
3. After authentication and consent, Microsoft redirects to `/login/microsoft/callback`.
4. The app finds or creates a user by Azure ID or email, logs them in, and redirects to the **dashboard**.

## Troubleshooting

- **Redirect URI mismatch**: The URL in Azure (App registration → Authentication) must **exactly** match `AZURE_REDIRECT_URI` (including `http`/`https` and trailing slash or not).
- **Invalid client**: Double-check `AZURE_CLIENT_ID` and `AZURE_CLIENT_SECRET`; ensure the secret hasn’t expired.
- **Insufficient privileges**: Ensure the required Microsoft Graph delegated permissions are added and, if needed, admin consent is granted.
