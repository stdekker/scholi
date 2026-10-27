# AGENTS.md

Instructions for AI coding agents (and humans) working on **Scholi**.

## Purpose

Scholi is a collection of fun, educational browser games in **Dutch** for **primary-school pupils** (basisonderwijs, groep 3–8, roughly ages 6–12). The first game is **rekenen** (arithmetic). More subjects may follow.

Game specifications and mechanics are **not defined yet**. Don't invent game rules, scoring or levels unless a task asks for them. The current code is groundwork only.

## Stack

| Layer | Choice |
|---|---|
| Dev environment | DDEV (nginx-fpm, PHP 8.4, MariaDB 11.8, Node 24) |
| Backend | Symfony **7.4 LTS** (webapp pack: Twig, Doctrine ORM + Migrations, Security, Forms, Mailer, Messenger) |
| Frontend | Symfony UX: **AssetMapper** + **Stimulus** + **Turbo** (native ES modules, no bundler/build step) |
| Styling | Plain semantic CSS in `assets/styles/` (no Tailwind or utility frameworks) |
| PHP quality | PHPUnit 13, PHPStan (level max + Symfony extension), PHP-CS-Fixer (`@Symfony`) |
| JS quality | ESLint (flat config), Prettier, Vitest |

Node/npm is used **only for dev tooling** (lint, format, tests). Runtime JS dependencies are added with `importmap:require`, never with npm.

Stay on Symfony 7.4 LTS (`extra.symfony.require: 7.4.*`). Don't upgrade to 8.x without being asked.

## Run everything through DDEV

PHP, Composer, Node and the database run in containers. **Never** run `php`, `composer`, `npm` or `bin/console` directly on the host.

| Task | Command |
|---|---|
| Start / stop | `ddev start` / `ddev stop` |
| Install dependencies | `ddev composer install && ddev npm install` |
| Symfony console | `ddev console <command>` |
| Composer | `ddev composer <args>` |
| Add a frontend package | `ddev console importmap:require <package>` |
| npm (dev tools only) | `ddev npm <args>` |
| **All quality checks** | `ddev qa` |
| PHP only | `ddev composer qa` (or `cs`, `cs:fix`, `phpstan`, `test`) |
| JS only | `ddev npm run lint`, `ddev npm run format`, `ddev npm test` |
| Database query | `ddev mysql -e 'SELECT 1'` |
| Migrations | `ddev console make:migration`, `ddev console doctrine:migrations:migrate` |
| Site URL | `ddev launch --print-url` → https://scholi.ddev.site |
| Mail (Mailpit) | `ddev launch -m --print-url` |

DDEV generates `.env.local` itself (database and mailer settings). Don't commit it. The test env doesn't read `.env.local`, so the committed defaults in `.env` also point at the DDEV database.

## Project layout

```
src/Controller/HomeController.php           # / – subject overview
src/Controller/Game/<Subject>Controller.php  # one controller per subject, e.g. RekenenController (/rekenen/…)
templates/base.html.twig                     # layout (lang="nl"; blocks: meta, title, body)
templates/home/                              # homepage
templates/game/<subject>/                    # subject + game pages, e.g. game/rekenen/tafel.html.twig
assets/app.js                                # AssetMapper entrypoint
assets/controllers/<game>_controller.js      # Stimulus controllers: DOM wiring only, e.g. tafels_controller.js
assets/games/<subject>/<game>.js             # pure game logic (ES modules), e.g. rekenen/tafels.js
assets/games/shared/                         # pure helpers shared by games (random.js: randomInt, shuffle)
assets/styles/app.css                        # global styles + design tokens (CSS custom properties)
translations/messages.nl.yaml                # all user-facing text
tests/Controller/                            # PHPUnit functional tests
tests/js/games/                              # Vitest unit tests, mirroring assets/games/
.ddev/commands/web/qa                        # `ddev qa`
```

A **subject** (*vak*, e.g. rekenen) groups one or more **games** (e.g. tafels).

## Games

### Rekenen › Tafels (bubbels)

- `/rekenen` lists the tables 2–10. `/rekenen/tafel/{2..10}` is the game; other values give a 404.
- A round has ten bubbles, "multiplier × table" for multipliers 1–10 (Dutch notation: `2 × 6` belongs to *de tafel van 6*), in random order. The bubbles bob gently, and the bobbing is off under `prefers-reduced-motion`.
- Clicking a bubble enlarges it and shows an answer field plus an on-screen keypad (0–9, ⌫, OK). The physical keyboard also works: digits, Backspace, Enter to check, Escape to close.
- A correct answer pops the bubble and removes it. A wrong answer gives a friendly "try again" with a wiggle, and the pupil can retry without limit. There is no score, timer or mistake counter.
- When all ten bubbles are popped, a completion panel offers "Nog een keer" (new round) and "Andere tafel".
- Logic: `assets/games/rekenen/tafels.js`. Wiring: `assets/controllers/tafels_controller.js`. Translated strings with `%placeholders%` are passed as Stimulus values and filled in with `format()`.
- The game page sets `turbo-cache-control: no-cache`, and the controller's `connect()` rebuilds the board from scratch.

### Frontend architecture rule

- **Game logic** lives in `assets/games/<game>/*.js`. These are pure ES modules: no DOM access, no bare imports (no `@hotwired/...` or other importmap packages), and only relative imports. Inject randomness and time as parameters so the code is deterministic in tests.
- **Stimulus controllers** in `assets/controllers/` import that logic with a relative path (e.g. `../games/rekenen/tafels.js`). They only connect the DOM to it: targets, actions, rendering.
- With this split, all game logic can be tested with Vitest in Node without a browser or importmap.
- JS tests live in `tests/js/`, never in `assets/`, because AssetMapper serves everything under `assets/` publicly.

## Language conventions

- **Code, identifiers, comments, commit messages and docs:** English.
- **Everything a pupil, teacher or parent sees:** Dutch (`nl`, the default and only locale). Put the text in `translations/messages.nl.yaml` and use `{{ 'key'|trans }}` / `$translator->trans()`. Don't hard-code Dutch strings in templates or JS. For text that JS renders, pass it in through Stimulus values or data attributes from Twig.
- School and domain terms may stay Dutch in names where that's clearer: routes and URLs (`/rekenen`), game names (`RekenenController`, `rekenen_controller.js`).
- Use the Dutch terms educators use: *plussommen, minsommen, tafels, groep 3*, and so on.

## Audience: child-focused UX & accessibility

- Large text (base 1.25rem) and large touch targets (≥ 48px). Games must work by touch (tablets/Chromebooks), mouse **and** keyboard.
- Use short, simple Dutch sentences and plenty of positive, encouraging feedback. Never shame mistakes.
- Don't put the child under time pressure by default. If a timer is added, it must be optional.
- Meet WCAG 2.2 AA: contrast, visible focus (`:focus-visible`), semantic HTML, `aria-live` for feedback, and support for `prefers-reduced-motion`.
- Never rely on colour or sound alone to carry meaning.

## Privacy (AVG/GDPR, minors)

- Load nothing from third parties: no CDNs, Google Fonts, analytics, trackers, ads or embeds. AssetMapper vendors all JS locally (`assets/vendor/`, installed with `importmap:install`).
- Collect as little personal data as possible. Ask before adding anything that stores data about pupils.

## Code style

- PHP: `declare(strict_types=1);` in new files, `final` classes by default, attribute routing, constructor injection. Follow `@Symfony` CS rules. PHPStan must pass at level max, and you may not lower the level or add baseline entries to get it passing.
- CSS: semantic class names in BEM style (`.game-card`, `.game-card__title`). Use the design tokens in `:root`. No utility-class piles.
- JS: ES modules, Prettier formatting (single quotes, 4 spaces, width 100), JSDoc on exported functions.

## Definition of done

1. `ddev qa` passes (cs, phpstan, phpunit, eslint, prettier, vitest).
2. New game logic has Vitest tests. New pages have a functional test (add them to `tests/Controller/SmokeTest.php` or a dedicated test).
3. All user-facing text is in `translations/messages.nl.yaml`.
4. The page works in the browser without console errors at phone and tablet widths.

## Adding a new game (checklist)

1. A Dutch route in the subject's controller (`src/Controller/Game/<Subject>Controller.php`), or a new subject controller plus a card on the homepage (`templates/home/index.html.twig`).
2. `templates/game/<subject>/<game>.html.twig` that mounts `data-controller="<game>"`.
3. `assets/controllers/<game>_controller.js` (thin) + `assets/games/<subject>/<game>.js` (logic).
4. `tests/js/games/<subject>/<game>.test.js` + functional tests for the new URLs.
5. Translation keys under `game.<subject>.<game>.*`.
6. Describe the mechanics under **Games** in this file.
7. Run `ddev qa`.
