# Scholi

Fun, educational games in Dutch for primary-school pupils, starting with **rekenen**.

Symfony 7.4 LTS · Symfony UX (AssetMapper, Stimulus, Turbo) · DDEV · MariaDB

## Getting started

Requires [DDEV](https://docs.ddev.com/).

```bash
ddev start
ddev composer install
ddev npm install
ddev console doctrine:migrations:migrate -n
```

Open https://scholi.ddev.site

## Quality checks

```bash
ddev qa
```

Runs PHP-CS-Fixer, PHPStan, PHPUnit, ESLint, Prettier and Vitest.

See [AGENTS.md](AGENTS.md) for conventions and project structure.

## Deploy

```bash
bin/deploy --init      # once: server folders + .env.local template (then fill in DATABASE_URL on the server)
bin/deploy --dry-run   # build and check, change nothing
bin/deploy             # deploy the committed HEAD to scholi.nl
```

See [AGENTS.md](AGENTS.md#deployment-production-scholinl) for details.

## License

Scholi is free software, licensed under the [GNU General Public License v3.0 or later](LICENSE).
