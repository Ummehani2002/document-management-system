



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


## 6. Run migration

```bash
php artisan migrate
```

This adds the `azure_id` column to the `users` table.

## Flow

1. User opens `/login` and clicks **Sign in with Microsoft**.
2. They enter their work email and password on Microsoft’s page (MFA/code if your tenant requires it).
3. After authentication and consent, Microsoft redirects to `/login/microsoft/callback`.
4. The app finds or creates a user by Azure ID or email, stores Microsoft mail permissions, logs them in, and redirects to the **dashboard**.

## Share documents by email

1. User must sign in with **Microsoft** (not only the local username/password form).
2. In Search, open the **⋮** menu on a file → **Share**.
3. Start typing a colleague’s name or email — the app suggests company contacts from Microsoft 365 and DMS users.
4. Pick a suggestion (or type the full address) and click **Send**.
5. The file is sent **from the logged-in user’s Microsoft email address** via Microsoft Graph.

### Azure permissions for Share

In **App registration → API permissions**, add these **Microsoft Graph delegated** permissions:

- `User.Read`
- `Mail.Send`
- `People.Read` (frequent contacts while typing)
- `User.ReadBasic.All` (search everyone in your company directory — **admin consent** usually required)

Grant **admin consent** if your tenant requires it. After adding permissions, users who already granted mail access should open **Share** once more and approve the updated permissions when prompted.

If Share says to sign in with Microsoft, log out and use the **Sign in with Microsoft** button on the login page again.

## Troubleshooting

- **AADSTS50011 (redirect URI mismatch)**: The URL in Azure (App registration → Authentication) must **exactly** match `AZURE_REDIRECT_URI` (including `http`/`https`, port, and no trailing slash).
- **AADSTS900561 (endpoint only accepts POST)**: Usually happens on the Microsoft **consent** page when `Mail.Send` is requested during login. Sign in with basic scopes first, then grant mail permission when sharing. Also ensure the redirect URI is only under **Web** (not SPA) and implicit grant is disabled.
- **Invalid client**: Double-check `AZURE_CLIENT_ID` and `AZURE_CLIENT_SECRET`; ensure the secret hasn’t expired.
- **Insufficient privileges**: Ensure the required Microsoft Graph delegated permissions are added and, if needed, admin consent is granted.
