# Deployment (production: scholi.nl)

`bin/deploy` (run on the host, not in DDEV; it uses your `vimexx` SSH alias) deploys the **committed HEAD** to the Vimexx/DirectAdmin hosting at `/home/u150449p142020/domains/scholi.nl`.

- The server's Composer and git are too old, so the release is **built locally in DDEV**: `git archive`, `composer install --no-dev`, `importmap:install` and `asset-map:compile`. It is then rsynced to `app/releases/<timestamp>-<sha>`, with unchanged files hard-linked.
- On the server it links `app/shared/.env.local` and `app/shared/var/log`, then runs `cache:clear` and `doctrine:migrations:migrate` with `/opt/alt/php84/usr/bin/php`. After that it switches `app/current` atomically and keeps 5 releases. `public_html` is a symlink to `app/current/public`.
- One-time setup: `bin/deploy --init` creates the folders and an `app/shared/.env.local` template with `APP_ENV=prod` and a random `APP_SECRET`. Fill in `DATABASE_URL` for the database created in DirectAdmin by hand on the server. Database passwords never go into git or the script.
- `bin/deploy --dry-run` builds and checks everything without changing the server.
- The dictee recordings (`assets/audio/`) are git-ignored, so they are not in `git archive`: the script copies them from the working tree before `asset-map:compile` and fails when they are missing.
- The website's PHP version (DirectAdmin / CloudLinux selector) must be **8.4**. Production uses Apache, so `public/.htaccess` is the front controller; DDEV uses nginx.
- Overridable via environment variables: `DEPLOY_HOST`, `DEPLOY_PATH`, `DEPLOY_PHP`, `KEEP_RELEASES`.
