# Importar o extrato do banco

O Tostão lê o ficheiro CSV (ou texto separado por tabuladores) que o teu banco te deixa descarregar. Não precisa de ligação ao banco nem das tuas credenciais.

## Passo a passo

1. No homebanking, exporta os movimentos para **CSV** (ou "Excel/CSV"). Quase todos os bancos têm esta opção na página de movimentos.
2. No Tostão, abre **Importar**, escolhe a conta a que o extrato pertence e larga o ficheiro.
3. **Confirma como ler o ficheiro.** O Tostão adivinha separador, formato da data e colunas, e mostra já as linhas lidas. Se algo estiver errado, corrige no formulário e carrega em *Atualizar pré-visualização*.
4. **Escolhe as linhas.** As novas vêm marcadas, as já importadas vêm desmarcadas e esbatidas. Podes mudar a categoria e a pessoa de cada linha antes de guardar.
5. Carrega em *Importar linhas selecionadas*. O ficheiro é apagado do servidor.

Nada fica guardado até ao passo 5.

## O que é compreendido

| | Exemplos aceites |
| --- | --- |
| Separador | `;`  `,`  tabulador  `\|` (detetado) |
| Codificação | UTF-8, UTF-8 com BOM, Windows-1252 (acentos de ficheiros antigos) |
| Datas | `31/12/2026`, `31-12-2026`, `31.12.2026`, `2026-12-31`, `12/31/2026`, `31/12/26` |
| Montantes | `-5,50 €`, `1.234,56`, `1,234.56`, `1186.35`, `(12.30)`, `+40,00 €`, `5,50-` |
| Colunas | um montante com sinal, **ou** colunas de débito e de crédito |
| Linhas | linhas em branco e cabeçalhos extra são ignorados |

Se um montante tiver um único separador seguido de três dígitos (`1.234`), no modo *Automático* é lido como milhares (1234). Se o teu banco usa o ponto como decimal, escolhe **Ponto (12.50)** em *Separador decimal*.

## Duplicados

Cada linha tem uma assinatura calculada com a conta, a data, o montante e a descrição. Se importares o mesmo extrato (ou um extrato que se sobrepõe ao anterior), as linhas repetidas aparecem como **Já importado** e não são gravadas outra vez.

Três cafés de 1,40 € no mesmo dia são três movimentos diferentes. A assinatura inclui o número de ordem da ocorrência no ficheiro, por isso nenhum se perde e, ao reimportar, todos são reconhecidos.

Se já tiveres registado à mão um movimento com a mesma data e montante, a linha aparece como **Possível duplicado** (desmarcada). A decisão é tua.

## Regras

Em **Mais → Regras** defines palavras-chave que preenchem categoria e pessoa automaticamente.

| Palavra-chave | Categoria | Pessoa |
| --- | --- | --- |
| `PINGO DOCE` | Supermercado | |
| `GALP` | Combustível e transportes | |
| `NETFLIX` | Lazer | Rui |

A procura ignora maiúsculas e minúsculas e procura em qualquer parte da descrição. Quando várias regras servem, ganha a de **menor número de prioridade**, campo a campo: uma regra pode dar a categoria e outra a pessoa.

## Quando não funciona à primeira

| Sintoma | O que fazer |
| --- | --- |
| "N linhas não puderam ser lidas" | Quase sempre é o formato da data. Experimenta outro em *Formato da data*. A mensagem diz as linhas do ficheiro. |
| Tudo numa só coluna | O separador está errado. Escolhe outro em *Separador*. |
| Montantes todos positivos | O banco usa colunas de débito e crédito. Escolhe-as em vez de *Coluna do montante*. |
| Montantes 100 vezes maiores ou menores | Troca o *Separador decimal* para Vírgula ou Ponto. |
| Acentos estranhos (`Ã©`) | Não deve acontecer: Windows-1252 é convertido. Se acontecer, abre um issue com as primeiras linhas (sem dados reais). |
| A primeira linha é um movimento e não um cabeçalho | Desmarca *A primeira linha é um cabeçalho*. |

## Privacidade

O ficheiro é guardado em `storage/app/private/imports` apenas até confirmares. Ficheiros abandonados são apagados ao fim de um dia (`php artisan tostao:prune-imports`, corre sozinho com o agendador). Nunca é enviado a terceiros.

Se abrires um issue por causa de um banco que não funciona, **apaga os dados reais** e deixa só a estrutura (cabeçalho e formato das datas e dos montantes).

## Extratos com cabeçalho a meio e codificação UTF-16

Alguns bancos (o Millennium bcp, por exemplo) exportam o ficheiro em UTF-16 e põem algumas linhas com dados da conta (saldo, período) antes da tabela de movimentos. O Tostão deteta a codificação, procura a linha de cabeçalho (a primeira que tem uma coluna de data e uma de montante) e ignora o que vem antes, bem como linhas de rodapé de uma só coluna. Montantes como `-20.00` e `1113.32` são lidos com o ponto como separador decimal.

## Extratos em PDF (Millennium bcp)

O "Extrato combinado" mensal do Millennium bcp pode ser carregado diretamente, sem converter para CSV. Na página **Importar**, escolhe a conta e o PDF; o resto é igual aos CSV (revisão linha a linha, regras de categoria, duplicados assinalados).

O PDF não tem colunas separadas de débito e crédito, por isso o Tostão deduz o sinal de cada linha pelo saldo que vem a seguir: se o saldo desceu, saiu dinheiro; se subiu, entrou. Isso permite uma verificação que o CSV não dá: no fim, os movimentos têm de somar exatamente o **saldo final** impresso no extrato. Se não somarem, a página avisa que alguma linha foi mal lida, em vez de importar em silêncio.

- Só são lidos os movimentos da conta à ordem. Os pagamentos de prestações do empréstimo e as secções do cartão de crédito do mesmo PDF não são movimentos desta conta.
- Cada banco desenha o PDF à sua maneira: há um leitor por formato (`app/Statements`). Outro formato mostra "não é um extrato que o Tostão saiba ler" e deves usar o CSV. Para acrescentar um banco, implementa `StatementParser`.
- A extração do texto é feita em PHP (`smalot/pdfparser`), sem programas externos.

## Importar um período que se sobrepõe a um anterior

Podes importar um extrato a meio do mês e, mais tarde, o extrato completo desse mês. Cada linha é comparada com o que a conta já tem:

- **"Já importado"**: a mesma linha, com a mesma descrição, já entrou. Fica de fora.
- **"Possível duplicado"**: a conta já tem um movimento com a mesma data e montante que nenhuma linha deste ficheiro explica (escrito à mão, ou importado de outro ficheiro ou formato em que o banco escreveu a descrição de outra maneira, por exemplo com "CONTACTLESS" no fim). Fica **desmarcado** por omissão; se for mesmo um movimento novo, marca-o.
- **"Novo"**: não há nada igual. Fica marcado.

Dois cafés iguais no mesmo dia continuam a ser dois movimentos: cada linha só "gasta" um movimento existente.
