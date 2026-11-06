# Rekenen › Erbij en eraf

Shared round rules, saving and progress: [bubble-games.md](bubble-games.md).

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
