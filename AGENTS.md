# Tostão: notes for coding agents and new contributors

Self-hosted household finance app for people who share a home. Laravel 13, Blade, Alpine, Tailwind 3, Chart.js, Pest. SQLite by default; MySQL works too.

## Commands

```sh
composer setup            # install, .env, key, migrate, npm install, build
composer demo             # wipe the database and load the invented demo couple (ana@example.com / password)
composer test             # Pest (SQLite in memory)
./vendor/bin/pint         # code style, must be clean before committing
npm run build             # production assets (npm run dev while working on CSS/JS)
python scripts/extract-strings.py --missing   # strings missing from lang/pt.json
```

## Rules of the house

- **Money is integer cents** everywhere (`amount_cents`, negative = expense). Never use floats. Parse human input with `App\Support\Money::parse`, show it with `Money::format` or `<x-money>`.
- **One installation = one household.** There is no tenant column and no public sign-up after the first account. Do not add multi-tenancy.
- **Transfers** (category kind `transfer`) never count as spending or income. Anything that sums money must go through `App\Services\Reports`, which already handles this.
- **Owner semantics:** `owner_id = null` means *shared*, not "unknown". `paid_by_id` only means something on shared expenses and feeds Settle up.
- **Dates:** query ranges with `Transaction::betweenDates()` (SQLite stores `Y-m-d 00:00:00`; plain BETWEEN loses the last day).
- **Translations:** English strings are the keys. Every `__('...')` needs an entry in `lang/pt.json`. `TranslationsTest` fails if one is missing.
- **Logic lives in `app/Services`**, controllers stay thin. Add a test next to any behaviour you add: pure logic in `tests/Unit`, flows in `tests/Feature`.
- **Never commit real bank data.** Fixtures and the demo seeder use invented people and amounts only.

## Layout

| Path | What |
| --- | --- |
| `app/Support/Money.php`, `DateParser.php` | parsing and formatting of messy bank values |
| `app/Services/CsvImporter.php` | delimiter/encoding detection, column guessing, row parsing |
| `app/Services/ImportService.php` | duplicate detection (stable row hash), rule suggestions |
| `app/Services/Reports.php` | everything on the dashboard |
| `app/Services/SettleUp.php` | who owes whom, whole-cent splitting |
| `app/Services/RecurringService.php` | catch-up generation of due recurring items |
| `docs/` | architecture, import guide, deployment, case study |
