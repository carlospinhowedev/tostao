# Contribuir

Obrigado pelo interesse. O Tostão é pequeno de propósito, por isso as contribuições mais úteis são as que o mantêm assim.

## Antes de começar

```sh
composer setup
composer demo      # dados inventados para experimentar
composer test
```

Lê o [AGENTS.md](AGENTS.md): resume as regras do projeto (dinheiro em cêntimos, comum = `owner_id` nulo, transferências, traduções).

## Boas contribuições

- **Um banco que não importa.** Abre um issue com o cabeçalho e **três linhas inventadas** com o mesmo formato (datas, montantes, separador). Nunca coles dados reais.
- **Uma tradução** nova (`lang/<código>.json`).
- **Correções** com um teste que falhava antes.
- Melhorias de acessibilidade e de documentação.

## Fora do âmbito

Ligação direta aos bancos, multi-casa/multi-inquilino, contas de investimento, aplicação paga ou alojada. São decisões, não esquecimentos (ver [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)).

## Antes de abrir um pull request

1. `composer test` passa.
2. `./vendor/bin/pint` não muda nada.
3. Frases novas na interface têm entrada em `lang/pt.json` (o `TranslationsTest` avisa).
4. Descreve o *porquê* da alteração, não só o *quê*.

Mensagens de commit curtas, no imperativo, em português ou inglês.
