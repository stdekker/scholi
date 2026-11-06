# Players (accounts)

The rules that must not be broken (no passwords, never render the link as `<a>`, JSON-only POSTs, guest progress moves on registration only) are in [AGENTS.md](../AGENTS.md#players-accounts). This page has the details.

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
