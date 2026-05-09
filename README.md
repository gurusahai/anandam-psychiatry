# Anandam Psychiatry Centre Website

Static website for Anandam Psychiatry Centre in East Patel Nagar, New Delhi.

## Current Setup

- Static HTML/CSS/JavaScript site.
- Hosted on Vercel.
- Appointment booking handled through Calendly Free inline embed.
- No custom backend or PHP form handler is currently used.

## Main Files

```text
index.html              Main website page
style.css               Site styling
script.js               Navigation, scroll, FAQ, and UI behavior
vercel.json             Vercel static-site configuration
.vercelignore           Files excluded from Vercel uploads
assets/images/          Site images and logo
```

## Booking Flow

The contact section embeds Calendly using:

```text
https://calendly.com/kumar-dump2/30min
```

The visible booking area includes:

- Direct "Open Calendly" fallback button.
- Inline Calendly scheduler.
- Phone and WhatsApp contact cards as backup paths.

## Deployment

The production deployment is expected to run from the `main` branch on GitHub:

```text
https://github.com/gurusahai/anandam-psychiatry
```

When changes are pushed to `main`, Vercel should trigger a new deployment.

## Notes

- This project does not currently need PHP, Node, WordPress, or a database.
- Do not add patient-sensitive custom form fields unless there is a clear privacy and data-handling plan.
- If deploying to GoDaddy cPanel later, upload only the static site files: `index.html`, `style.css`, `script.js`, and `assets/`.
- `Anandam.md` is a local audit note and is intentionally ignored by Git/Vercel.
