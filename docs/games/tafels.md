# Rekenen › Tafels

Shared round rules, saving and progress: [bubble-games.md](bubble-games.md).

- `/rekenen/tafels` lists the tables 1–12 plus "Alle tafels" (a same-size tile centred under the grid). `/rekenen/tafel/{1..12}` is the game for one table; other values give a 404.
- A round has ten bubbles, "multiplier × table" for multipliers 1–10 (Dutch notation: `2 × 6` belongs to *de tafel van 6*), in random order, with a **1-minute** clock (`Tafels::TIME_LIMIT_MS`). The level is the table number.
- **Alle tafels** (`/rekenen/alle-tafels`) is a second menu of mixes. Each mix is 20 different sums (`ALL_TABLES_COUNT`) picked at random from all sums of its tables, with a **2-minute** clock (`Tafels::ALL_TIME_LIMIT_MS`). Mixes use the same template and controller as a single table; `tafelsSums()` takes the list of tables.
  - **Tafels 2 tot 5 / 2 tot 10 / 2 tot 12** (`/rekenen/alle-tafels/2-tot-{5,10,12}`, `App\Game\TafelsMix`). Their results are stored under the enum's value as level: -5, 0 and -12. "2 tot 10" keeps 0, which was the only "alle tafels" before the mixes, so older results and guest cookies stay valid. Each mix has its own badge on the menu and its own row on the player page.
  - **Geoefende tafels** (`/rekenen/alle-tafels/geoefend`): every table (1–12) with a finished round (✓ or ⭐), from the database or the guest cookie. It needs at least 2 such tables (`Tafels::MIN_PRACTISED_TABLES`). Until then the tile isn't a link, and the URL redirects to the menu. Its rounds are never saved, because the tables change per pupil.
- Next level: table n → n+1, table 12 → Tafels 2 tot 5 → 2 tot 10 → 2 tot 12 (`TafelsMix::next()`). There is none after 2 tot 12 or after geoefende tafels.
- Logic: `assets/games/rekenen/tafels.js`. Wiring: `assets/controllers/tafels_controller.js`. Server: `TafelsController`, `Tafels`, `TafelsMix`.
