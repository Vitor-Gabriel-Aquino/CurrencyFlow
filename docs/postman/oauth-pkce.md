# Testing OAuth PKCE With Postman

CurrencyFlow uses Laravel Passport with OAuth 2.0 Authorization Code Grant and PKCE.

The Postman collection is available at:

```text
docs/postman/CurrencyFlow.postman_collection.json
```

Postman supports OAuth 2.0 `Authorization Code (With PKCE)`. For the seeded local CurrencyFlow client, the registered redirect URI is:

```text
http://localhost:3000/auth/callback
```

Because the frontend is not running yet, the most reliable local Postman flow is to generate the PKCE values manually, authorize in the browser, copy the returned authorization code from the failed frontend callback URL, and exchange it with the collection request.

## Prerequisites

Start the application and seed the database:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate:fresh --seed
```

Import the Postman collection:

```text
docs/postman/CurrencyFlow.postman_collection.json
```

Confirm these collection variables:

```text
base_url = http://localhost:8000
client_id = 019ec29e-86dc-70bd-9de9-157bc6e2f735
frontend_redirect_uri = http://localhost:3000/auth/callback
```

Seeded users all use password:

```text
password
```

Use this finance user when testing approval and rejection:

```text
marta.kowalska@example.com
```

## Generate PKCE Values

Generate a verifier and challenge:

```bash
docker compose exec app php -r '$verifier = rtrim(strtr(base64_encode(random_bytes(64)), "+/", "-_"), "="); $challenge = rtrim(strtr(base64_encode(hash("sha256", $verifier, true)), "+/", "-_"), "="); echo "CODE_VERIFIER=$verifier\nCODE_CHALLENGE=$challenge\n";'
```

In Postman, set the collection variables:

```text
code_verifier = value from CODE_VERIFIER
code_challenge = value from CODE_CHALLENGE
```

## Authorize The Client

Open the request:

```text
OAuth PKCE / Open Authorization URL
```

Do not complete the login inside Postman's response Preview tab. The Laravel login and OAuth consent flow depend on browser session cookies and CSRF handling, so use a real browser for this step.

In Postman, copy the generated request URL and paste it into your browser.

You can copy the URL from the request bar after the collection variables are filled. It should look like this:

```text
http://localhost:8000/oauth/authorize?client_id=...&redirect_uri=http%3A%2F%2Flocalhost%3A3000%2Fauth%2Fcallback&response_type=code&scope=payments%3Aread%20payments%3Acreate%20payments%3Aapprove&code_challenge=...&code_challenge_method=S256&state=postman-test
```

Log in with a seeded user, for example:

```text
email: marta.kowalska@example.com
password: password
```

Approve the requested scopes.

The browser will redirect to:

```text
http://localhost:3000/auth/callback?code=...&state=postman-test
```

The frontend does not exist yet, so the page may fail to load. That is expected. Copy only the `code` query parameter value.

In Postman, set:

```text
authorization_code = copied code value
```

If you are already logged in from a previous browser session, Laravel may skip the login form and show the OAuth authorization page directly.

## Exchange The Code For Tokens

Open the request:

```text
OAuth PKCE / Exchange Authorization Code
```

Send the request.

The collection test script stores these variables automatically when the response is successful:

```text
access_token
refresh_token
```

Protected requests in the collection use:

```text
Authorization: Bearer {{access_token}}
```

## Test Protected API Requests

After `access_token` is set, use:

```text
Authentication / Get Current User
Payment Requests / Create Payment Request
Payment Requests / List Payment Requests
Payment Requests / Get Payment Request
Payment Requests / Approve Payment Request
Payment Requests / Reject Payment Request
```

The `Create Payment Request` request stores the created payment request UUID into:

```text
payment_request_id
```

The detail, approval, and rejection requests use that variable.

## Using Postman's Native OAuth 2.0 Helper

Postman's native OAuth helper can also be used with:

```text
Auth Type: OAuth 2.0
Grant Type: Authorization Code (With PKCE)
Auth URL: http://localhost:8000/oauth/authorize
Access Token URL: http://localhost:8000/oauth/token
Client ID: 019ec29e-86dc-70bd-9de9-157bc6e2f735
Scope: payments:read payments:create payments:approve
Code Challenge Method: SHA-256
Client Authentication: Send client credentials in body
```

However, Postman's browser callback must match a redirect URI registered for the OAuth client. The current seeded client is registered for the future frontend callback:

```text
http://localhost:3000/auth/callback
```

For that reason, the manual collection flow above is the recommended local workflow until a frontend callback exists or a dedicated Postman OAuth client is added.
