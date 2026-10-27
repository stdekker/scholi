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
| Font | **Atkinson Hyperlegible** 400/700 (`@fontsource/atkinson-hyperlegible` via importmap), self-hosted |
| Accounts | Link-based players (no passwords, no e-mail); stateless firewall + cookie, see **Players** |
| PHP quality | PHPUnit 13 (+ DAMA doctrine-test-bundle), PHPStan (level max + Symfony + Doctrine extensions), PHP-CS-Fixer (`@Symfony`) |
| JS quality | ESLint (flat config), Prettier, Vitest |

Node/npm is used **only for dev tooling** (lint, format, tests). Runtime JS dependencies are added with `importmap:require`, never with npm.

Stay on Symfony 7.4 LTS (`extra.symfony.require: 7.4.*`). Don't upgrade to 8.x without being asked.

## Run everything through DDEV

PHP, Composer, Node and the database run in containers. **Never** run `php`, `composer`, `npm` or `bin/console` directly on the host.

| Task | Command |
|---|---|
| Start / stop | `ddev start` / `ddev stop` |
| Install dependencies | `ddev composer install && ddev npm install && ddev console doctrine:migrations:migrate -n` |
| Symfony console | `ddev console <command>` |
| Composer | `ddev composer <args>` |
| Add a frontend package | `ddev console importmap:require <package>` |
| npm (dev tools only) | `ddev npm <args>` |
| **All quality checks** | `ddev qa` |
| PHP only | `ddev composer qa` (or `cs`, `cs:fix`, `phpstan`, `test`; `test` first migrates and validates the test DB) |
| JS only | `ddev npm run lint`, `ddev npm run format`, `ddev npm test` |
| Database query | `ddev mysql -e 'SELECT 1'` |
| Migrations | `ddev console make:migration`, `ddev console doctrine:migrations:migrate` (files in `src/Migrations/`) |
| Site URL | `ddev launch --print-url` → https://scholi.ddev.site |
| Mail (Mailpit) | `ddev launch -m --print-url` |

DDEV generates `.env.local` itself (database and mailer settings). Don't commit it. The test env doesn't read `.env.local`, so the committed defaults in `.env` also point at the DDEV database.

Tests use the `db_test` database, which a `post-start` hook in `.ddev/config.yaml` creates (the `db` user can't create databases). DAMA wraps every test in a transaction that is rolled back.

## Project layout

```
src/Controller/HomeController.php           # / – subject overview
src/Controller/Game/<Subject>Controller.php  # one controller per subject, e.g. RekenenController (/rekenen/…)
src/Controller/PlayerController.php          # /speler, /speler/nieuw, /speler/{token}
src/Entity/, src/Repository/                 # Doctrine: Player, TafelsResult
src/Game/                                    # server-side game rules (Tafels: tables, TIME_LIMIT_MS), progress, input DTOs
src/Security/                                # player link token, cookie, authenticator
src/Player/                                  # FunNameGenerator
src/Migrations/                              # Doctrine migrations
templates/base.html.twig                     # layout (lang="nl"; blocks: meta, title, body), header, footer with the "Meer over Scholi" popover
templates/home/                              # homepage
templates/game/<subject>/                    # subject + game pages, e.g. game/rekenen/tafel.html.twig
templates/player/                            # registration, player page, progress badge
assets/app.js                                # AssetMapper entrypoint
assets/controllers/<game>_controller.js      # Stimulus controllers: DOM wiring only, e.g. tafels_controller.js
assets/games/<subject>/<game>.js             # pure game logic (ES modules), e.g. rekenen/tafels.js
assets/games/shared/                         # pure helpers shared by games (random.js: randomInt, shuffle)
assets/styles/app.css                        # global styles + design tokens (CSS custom properties)
translations/messages.nl.yaml                # all user-facing text
tests/Controller/, tests/Unit/               # PHPUnit functional and unit tests (tests/Support: helpers)
tests/js/games/                              # Vitest unit tests, mirroring assets/games/
.ddev/commands/web/qa                        # `ddev qa`
```

A **subject** (*vak*, e.g. rekenen) groups one or more **games** (e.g. tafels).

## Games

### Rekenen › Tafels (bubbels)

- `/rekenen` lists the tables 2–10 plus "Alle tafels" (a same-size tile centred under the 3 × 3 grid). `/rekenen/tafel/{2..10}` is the game for one table; other values give a 404.
- **Alle tafels** (`/rekenen/alle-tafels`): 20 different sums (`ALL_TABLES_COUNT`) picked at random from all 90 sums of tables 2–10, with a **2-minute** clock (`Tafels::ALL_TIME_LIMIT_MS`). It uses the same template and controller as a single table. Its results are stored with table number `Tafels::ALL` (= 0, `ALL_TABLES` in JS) and show up as their own badge and row.
- A round has ten bubbles, "multiplier × table" for multipliers 1–10 (Dutch notation: `2 × 6` belongs to *de tafel van 6*), in random order. The bubbles bob gently, and the bobbing is off under `prefers-reduced-motion`.
- Clicking a bubble enlarges it and shows an answer field plus an on-screen keypad (0–9, ⌫, OK). The physical keyboard also works: digits, Backspace, Enter to check, Escape to close. Typing a digit while no bubble is open opens the focused bubble, or else the first remaining one, and enters that digit.
- A correct answer pops the bubble and removes it. A wrong answer gives a friendly "try again" with a wiggle. After **3 wrong answers** (`MAX_ATTEMPTS`) the bubble **freezes**: it shrinks, turns grey, shows a ❄️ and its sum without the answer, and can't be opened any more. When the round is over, frozen bubbles show the correct answer (`bubble--revealed`; the answer is only put in the DOM then). There is no score.
- The round ends when every bubble is popped or frozen, whether or not the time has run out. It only counts as passed when nothing froze. With frozen bubbles the panel says "Je hebt X van de Y bubbels geknapt. Oefen nog even en probeer het opnieuw!", and the round is **not saved**, so badges only reflect complete rounds.
- **Ready dialog:** every round (also after "Nog een keer") opens with a modal `<dialog>` showing the time limit and "Ben je er klaar voor?". A small "← Terug" link goes back to the table picker (hidden once the countdown runs). The pupil presses "Ik ben er klaar voor!", sees a countdown of 3, 2, 1, "Start!" (`READY_COUNTDOWN_SECONDS`), and then the dialog closes. Escape can't close it. The bubble field stays empty until "Start!"; then the bubbles pop up one after another (`bubble--appearing`).
- **Timer:** one minute (`TIME_LIMIT_MS`). It starts when the ready countdown ends and is shown as m:ss plus a shrinking bar. If it runs out, the pupil sees "De tijd is om! Maak de bubbels rustig af." and keeps playing.
- When all ten bubbles are popped, a completion panel appears. Within the minute it says the pupil **passed** ("Super gedaan! … binnen een minuut geknapt!"). Otherwise it says "Alle bubbels zijn geknapt. Iets sneller de volgende keer!". In both cases it offers "Nog een keer" (new round, clock reset) and "Andere tafel".
- Logic: `assets/games/rekenen/tafels.js`. Wiring: `assets/controllers/tafels_controller.js`. Translated strings with `%placeholders%` are passed as Stimulus values and filled in with `format()`.
- The time limit comes from the server (`App\Game\Tafels::timeLimitMs()` → `data-tafels-time-limit-value`), so the client's verdict and the stored result always agree.
- **Saving:** for a logged-in player, the finished round is POSTed as JSON (`{durationMs}`) to `/rekenen/tafel/{table}/resultaat` (or `/rekenen/alle-tafels/resultaat`). The server decides `passed`. Guests see a "Wil je je sterren bewaren?" link instead.
- **Progress:** the picker and the player page show each table's best result: ⭐ + best time when done within the minute, ✓ + best time when completed more slowly.
- The game page sets `turbo-cache-control: no-cache`, and the controller's `connect()` rebuilds the board from scratch.

## Players (accounts)

- **No passwords and no e-mail.** Registering (`/speler/nieuw`) only asks for a name and an animal emoji, pre-filled with a fun suggestion: a positive adjective + animal ("Dappere Dolfijn") and that animal's emoji 🐬 (`App\Player\FunNameGenerator`; the word lists are content data, not translations).
  - The emoji is a **separate field** (`Player::$emoji`, nullable for older players). It's picked in a popup grid: a native `popover` with radio buttons, validated server-side against `FunNameGenerator::emojis()` (only animals for now). `emoji_picker_controller.js` only updates the toggle button and closes the popup. The header and greeting use `Player::getDisplayName()` ("Dappere Dolfijn 🐬").
  - All animals are de-words, because the adjective's "-e" form is wrong for het-words. Emoji are Emoji 13.0 or older and contain no ZWJ sequences, so they render on older Chromebooks and tablets. A unit test checks that every combination fits `Player::NAME_MAX_LENGTH`.
  - The 🎲 "Andere naam" button (`name_suggestions_controller.js`) cycles through 20 server-generated suggestions (name + matching emoji) without asking the server again. It stays hidden without JS.
- The account *is* its **player link** `/speler/{token}`: 32 random alphanumeric characters (`App\Security\PlayerToken`). Only the SHA-256 hash is stored, so a lost link can't be recovered. The UI tells pupils to keep it and to share it only with parents or the teacher.
- The browser remembers the token in the `scholi_player` cookie (HttpOnly, SameSite=Lax, 400 days, refreshed on every visit). `PlayerCookieAuthenticator` logs the player in on each request; the `main` firewall is stateless and the app uses no session.
- An unknown or invalid cookie never blocks anyone: the visitor continues as a guest and `PlayerCookieSubscriber` clears the cookie. Opening a valid link sets the cookie (switching player if needed); an unknown link gives a friendly 404.
- "Uitloggen" (POST with CSRF) deletes the cookie, for shared school computers.
- Never render the player link as an `<a>` (Turbo prefetches links). Show it in a readonly input with a copy button.
- JSON endpoints that change data must accept JSON only (`#[MapRequestPayload(acceptFormat: 'json')]`). Together with the SameSite=Lax cookie this is the CSRF protection. HTML forms use Symfony's (stateless) CSRF tokens.

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
- Keep time pressure gentle. A timer may set a goal ("binnen een minuut"), but running out of time must never block the child: they can always finish, and the feedback stays encouraging.
- Meet WCAG 2.2 AA: contrast, visible focus (`:focus-visible`), semantic HTML, `aria-live` for feedback, and support for `prefers-reduced-motion`.
- Never rely on colour or sound alone to carry meaning.

## Privacy (AVG/GDPR, minors)

- Load nothing from third parties: no CDNs, Google Fonts, analytics, trackers, ads or embeds. AssetMapper vendors all JS **and fonts** locally into `assets/vendor/` (git-ignored). `importmap:install` downloads them, and `composer install` runs it automatically.
- Fonts: pin the font's CSS with `ddev console importmap:require @fontsource/<font>/<weight>.css` and import it in `assets/app.js`. AssetMapper also downloads the woff2/woff files that CSS references. Never commit font files and never link to fonts.googleapis.com.
- Collect as little personal data as possible. Ask before adding anything that stores data about pupils.
- What is stored today: per player a self-chosen name (a fun name is suggested; the form asks not to use a real name) and animal emoji, the SHA-256 hash of the player link token, the creation date, and per finished tafels round the table, duration, passed flag and date. Nothing else: no e-mail, no IP addresses, no tracking.

## Code style

- PHP: `declare(strict_types=1);` in new files, `final` classes by default (except Doctrine entities, which need proxies), attribute routing, constructor injection. Follow `@Symfony` CS rules. PHPStan must pass at level max, and you may not lower the level or add baseline entries to get it passing.
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
