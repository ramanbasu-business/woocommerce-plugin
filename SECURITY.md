# Security notes

This repository contains a legacy WordPress plugin. It intentionally does not include real credentials, client names, store URLs, or live customer data.

## Important

- Real credentials must never be committed to Git.
- Any live store values must remain in the WordPress admin configuration or secure environment-specific settings.
- Requests that include credential material should be sent only over HTTPS.
- Debug output must not print decrypted tokens, secrets, or raw request values.

## Current project posture

This repo is a sanitised showcase version. Before deployment to a production site, validate the exact configuration against the target WordPress installation and confirm that any live credentials are managed securely.
