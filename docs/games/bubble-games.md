# Bubble games (shared by tafels, erbij en eraf and dictee)

Read this before changing how a round plays, is timed, saved or shown. The extension points for a
new game (hooks and template variables) are in [AGENTS.md](../../AGENTS.md#bubble-games-extension-points).

Each game only decides **which sums** (or words) a round asks and its texts. Everything else is shared:
- the round rules: `assets/games/shared/round.js`
- the DOM wiring: `assets/controllers/bubble_game.js`, which the game's controller extends with `createSums()`
- the markup: `templates/game/bubble_game.html.twig`, which the game page extends and gives `controller`, `values` and its texts
- the server side: `AbstractBubbleGameController`, `Game`, `GameResult`, `LevelProgress`
- the texts: `game.bubbles.*`

The bubble is only how a task is shown right now. User-facing text always says **sommen** ("Klik op een som", "Sommen over: 3") for sums and **woorden** for dictee, never "bubbels".

## Tasks and answering

- A task is a sum `{left, operator: plus|min|keer, right}`. It is shown with `+`, `−` (U+2212) or `×` (`formatSum()`), and screen readers get "%left% plus/min/keer %right%". The bubbles bob gently, and the bobbing is off under `prefers-reduced-motion`.
- Clicking a bubble enlarges it and shows an answer field plus an on-screen keypad (0–9, ⌫, OK). The physical keyboard also works: digits, Backspace, Enter to check, Escape to close. Typing a digit while no bubble is open opens the focused bubble, or else the first remaining one, and enters that digit. The next sum **opens by itself**: the first one after "Start!", and after a pop or a freeze the next remaining one in order (wrapping around). The feedback ("Goed zo!") stays visible. The pupil can tap another bubble instead, for example when they don't know the answer straight away. If no bubble is open for 3 seconds (`AUTO_OPEN_DELAY_MS`, e.g. after Escape or a pause), the focused bubble, or else the first remaining one, opens as well.
- A correct answer pops the bubble and removes it, with a burst of confetti in the bubble's colour (decoration only, none under `prefers-reduced-motion`). A wrong answer gives a friendly "try again" with a wiggle. After **3 wrong answers** (`MAX_ATTEMPTS`) the bubble **freezes**: it shrinks, turns grey, shows a ❄️ and its sum without the answer, and can't be opened any more. When the round is over, frozen bubbles show the correct answer (`bubble--revealed`; the answer is only put in the DOM then). There is no score.
- The round ends when every bubble is popped or frozen, whether or not the time has run out. It only counts as passed when nothing froze. With frozen bubbles the panel says "Je hebt X van de Y sommen goed gemaakt. Oefen nog even en probeer het opnieuw!", and the round is **not saved**, so badges only reflect complete rounds.

## Ready dialog, timer, pause

- **Ready dialog:** every round (also after "Nog een keer") opens with a modal `<dialog>` showing the time limit and "Ben je er klaar voor?". A small "← Terug" link goes back to the game's picker (hidden once the countdown runs). The pupil presses "Ik ben er klaar voor!", sees a countdown of 3, 2, 1, "Start!" (`READY_COUNTDOWN_SECONDS`), and then the dialog closes. Escape can't close it. The bubble field stays empty until "Start!"; then the bubbles pop up one after another (`bubble--appearing`).
- **Timer:** it starts when the ready countdown ends and is shown as m:ss plus a shrinking bar. If it runs out, the pupil sees "De tijd is om! Maak de sommen rustig af." and keeps playing. The limit comes from the server (`Game::timeLimitMs($level)` → `data-<game>-time-limit-value`), so the client's verdict and the stored result always agree.
- **Pause:** a click on a menu link (anything in the site header) while a round is being played (after "Start!", before the last bubble) doesn't leave the page. The round pauses (`pauseRound()`/`resumeRound()`: the clock, the auto-open and the bobbing stop) and a dialog asks "Wil je stoppen?". "Verder spelen" or Escape resumes; the paused time counts neither for the limit nor for the practice time. "Stoppen" follows the link, and the unfinished round doesn't count. A Ctrl/⌘-click (new tab) is left alone.

## Completion panel

- When all bubbles are popped, a completion panel appears and the game's header (title, intro, timer) is hidden. Within the time it says the pupil **passed** ("Helemaal goed! … sneller dan een minuut gemaakt."). Otherwise it says "Je hebt alle sommen gemaakt. Iets sneller de volgende keer!". In both cases a line adds the round's time in words, in brand blue: "Je deed het dit keer in **41 seconden**." (`formatDuration()`; not shown when bubbles froze). It offers "Nog een keer" (new round, clock reset) and a link back to the picker. After **passing**, a "Volgende: …" link to the next level (`next`, from the game's controller) replaces "Nog een keer" as the main button. When the round is too slow or sums froze, or when there is no next level, "Nog een keer" is the main button.
- Translated strings with `%placeholders%` are passed as Stimulus values and filled in with `format()`.

## Saving and progress

- **Saving:** the finished round is POSTed as JSON (`{durationMs}`) to the game's `…/resultaat` route, and the server decides `passed`.
  - Each game stores its rounds per **level**, a number whose meaning belongs to the game (see the game's page in this folder).
  - For a player, the result goes to the database as a `GameResult` (`game`, `level`), with a 201.
  - For a guest, it goes into the **guest progress cookie** (200). The panel says nothing about saving; only a failed save shows a note ("Je resultaat kon niet worden bewaard …").
- **Progress:** the pickers and the player page show each level's best result: ⭐ + best time when done within the time, ✓ + best time when completed more slowly. Guests see the badges from their cookie on the pickers.
- **Guest progress cookie** (`App\Security\GuestProgressCookie`, `App\Player\GuestProgress`):
  - `scholi_guest`: HttpOnly, SameSite=Lax, 400 days. It holds only the best time and date per game and level (never `passed`), and the practice seconds per day of the last seven days.
  - Version 2 is `{"v":2,"r":{"<game>":{"<level>":[ms, ts]}}}`. Version 1 (tafels only) is still read as tafels.
  - Signed with an HMAC (a key derived from `APP_SECRET`), so it only holds results the server accepted. A tampered or malformed cookie is ignored, and cleared on the pickers.
  - Nothing about guests is stored on the server.
- Game pages set `turbo-cache-control: no-cache`, and the controller's `connect()` rebuilds the board from scratch.

## Practice time

`App\Player\PracticeTime`: the whole minutes played in the last seven days.
- **What counts:** every finished round, also rounds with frozen bubbles and unsaved rounds such as "geoefende tafels". A round that is left before it ends doesn't count.
- **Measuring:** `practiceSeconds()` counts the whole seconds from the clock's start (after 3-2-1) to the end of the round, including time after the limit.
- **Sending:** when the round ends, the controller POSTs `{seconds}` as JSON to `/oefentijd` (`PracticeController`, 1–3600 s). It does this first and only then saves the result: the two calls go one after the other because both rewrite the guest cookie.
- **Storing:** seconds are summed per calendar day (Europe/Amsterdam). The window is today and the 6 days before (`WINDOW_DAYS`); the minutes are the summed seconds ÷ 60, rounded down.
  - For a player, they go in `PracticeDay` (one row per player and day). Each write deletes that player's rows from before the window.
  - For a guest, they go in the cookie under `"p":{"<Y-m-d>":seconds}`, also only for the window.
- **Showing:** the endpoint answers `{minutes}`. The header (⏱️) and the player page show the total; the completion panel doesn't.

## Header

It has the colour of the page (the footer fades into a faint shade at the very bottom). On the left is the Scholi logo (`assets/images/scholi-logo.webp`, a blue felt cloud with the name, linking home); on the right is a blue pill with a slightly darker edge for the player. If the logo can't be loaded, `brand_logo_controller.js` turns the logo link into the same kind of blue pill with the text "Scholi" (`site-header__brand--text`; the text is in the markup, hidden while the logo works). For a player, the pill shows next to the name the number of badges (⭐: levels with a ⭐ or ✓, over all games, `GameResultRepository::badgeCount()`) and the practice minutes (⏱️). It comes from the Twig function `player_stats()` (`App\Twig\PlayerStatsExtension`) and has one visually hidden sentence for screen readers. On phones (up to 40rem) the name is visually hidden and only the emoji shows (👤 without one). Guests see "Voortgang bewaren" instead.

## Images

- `assets/images/scholi-logo.webp`: the Scholi logo (480×234, transparent) in the header; the link falls back to a blue pill with the text.
- `assets/images/scholi-mascot.webp`: the blue elephant mascot (192×192, transparent) in the "Meer over Scholi" popup.
- `assets/images/scholi-mascot-large.webp`: the same at 384×384, bottom right of every task selection screen (never in a game, never in the header). Fixed in the corner of the window on a monitor (≥ 90rem, beside the page column), in the footer on smaller screens. A page opts in by setting `mascot` to true.
