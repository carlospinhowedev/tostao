# Investimentos e poupança

O Tostão não é uma app de bolsa, mas uma casa também tem dinheiro em corretoras. Uma conta do tipo **Investimentos** regista duas coisas:

- **Quanto vale** (um valor por dia), que aparece na lista de contas do painel.
- **O dinheiro que lá pões e tiras** (depósitos e levantamentos), que ficam como **transferências**: pôr 500 € numa corretora nunca conta como despesa.

As compras e vendas de ações não são registadas: não interessam a um orçamento de casa e misturavam-se com as despesas.

## Trading 212 (por API)

O Trading 212 tem uma [API pública](https://docs.trading212.com/api) (em beta) para contas **Invest** e **Stocks ISA**. A ligação só lê: o Tostão pede o valor da conta e o histórico de depósitos e levantamentos.

1. Na app do Trading 212: **Definições → API (Beta) → Gerar chave**. Aceita o aviso de risco.
2. Dá à chave só as permissões que o Tostão usa: dados da conta e histórico de transações. **Não** dês permissão para ordens: a chave nunca precisa de comprar nem vender.
3. Se puderes, restringe a chave ao IP de onde o Tostão corre.
4. Guarda o **segredo** (só aparece uma vez) e põe no `.env`:

```dotenv
TRADING212_API_KEY=a-tua-chave
TRADING212_API_SECRET=o-teu-segredo
# TRADING212_ENV=demo   # só para testar com uma conta de simulação
```

5. Em **Mais → Contas**, cria uma conta do tipo *Investimentos* (por exemplo "Trading 212").
6. Em **Mais → Investimentos**, escolhe essa conta, a data a partir da qual queres os depósitos e carrega em *Ligar e sincronizar*.

A sincronização repete-se todos os dias às 07:30 (com o agendador do Laravel) ou quando carregas em *Sincronizar agora*. Cada movimento tem a referência do Trading 212, por isso sincronizar duas vezes nunca duplica.

**Limites e cuidados**
- Os limites do Trading 212 são apertados (por exemplo, 20 pedidos por minuto no histórico). O Tostão lê no máximo 15 páginas de 50 movimentos por sincronização; se tiveres muito histórico, a primeira sincronização pode precisar de ser repetida.
- Só lê **depósitos e levantamentos**. Comissões, juros e dividendos ficam de fora.
- A API não suporta contas SIPP nem contas em várias moedas. O valor vem na moeda principal da conta.
- O valor é a soma do dinheiro e dos investimentos que a API devolve. A documentação não explica o campo do total, por isso o Tostão soma as partes.
- **Isto foi escrito a partir da documentação pública e testado só com respostas simuladas.** Falta uma ligação real: se algum campo vier diferente, a mensagem de erro aparece em *Investimentos*.

## XTB (sem API)

A XTB **desativou a sua API a 14 de março de 2025** e não indica substituto, por isso não há ligação automática. Com a XTB:

- **Valor:** em **Mais → Investimentos**, escreve o valor da conta quando quiseres (ao fim do mês, por exemplo). Cada dia fica com um valor; escrever outro no mesmo dia substitui o anterior.
- **Depósitos e levantamentos:** podes registá-los à mão como transferências. A plataforma web da XTB gera um relatório de operações em numerário (separador *Histórico*), mas em Excel; se o guardares como CSV, a importação de ficheiros do Tostão consegue lê-lo (não testei o formato real).

## Outras corretoras e poupanças de banco

Qualquer conta de investimento aceita um valor escrito à mão. Para acrescentar uma corretora com API, implementa `App\Brokers\BrokerProvider` (valor da conta e movimentos de dinheiro) e regista a classe em `config/tostao.php`, em `brokers.providers`. Ver `app/Brokers/Trading212Provider.php` como exemplo.

## Transferências entre as tuas contas

O dinheiro que sai da conta de um e entra na conjunta aparece nos dois extratos. Em **Mais → Transferências entre contas** o Tostão propõe os pares (mesmo montante, contas diferentes, até 3 dias de diferença) e tu confirmas. Os casos ambíguos ficam de fora de propósito. Depois de marcados, deixam de contar como despesa e receita.
