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
| Speech (dev time only) | **ElevenLabs** text-to-speech (`@elevenlabs/elevenlabs-js`, a dev dependency) records the dictee words; the mp3 files are generated and git-ignored, see **Taal › Dictee** |
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
| Record dictee words | `ddev npm run audio:dictee` (only words without a recording; add `-- --force` for all). Needs `ELEVENLABS_API_KEY` in `.env.local`. The recordings are not in git: run it on every fresh checkout, before `ddev qa` and before a deploy |
| **All quality checks** | `ddev qa` |
| PHP only | `ddev composer qa` (or `cs`, `cs:fix`, `phpstan`, `test`; `test` first migrates and validates the test DB) |
| JS only | `ddev npm run lint`, `ddev npm run format`, `ddev npm test` |
| Database query | `ddev mysql -e 'SELECT 1'` |
| Migrations | `ddev console make:migration`, `ddev console doctrine:migrations:migrate` (files in `src/Migrations/`) |
| Site URL | `ddev launch --print-url` → https://scholi.ddev.site |
| Mail (Mailpit) | `ddev launch -m --print-url` |

DDEV generates `.env.local` itself (database and mailer settings). Don't commit it. The test env doesn't read `.env.local`, so the committed defaults in `.env` also point at the DDEV database.

## Deployment (production: scholi.nl)

`bin/deploy` (run on the host, not in DDEV; it uses your `vimexx` SSH alias) deploys the **committed HEAD** to the Vimexx/DirectAdmin hosting at `/home/u150449p142020/domains/scholi.nl`.

- The server's Composer and git are too old, so the release is **built locally in DDEV**: `git archive`, `composer install --no-dev`, `importmap:install` and `asset-map:compile`. It is then rsynced to `app/releases/<timestamp>-<sha>`, with unchanged files hard-linked.
- On the server it links `app/shared/.env.local` and `app/shared/var/log`, then runs `cache:clear` and `doctrine:migrations:migrate` with `/opt/alt/php84/usr/bin/php`. After that it switches `app/current` atomically and keeps 5 releases. `public_html` is a symlink to `app/current/public`.
- One-time setup: `bin/deploy --init` creates the folders and an `app/shared/.env.local` template with `APP_ENV=prod` and a random `APP_SECRET`. Fill in `DATABASE_URL` for the database created in DirectAdmin by hand on the server. Database passwords never go into git or the script.
- `bin/deploy --dry-run` builds and checks everything without changing the server.
- The dictee recordings (`assets/audio/`) are git-ignored, so they are not in `git archive`: the script copies them from the working tree before `asset-map:compile` and fails when they are missing.
- The website's PHP version (DirectAdmin / CloudLinux selector) must be **8.4**. Production uses Apache, so `public/.htaccess` is the front controller; DDEV uses nginx.
- Overridable via environment variables: `DEPLOY_HOST`, `DEPLOY_PATH`, `DEPLOY_PHP`, `KEEP_RELEASES`.

Tests use the `db_test` database, which a `post-start` hook in `.ddev/config.yaml` creates (the `db` user can't create databases). DAMA wraps every test in a transaction that is rolled back.

## Project layout

```
src/Controller/HomeController.php           # / – subject overview
src/Controller/Game/<Subject>Controller.php  # a subject's game list, e.g. RekenenController (/rekenen)
src/Controller/Game/<Game>Controller.php     # one controller per game, e.g. TafelsController, ErbijErafController
src/Controller/Game/TaalController.php       # /taal – the taal games
src/Controller/Game/DicteeController.php     # /taal/dictee – picker, game, result
src/Controller/Game/AbstractBubbleGameController.php  # shared: progress, storing a round, layout variables
src/Controller/PlayerController.php          # /speler, /speler/nieuw, /speler/{token}
src/Entity/, src/Repository/                 # Doctrine: Player, GameResult
src/Game/                                    # server-side game rules (Game enum, Tafels, TafelsMix, ErbijEraf*, Dictee*, dictee_words.json), LevelProgress, RoundTime, input DTO
src/Security/                                # player link token, cookie, authenticator, signed guest progress cookie
src/Player/                                  # FunNameGenerator, GuestProgress
src/Migrations/                              # Doctrine migrations
templates/base.html.twig                     # layout (lang="nl"; blocks: meta, title, body; a page sets `mascot` to true for the elephant in the footer), header, footer with the "Meer over Scholi" popover
templates/home/                              # homepage
templates/game/bubble_game.html.twig         # shared layout of the bubble games (dialog, bubbles, keypad, completion panel)
templates/game/<subject>/                    # subject + game pages, e.g. game/rekenen/tafel.html.twig
templates/player/                            # registration, player page, progress badge
assets/app.js                                # AssetMapper entrypoint
assets/controllers/<game>_controller.js      # Stimulus controllers: DOM wiring only, e.g. tafels_controller.js
assets/controllers/bubble_game.js            # base class of the bubble games' controllers (not registered itself)
assets/games/<subject>/<game>.js             # pure game logic (ES modules), e.g. rekenen/tafels.js, taal/dictee.js
assets/audio/taal/dictee/<word>.mp3          # recordings of the dictee words (git-ignored, made by bin/generate-dictee-audio.mjs)
assets/games/shared/                         # pure helpers shared by games: round.js (round state, clock), input.js, format.js, random.js
assets/styles/app.css                        # global styles + design tokens (CSS custom properties)
assets/images/scholi-logo.webp               # the Scholi logo (480×234, transparent) in the header; the link falls back to a blue pill with the text
assets/images/scholi-mascot.webp             # the blue elephant mascot (192×192, transparent) in the "Meer over Scholi" popup
assets/images/scholi-mascot-large.webp       # the same at 384×384: bottom right of every task selection screen (never in a game, never in the header): fixed in the corner of the window on a monitor (≥ 90rem, beside the page column), in the footer on smaller screens
translations/messages.nl.yaml                # all user-facing text
bin/generate-dictee-audio.mjs                # dev script: records the dictee words with ElevenLabs
tests/Controller/, tests/Unit/               # PHPUnit functional and unit tests (tests/Support: helpers)
tests/js/games/                              # Vitest unit tests, mirroring assets/games/
.ddev/commands/web/qa                        # `ddev qa`
```

A **subject** (*vak*, e.g. rekenen) groups one or more **games** (e.g. tafels).

## Games

`/rekenen` lists the rekenen games: **Tafels** (`/rekenen/tafels`) and **Erbij en eraf** (`/rekenen/erbij-eraf`).

### Bubble games (shared by tafels, erbij en eraf and dictee)

Each game only decides **which sums** a round asks and its texts. Everything else is shared:
- the round rules: `assets/games/shared/round.js`
- the DOM wiring: `assets/controllers/bubble_game.js`, which the game's controller extends with `createSums()`
- the markup: `templates/game/bubble_game.html.twig`, which the game page extends and gives `controller`, `values` and its texts
- the server side: `AbstractBubbleGameController`, `Game`, `GameResult`, `LevelProgress`
- the texts: `game.bubbles.*`

The bubble is only how a task is shown right now. User-facing text always says **sommen** ("Klik op een som", "Sommen over: 3") for sums and **woorden** for dictee, never "bubbels". A game whose tasks are not sums changes the shared parts through hooks, not by copying them:
- in `bubble_game.js`: `newRound()` (with `createTaskRound()`), `fillQuestion()`, `isCorrect()`, `correctParams()`, `acceptsKey()`/`appendKey()` (what typing accepts), `taskActivated()` and `sumLabel()`;
- in `bubble_game.html.twig`: the variables `text_keys` (translation keys replacing the texts about sommen), `keypad: false` (no digits, only OK), `answer_max_length`, `replay_label` and `modifier`.

- A task is a sum `{left, operator: plus|min|keer, right}`. It is shown with `+`, `−` (U+2212) or `×` (`formatSum()`), and screen readers get "%left% plus/min/keer %right%". The bubbles bob gently, and the bobbing is off under `prefers-reduced-motion`.
- Clicking a bubble enlarges it and shows an answer field plus an on-screen keypad (0–9, ⌫, OK). The physical keyboard also works: digits, Backspace, Enter to check, Escape to close. Typing a digit while no bubble is open opens the focused bubble, or else the first remaining one, and enters that digit. The next sum **opens by itself**: the first one after "Start!", and after a pop or a freeze the next remaining one in order (wrapping around). The feedback ("Goed zo!") stays visible. The pupil can tap another bubble instead, for example when they don't know the answer straight away. If no bubble is open for 3 seconds (`AUTO_OPEN_DELAY_MS`, e.g. after Escape or a pause), the focused bubble, or else the first remaining one, opens as well.
- A correct answer pops the bubble and removes it, with a burst of confetti in the bubble's colour (`burst()`, decoration only, none under `prefers-reduced-motion`). A wrong answer gives a friendly "try again" with a wiggle. After **3 wrong answers** (`MAX_ATTEMPTS`) the bubble **freezes**: it shrinks, turns grey, shows a ❄️ and its sum without the answer, and can't be opened any more. When the round is over, frozen bubbles show the correct answer (`bubble--revealed`; the answer is only put in the DOM then). There is no score.
- The round ends when every bubble is popped or frozen, whether or not the time has run out. It only counts as passed when nothing froze. With frozen bubbles the panel says "Je hebt X van de Y sommen goed gemaakt. Oefen nog even en probeer het opnieuw!", and the round is **not saved**, so badges only reflect complete rounds.
- **Ready dialog:** every round (also after "Nog een keer") opens with a modal `<dialog>` showing the time limit and "Ben je er klaar voor?". A small "← Terug" link goes back to the game's picker (hidden once the countdown runs). The pupil presses "Ik ben er klaar voor!", sees a countdown of 3, 2, 1, "Start!" (`READY_COUNTDOWN_SECONDS`), and then the dialog closes. Escape can't close it. The bubble field stays empty until "Start!"; then the bubbles pop up one after another (`bubble--appearing`).
- **Timer:** it starts when the ready countdown ends and is shown as m:ss plus a shrinking bar. If it runs out, the pupil sees "De tijd is om! Maak de sommen rustig af." and keeps playing. The limit comes from the server (`Game::timeLimitMs($level)` → `data-<game>-time-limit-value`), so the client's verdict and the stored result always agree.
- **Pause:** a click on a menu link (anything in the site header) while a round is being played (after "Start!", before the last bubble) doesn't leave the page. The round pauses (`pauseRound()`/`resumeRound()`: the clock, the auto-open and the bobbing stop) and a dialog asks "Wil je stoppen?". "Verder spelen" or Escape resumes; the paused time counts neither for the limit nor for the practice time. "Stoppen" follows the link, and the unfinished round doesn't count. A Ctrl/⌘-click (new tab) is left alone.
- When all bubbles are popped, a completion panel appears and the game's header (title, intro, timer) is hidden. Within the time it says the pupil **passed** ("Helemaal goed! … sneller dan een minuut gemaakt."). Otherwise it says "Je hebt alle sommen gemaakt. Iets sneller de volgende keer!". In both cases a line adds the round's time in words, in brand blue: "Je deed het dit keer in **41 seconden**." (`formatDuration()`; not shown when bubbles froze). It offers "Nog een keer" (new round, clock reset) and a link back to the picker. After **passing**, a "Volgende: …" link to the next level (`next`, from the game's controller) replaces "Nog een keer" as the main button. When the round is too slow or sums froze, or when there is no next level, "Nog een keer" is the main button.
- Translated strings with `%placeholders%` are passed as Stimulus values and filled in with `format()`.
- **Saving:** the finished round is POSTed as JSON (`{durationMs}`) to the game's `…/resultaat` route, and the server decides `passed`.
  - Each game stores its rounds per **level**, a number whose meaning belongs to the game (see below).
  - For a player, the result goes to the database as a `GameResult` (`game`, `level`), with a 201.
  - For a guest, it goes into the **guest progress cookie** (200). The panel says nothing about saving; only a failed save shows a note ("Je resultaat kon niet worden bewaard …").
- **Progress:** the pickers and the player page show each level's best result: ⭐ + best time when done within the time, ✓ + best time when completed more slowly. Guests see the badges from their cookie on the pickers.
- **Guest progress cookie** (`App\Security\GuestProgressCookie`, `App\Player\GuestProgress`):
  - `scholi_guest`: HttpOnly, SameSite=Lax, 400 days. It holds only the best time and date per game and level (never `passed`), and the practice seconds per day of the last seven days.
  - Version 2 is `{"v":2,"r":{"<game>":{"<level>":[ms, ts]}}}`. Version 1 (tafels only) is still read as tafels.
  - Signed with an HMAC (a key derived from `APP_SECRET`), so it only holds results the server accepted. A tampered or malformed cookie is ignored, and cleared on the pickers.
  - Nothing about guests is stored on the server.
- Game pages set `turbo-cache-control: no-cache`, and the controller's `connect()` rebuilds the board from scratch.
- **Practice time** (`App\Player\PracticeTime`): the whole minutes played in the last seven days.
  - **What counts:** every finished round, also rounds with frozen bubbles and unsaved rounds such as "geoefende tafels". A round that is left before it ends doesn't count.
  - **Measuring:** `practiceSeconds()` counts the whole seconds from the clock's start (after 3-2-1) to the end of the round, including time after the limit.
  - **Sending:** when the round ends, the controller POSTs `{seconds}` as JSON to `/oefentijd` (`PracticeController`, 1–3600 s). It does this first and only then saves the result: the two calls go one after the other because both rewrite the guest cookie.
  - **Storing:** seconds are summed per calendar day (Europe/Amsterdam). The window is today and the 6 days before (`WINDOW_DAYS`); the minutes are the summed seconds ÷ 60, rounded down.
    - For a player, they go in `PracticeDay` (one row per player and day). Each write deletes that player's rows from before the window.
    - For a guest, they go in the cookie under `"p":{"<Y-m-d>":seconds}`, also only for the window.
  - **Showing:** the endpoint answers `{minutes}`. The header (⏱️) and the player page show the total; the completion panel doesn't.
- **Header:** it has the colour of the page (the footer fades into a faint shade at the very bottom). On the left is the Scholi logo (`assets/images/scholi-logo.webp`, a blue felt cloud with the name, linking home); on the right is a blue pill with a slightly darker edge for the player. If the logo can't be loaded, `brand_logo_controller.js` turns the logo link into the same kind of blue pill with the text "Scholi" (`site-header__brand--text`; the text is in the markup, hidden while the logo works). For a player, the pill shows next to the name the number of badges (⭐: levels with a ⭐ or ✓, over all games, `GameResultRepository::badgeCount()`) and the practice minutes (⏱️). It comes from the Twig function `player_stats()` (`App\Twig\PlayerStatsExtension`) and has one visually hidden sentence for screen readers. On phones (up to 40rem) the name is visually hidden and only the emoji shows (👤 without one). Guests see "Voortgang bewaren" instead.

### Rekenen › Tafels

- `/rekenen/tafels` lists the tables 1–12 plus "Alle tafels" (a same-size tile centred under the grid). `/rekenen/tafel/{1..12}` is the game for one table; other values give a 404.
- A round has ten bubbles, "multiplier × table" for multipliers 1–10 (Dutch notation: `2 × 6` belongs to *de tafel van 6*), in random order, with a **1-minute** clock (`Tafels::TIME_LIMIT_MS`). The level is the table number.
- **Alle tafels** (`/rekenen/alle-tafels`) is a second menu of mixes. Each mix is 20 different sums (`ALL_TABLES_COUNT`) picked at random from all sums of its tables, with a **2-minute** clock (`Tafels::ALL_TIME_LIMIT_MS`). Mixes use the same template and controller as a single table; `tafelsSums()` takes the list of tables.
  - **Tafels 2 tot 5 / 2 tot 10 / 2 tot 12** (`/rekenen/alle-tafels/2-tot-{5,10,12}`, `App\Game\TafelsMix`). Their results are stored under the enum's value as level: -5, 0 and -12. "2 tot 10" keeps 0, which was the only "alle tafels" before the mixes, so older results and guest cookies stay valid. Each mix has its own badge on the menu and its own row on the player page.
  - **Geoefende tafels** (`/rekenen/alle-tafels/geoefend`): every table (1–12) with a finished round (✓ or ⭐), from the database or the guest cookie. It needs at least 2 such tables (`Tafels::MIN_PRACTISED_TABLES`). Until then the tile isn't a link, and the URL redirects to the menu. Its rounds are never saved, because the tables change per pupil.
- Next level: table n → n+1, table 12 → Tafels 2 tot 5 → 2 tot 10 → 2 tot 12 (`TafelsMix::next()`). There is none after 2 tot 12 or after geoefende tafels.
- Logic: `assets/games/rekenen/tafels.js`. Wiring: `assets/controllers/tafels_controller.js`. Server: `TafelsController`, `Tafels`, `TafelsMix`.

### Rekenen › Erbij en eraf

- `/rekenen/erbij-eraf` asks two simple questions on one page. First "Wat wil je oefenen?": a row of three toggle links (`ErbijErafOperation`) for **Erbij** (plussommen), **Eraf** (minsommen) and **Door elkaar** (5 + 5). They reload the page with `?soort=…`, with erbij as the default; the chosen one has `aria-current`. Then "Kies je niveau": six level tiles (`ErbijErafLevel`, roughly groep 3 to 5), each with an example sum and the badge for that choice. The game's back links return to the same `?soort=`.
- `/rekenen/erbij-eraf/{level}/{operation}` is the game, for example `/rekenen/erbij-eraf/tot-20-brug/eraf`. Unknown slugs give a 404.
- A round has **ten** different sums, picked at random from every sum the level allows (`allSums()`/`pickSums()` in `assets/games/rekenen/erbij_eraf.js`). Terms are never 0, answers never negative and at most 100.

| Level (slug) | Erbij, e.g. | Eraf, e.g. | Rule | Clock |
|---|---|---|---|---|
| Tot 10 (`tot-10`) | 3 + 4 | 9 − 5 | everything within 10 | 1 min |
| Tot 20 zonder brug (`tot-20`) | 12 + 5 | 17 − 4 | 11–19 with a one-digit number, the units stay below ten | 1 min |
| Tot 20 met brug (`tot-20-brug`) | 8 + 5 | 13 − 6 | across the ten | 1 min |
| Tientallen tot 100 (`tientallen`) | 30 + 40 | 90 − 50 | only tens | 1 min |
| Tot 100 zonder brug (`tot-100`) | 34 + 25 | 68 − 25 | above 20, the units don't cross the ten | **2 min** |
| Tot 100 met brug (`tot-100-brug`) | 38 + 7 | 52 − 18 | above 20, the units cross the ten | **2 min** |

- A round is stored as level `ErbijEraf::storedLevel()` = level × 10 + operation code (erbij 1, eraf 2, door elkaar 3), for example 32 = tot 20 met brug, eraf.
- Next level: the next level with the same operation. After tot 100 met brug comes tot 10 of the next operation (erbij → eraf → door elkaar); there is none after door elkaar.
- Logic: `assets/games/rekenen/erbij_eraf.js`. Wiring: `assets/controllers/erbij_eraf_controller.js`. Server: `ErbijErafController`, `ErbijEraf`, `ErbijErafLevel`, `ErbijErafOperation`.

### Taal › Dictee

- `/taal` lists the taal games (only **Dictee** for now). `/taal/dictee` asks two simple questions on one page: first "Kies je groep" (toggle links `?groep=6|8`, groep 6 by default, the chosen one has `aria-current`), then "Hoe wil je oefenen?": four level tiles (`DicteeLevel`), each with its description and badge. `/taal/dictee/{groep-6|groep-8}/{level}` is the game, for example `/taal/dictee/groep-8/streepjes`. Unknown slugs give a 404.
- The game speaks a word and the pupil writes it. A round has **ten** different words, picked at random from the groep's list in `src/Game/dictee_words.json` (240 words each for groep 6 and groep 8, in a mix of spelling topics; other groepen follow later). Words are lowercase letters a–z only: no accents, no spaces.
- The level decides what the pupil sees of the word while writing it (`DicteeLevel::mode()`; the hint is made in `assets/games/taal/dictee.js`):

| Level (slug) | Mode | The bubble shows |
|---|---|---|
| Overschrijven (`overschrijven`) | full | every letter |
| Letters aanvullen (`aanvullen`) | partial | about half of the letters: at least one blank, at least one letter, random each round |
| Streepjes (`streepjes`) | blanks | one blank (underline) per letter |
| Zonder hulp (`zonder-hulp`) | hidden | nothing, only 🔊 |

- Every level has a **3-minute** clock (`RoundTime::THREE_MINUTES_MS`; the badge says "Binnen drie minuten"). A round is stored as level `Dictee::storedLevel()` = groep × 10 + level, for example 83 = groep 8, streepjes. Next level: the next level of the same groep; none after zonder hulp.
- It is a bubble game with word bubbles: wide rounded cards (`bubble-game--words`) instead of circles, because a word needs more room. Opening a bubble **plays its word** (also the first one right after "Start!"), and a "Luister nog eens" button inside the open bubble plays it again. Typing works with the keyboard: letters only, lowercased, at most `Dictee::ANSWER_MAX_LENGTH` (20). The dock only has the OK button (Enter works too). `checkWord()` ignores capitals and spaces around the word; anything else must be exact. Autocorrect, autocapitalize and spellcheck are off on the field. Wrong answers, freezing, pause, timer, practice time, saving and progress work as in the other bubble games. A screen reader gets "Woord 3: k, leeg, t" for a bubble.
- **Recordings:** `assets/audio/taal/dictee/<word>.mp3`, made with ElevenLabs by `bin/generate-dictee-audio.mjs` (voice `7qdUFMklKPaaAVMsBTBt`, model `eleven_v4_turbo` with `language_code` nl so a single word is never taken for another language, stability 1; overridable with `ELEVENLABS_VOICE_ID`, `ELEVENLABS_MODEL_ID` and `ELEVENLABS_STABILITY`. Eleven v4 has no speed setting; with an older model (`eleven_flash_v2_5`) `ELEVENLABS_SPEED` (default 0.7, the slowest) applies). The API has no volume setting, so the script asks for raw audio, brings every word to the same loudness without clipping and trims the silence around it and encodes a small mono mp3 itself (24 kHz, 40 kbps, `@breezystack/lamejs`, a dev dependency; no ffmpeg needed). They are generated files, so `/assets/audio/` is git-ignored; AssetMapper serves them, and the page gives the controller a word → URL map (the `audio` value). The browser never contacts ElevenLabs and production needs no key. After adding words to the list, and on a fresh checkout, run `ddev npm run audio:dictee` (it only makes the missing ones). `bin/deploy` copies the recordings from your working tree into the release and stops when there are none. A test fails when a word has no recording or a recording has no word, so `ddev qa` needs them too.
- Some browsers (iPads) only play sound that starts from a click. "Ik ben er klaar voor!" is that click: `unlockAudio()` plays a recording silently then, so the words that follow may start on their own. A recording that can't be played shows "Het geluid werkt niet …" in the status line.
- Logic: `assets/games/taal/dictee.js`. Wiring: `assets/controllers/dictee_controller.js`. Server: `DicteeController`, `Dictee`, `DicteeGroup`, `DicteeLevel`.

## Players (accounts)

- **No passwords and no e-mail.** Registering (`/speler/nieuw`) only asks for a name and an animal emoji, pre-filled with a fun suggestion: a positive adjective + animal ("Dappere Dolfijn") and that animal's emoji 🐬 (`App\Player\FunNameGenerator`; the word lists are content data, not translations).
  - The emoji is a **separate field** (`Player::$emoji`, nullable for older players). It's picked in a popup grid: a native `popover` with radio buttons, validated server-side against `FunNameGenerator::emojis()` (only animals for now). `emoji_picker_controller.js` only updates the toggle button and closes the popup. The header and greeting use `Player::getDisplayName()` ("Dappere Dolfijn 🐬").
  - All animals are de-words, because the adjective's "-e" form is wrong for het-words. Emoji are Emoji 13.0 or older and contain no ZWJ sequences, so they render on older Chromebooks and tablets. A unit test checks that every combination fits `Player::NAME_MAX_LENGTH`.
  - The 🎲 "Andere naam" button (`name_suggestions_controller.js`) cycles through 20 server-generated suggestions (name + matching emoji) without asking the server again. It stays hidden without JS.
- The account *is* its **player link** `/speler/{token}`: 32 random alphanumeric characters (`App\Security\PlayerToken`). Only the SHA-256 hash is stored, so a lost link can't be recovered. The UI tells pupils to keep it and to share it only with parents or the teacher.
- The browser remembers the token in the `scholi_player` cookie (HttpOnly, SameSite=Lax, 400 days, refreshed on every visit). `PlayerCookieAuthenticator` logs the player in on each request; the `main` firewall is stateless and the app uses no session.
- An unknown or invalid cookie never blocks anyone: the visitor continues as a guest and `PlayerCookieSubscriber` clears the cookie. Opening a valid link sets the cookie (switching player if needed); an unknown link gives a friendly 404.
- "Uitloggen" (POST with CSRF) deletes the cookie, for shared school computers.
- **Guest progress moves to the account on registration only**: `/speler/nieuw` turns each guest result (of every game) into a `GameResult` (original date, `passed` recomputed) and each practice day into a `PracticeDay`, then clears the guest cookie. Opening an existing player link or logging out leaves the guest cookie alone, so on a shared computer a guest's stars never end up in another child's account.
- Never render the player link as an `<a>` (Turbo prefetches links). Show it in a readonly input with a copy button.
- JSON endpoints that change data must accept JSON only (`#[MapRequestPayload(acceptFormat: 'json')]`). Together with the SameSite=Lax cookie this is the CSRF protection. HTML forms use Symfony's (stateless) CSRF tokens.

### Frontend architecture rule

- **Game logic** lives in `assets/games/<game>/*.js`. These are pure ES modules: no DOM access, no bare imports (no `@hotwired/...` or other importmap packages), and only relative imports. Inject randomness and time as parameters so the code is deterministic in tests.
- **Stimulus controllers** in `assets/controllers/` import that logic with a relative path (e.g. `../games/rekenen/tafels.js`). They only connect the DOM to it: targets, actions, rendering. Shared controller code lives in a base class file without the `_controller.js` suffix (`bubble_game.js`), so Stimulus doesn't register it on its own.
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
- The "Meer over Scholi" popup links to the GitHub repository (`github_url`, a Twig global in `config/packages/twig.yaml`). It is a plain link that opens a new tab, with the GitHub mark as an inline SVG: nothing is loaded from GitHub.
- Speech: the dictee recordings are made at dev time from the word lists only, so no pupil data is ever sent to ElevenLabs. The API key lives in `.env.local` (git-ignored) and is never needed in production.
- Collect as little personal data as possible. Ask before adding anything that stores data about pupils.
- What is stored today: per player a self-chosen name (a fun name is suggested; the form asks not to use a real name) and animal emoji, the SHA-256 hash of the player link token, the creation date, per finished round the game, level (e.g. the table), duration, passed flag and date, and the seconds practised per day for the last seven days only (older days are deleted on the player's next round). For guests the server stores nothing: their best time and date per game and level, and their practice seconds per day, live only in the signed `scholi_guest` cookie in their browser. Nothing else: no e-mail, no IP addresses, no tracking.

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

1. A game controller with Dutch routes (`src/Controller/Game/<Game>Controller.php`; a bubble game extends `AbstractBubbleGameController`) and a card in the subject's game list (`templates/game/<subject>/index.html.twig`). A new subject also needs a card on the homepage (`templates/home/index.html.twig`).
2. `templates/game/<subject>/<game>.html.twig` that mounts `data-controller="<game>"`. A bubble game extends `game/bubble_game.html.twig` instead.
3. `assets/controllers/<game>_controller.js` (thin; a bubble game extends `bubble_game.js` and implements `createSums()`) + `assets/games/<subject>/<game>.js` (logic). Reuse `assets/games/shared/`.
4. If its rounds are saved: a case in the `Game` enum with its level rules, so results and the guest cookie accept it.
5. `tests/js/games/<subject>/<game>.test.js` + functional tests for the new URLs.
6. Translation keys under `game.<subject>.<game>.*` (shared bubble texts are in `game.bubbles.*`).
7. Describe the mechanics under **Games** in this file.
8. Run `ddev qa`.
