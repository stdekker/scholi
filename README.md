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
