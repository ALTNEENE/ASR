# Vercel Deployment

This project is configured for Vercel using the community PHP runtime in `vercel.json`.

## Required Vercel environment variables

Set these in Vercel Project Settings -> Environment Variables.

- `DATABASE_URL` or all of `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `APP_URL`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`
- `GEMINI_API_KEY`
- `GROQ_API_KEY`
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USERNAME`, `SMTP_PASSWORD`
- `FROM_EMAIL`, `FROM_NAME`

Vercel cannot connect to your local XAMPP MySQL database. Use a hosted MySQL-compatible database and import the SQL files in `database_setup.sql` and `db/migrations/`.

## Local XAMPP environment

For local AI features, copy `.env.example` to `.env` and set your real keys:

```env
GROQ_API_KEY=gsk_your_real_key
GEMINI_API_KEY=your_real_key
```

The PHP config loads `.env` automatically on XAMPP. Do not commit `.env`.

## Notes

- Dependencies are installed from the root `composer.json`.
- `vendor/`, generated PDFs, local editor files, and prototype files are excluded from deployment.
- Runtime filesystems on serverless platforms are ephemeral. Reports should be streamed to the browser or emailed, not saved permanently inside the project.
