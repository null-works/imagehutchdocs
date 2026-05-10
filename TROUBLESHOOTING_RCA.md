# Root Cause Analysis: 403 Forbidden Outage (May 10, 2026)

## Incident Description
On May 10, 2026, the `imagehut.ch` site became inaccessible to all users, returning a `403 Forbidden` error on the root and all virtual routes. Static assets (images) redirected to R2 remained accessible.

## Root Cause
The outage was caused by an overly restrictive security rule in the `.htaccess` file combined with a change in the server's configuration that enabled `.htaccess` processing.

### Technical Details
1. **Broken .htaccess Rule**: The `.htaccess` file contained a "Single PHP-entrypoint" rule designed to block direct access to PHP files. However, it failed to include `index.php` in its exception list:
   ```apache
   RewriteCond %{REQUEST_URI} !^/(import|refresh_counts|check_user|dump_session)\.php$
   RewriteRule \.php$ - [NC,L,F,R=404]
   ```
2. **Trigger**: The rule had been dormant because Apache was previously configured with `AllowOverride None`, causing it to ignore `.htaccess` files. A recent container recreation/update enabled `AllowOverride All` (via `/etc/apache2/conf-enabled/docker-php.conf`), activating the dormant block.
3. **Effect**: Since Chevereto rewrites all requests to `index.php`, and `index.php` was not in the allowed list, Apache blocked every request to the application.

## Resolution
The `.htaccess` file was patched to include `index` in the allowed entrypoints:
```apache
RewriteCond %{REQUEST_URI} ^/(index|import|refresh_counts|check_user|dump_session)\.php$
```

## Lessons Learned
- **Implicit Dependencies**: Hardening rules that block `.php` files must always account for the primary `index.php` front-controller.
- **Dormant Configuration**: Just because a site is working doesn't mean the `.htaccess` file is valid; it might simply be ignored. Always verify `AllowOverride` status during environment updates.
- **Container Uptime**: Correlating outages with container recreation events is a key diagnostic step.
- **Remote Automation**: Using `SSH_ASKPASS` for non-interactive SSH on Windows is a reliable way to automate remote troubleshooting when TTY-based prompts are problematic.

## Future Prevention
- Periodically verify that `.htaccess` files in the repository match the active production versions.
- Include `index.php` by default in any "allow-list" security rules.
- Test container recreations in a staging environment to catch "dormant" configuration issues.
