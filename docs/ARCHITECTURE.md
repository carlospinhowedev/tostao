# Arquitetura

Tostão é uma aplicação Laravel clássica (MVC, Blade) com a lógica de negócio em classes de serviço pequenas e testáveis. Não há API, não há SPA: servidor a renderizar HTML, Alpine.js para o pouco comportamento no browser e Chart.js para dois gráficos.

## Decisões e porquê

### Dinheiro em cêntimos inteiros
Todas as colunas de montante são `bigint` em cêntimos (`amount_cents`, negativo = despesa). Somar números decimais em vírgula flutuante dá erros de cêntimo que se acumulam e deixam de bater com o banco. `App\Support\Money` converte o que as pessoas escrevem e os bancos exportam; a divisão de uma despesa por duas pessoas distribui os cêntimos que sobram, para a soma das partes ser sempre igual ao total.

### Uma instalação, uma casa
Não há tabela de "casas" nem coluna de inquilino em cada tabela. Os membros são simplesmente utilizadores. Isto tira uma classe inteira de problemas (isolamento entre contas, convites, planos) e deixa o código pequeno. O preço é aceite de propósito: se quiseres duas casas, instalas duas vezes. O registo só está aberto até existir o primeiro utilizador; os restantes são adicionados por quem já está dentro.

### "Comum" é `owner_id = null`
Um movimento pertence a um membro ou à casa. `null` significa **comum**, não "desconhecido". Para despesas comuns pagas do bolso de uma pessoa existe `paid_by_id`, que alimenta o acerto de contas. Despesas pagas pela conta conjunta não geram dívida.

### Transferências não são despesa
Passar 900 € da conta pessoal para a conjunta não é gastar dinheiro. As categorias têm um tipo (`expense`, `income`, `transfer`) e tudo o que soma dinheiro passa por `Reports`, que ignora as transferências. Sem isto, qualquer painel de um casal com contas separadas mente.

### Importação sem estado e sem ligação ao banco
O ficheiro é guardado num identificador aleatório (UUID) e, em cada passo, relido e interpretado de novo com as definições do formulário. Não há tabela de "importações" nem sessão a gerir: o ficheiro é a única fonte de verdade até o utilizador confirmar. As linhas ganham uma assinatura `sha256(conta | data | montante | descrição | n.º de ocorrência)` guardada numa coluna única, e é a base de dados que garante que importar duas vezes é seguro, mesmo que a validação da interface seja contornada.

### Agregação em PHP, não em SQL
`Reports` carrega os movimentos do período e agrega em coleções. Para o volume de uma casa (milhares de linhas por ano) é instantâneo, e o resultado é igual em SQLite, MySQL e PostgreSQL, sem funções de data específicas de cada motor. Se um dia for lento, é um sítio só para otimizar.

### Recorrentes sem cron obrigatório
Quem aloja em casa raramente configura um agendador. Por isso o painel gera os recorrentes vencidos sempre que alguém o abre, e o comando `tostao:recurring` (agendado diariamente) faz o mesmo para quem tiver cron. Cada item avança a sua própria `next_date`, por isso apanhar três meses em atraso cria exatamente três movimentos, uma vez. O dia do mês fica guardado (`day_of_month`): um item do dia 31 cai a 28 em fevereiro e volta a 31 em março, em vez de ficar preso ao 28.

### Bancos atrás de uma interface
A ligação aos bancos (opcional) não fala com nenhum banco em concreto. `App\Banking\BankProvider` descreve o que é preciso (lista de bancos, consentimento, movimentos) e `BankRegistry` descobre os fornecedores em `config/tostao.php`. A lista de bancos é sempre devolvida pelo fornecedor, nunca escrita no código. Os movimentos entram pelo `ImportService`, o mesmo caminho do CSV, por isso regras e duplicados não têm uma segunda implementação. Ver [BANKS.md](BANKS.md).

### Interface sem dependências externas em runtime
Tipo de letra (Inter) e bibliotecas vão compiladas no próprio build. Nenhum pedido a CDNs ou serviços de terceiros: coerente com uma aplicação cuja promessa é privacidade. As cores são variáveis CSS, o modo escuro está num só sítio (`resources/css/app.css`).

## Modelo de dados

```
users ─────────┬──< accounts          (owner_id nulo = conta conjunta)
               ├──< transactions      (owner_id nulo = comum; paid_by_id)
               ├──< recurring_transactions
               ├──< rules             (set_owner distingue "comum" de "não alterar")
               └──< settlements       (from_user_id, to_user_id)

accounts ──< transactions >── categories (kind: expense | income | transfer; monthly_budget_cents)
```

`transactions.import_hash` é único: é a rede de segurança contra duplicados.

## Estrutura do código

```
app/
  Models/                  Eloquent, sem lógica pesada
  Http/Controllers/        finos: validam, chamam um serviço, devolvem uma vista
  Rules/Amount.php         regra de validação para "12,50"
  Support/
    Money.php              parse e formatação de montantes
    DateParser.php         datas por formato, com deteção
  Services/
    CsvImporter.php        separador, codificação, colunas, linhas
    ImportService.php      assinatura, duplicados, sugestões das regras
    RuleEngine.php         palavras-chave → categoria e pessoa
    Reports.php            tudo o que o painel mostra
    SettleUp.php           quem deve a quem
    RecurringService.php   geração dos movimentos vencidos
    DefaultData.php        categorias e conta de arranque
resources/views/           Blade; componentes: x-money, x-field, x-owner-options
tests/Unit                 lógica pura (valores, datas, CSV, divisão)
tests/Feature              fluxos completos (importação, painel, acertos, gestão)
```

## Testes

Pest, base de dados SQLite em memória. A pirâmide é deliberada: a lógica difícil (valores, CSV, divisão de cêntimos) está em testes unitários rápidos, e os fluxos importantes (importar e reimportar, painel, acerto de contas, recorrentes em atraso) em testes de funcionalidade. O ficheiro `tests/Fixtures/extrato-tabulado.tsv` reproduz a forma de um extrato real de um banco português (tabuladores, `-5,50 €`, milhares com ponto, linhas em branco) com dados inventados.

Os testes já apanharam um erro verdadeiro durante o desenvolvimento: o último dia de cada mês ficava fora dos filtros em SQLite, porque o Eloquent guarda datas como `2026-11-30 00:00:00` e um `BETWEEN '…-01' AND '…-30'` exclui essa linha. A correção é `Transaction::betweenDates()`, que compara só a parte da data.

## Segurança

- Palavras-passe com hash (bcrypt), proteção CSRF e limitação de tentativas de início de sessão (Laravel Breeze).
- Ficheiros importados: extensão e tamanho validados, guardados fora de `public`, identificados por UUID, apagados ao confirmar ou ao fim de um dia.
- Pesquisa com `LIKE` escapa `%` e `_` para ser literal.
- Sem registo público depois do primeiro utilizador.
- Remover um membro com movimentos ou contas é bloqueado (também a partir do perfil): apagá-lo transformaria silenciosamente os seus movimentos em "comuns". O último membro também não se pode apagar, para o registo não reabrir sobre dados existentes.
- Os identificadores de pessoa, categoria e conta recebidos de formulários são sempre validados.

Para reportar um problema de segurança, ver [SECURITY.md](../SECURITY.md).
