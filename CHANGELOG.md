# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-PT/1.1.0/).

## [Unreleased]

### Added
- Optional bank connections through open banking (`docs/BANKS.md`): a `BankProvider` interface, a registry driven by `config/tostao.php`, an Enable Banking provider, a demo provider, consent renewal that keeps the account mapping, and a `tostao:sync-banks` command (scheduled daily).
- Bank movements go through the same import path as CSV files, using the bank's own reference as the duplicate key.

- Rules can add a second condition: the amount is a whole multiple of a value (for example 5,00). Fuel-station charges of exactly 20,00 are rarely fuel.
- Rules can be applied to transactions that have no category yet (button on the Rules page).
- The transactions table edits category and person in place, saving each change at once, and a bar applies a category and/or person to every ticked row.

- Investment accounts (`docs/INVESTMENTS.md`): a value per day, deposits and withdrawals kept as transfers, a typed-in value for brokers with no API, and a Trading 212 provider (API key and secret in `.env`). XTB switched its API off in March 2025, so it is manual.
- Transfers between your own accounts: pairs of a withdrawal and a deposit of the same amount are proposed and confirmed, and then stop counting as spending or income.
- A "Rule" button on each transaction opens the rule form filled in with a suggested keyword, category and person, and a link shows how many transactions have no category.
- `tostao:backup` writes a consistent SQLite snapshot, keeps the newest ones, and runs every night.
- Rules can skip descriptions that contain a given word.

### Fixed
- CSV import now reads UTF-16 files (with or without a byte order mark) and statements that have account details above the header row, as exported by Millennium bcp. Previously these crashed the review page.

### Changed
- The translation test now also covers sentences that contain a full stop.

## [1.0.0] - 2026-11-17

Primeira versão pública.

### Adicionado
- Painel por pessoa, por categoria e por mês, com evolução de seis meses, orçamentos, contas e maiores despesas.
- Importação de CSV do banco: deteção de separador, codificação, formato de data e colunas; montantes em vários formatos; débito/crédito separados; pré-visualização; deteção de duplicados.
- Regras de categorização por palavra-chave.
- Orçamentos mensais por categoria.
- Acerto de contas entre membros, com divisão ao cêntimo e registo de pagamentos.
- Movimentos recorrentes, gerados quando vencem.
- Contas, categorias (despesa, receita, transferência) e membros.
- Exportação CSV com filtros.
- Interface em português e inglês, modo claro e escuro.
- Dados de demonstração inventados (`composer demo`).
