# Ligações a bancos (opcional)

Por omissão o Tostão funciona só com ficheiros CSV. Se quiseres, podes ligá-lo ao teu banco para ir buscar os movimentos novos sozinho. É **leitura apenas**: a aplicação nunca vê a tua palavra-passe do banco e não consegue mexer em dinheiro.

> **Estado:** o fluxo completo está testado com um banco de demonstração e com respostas simuladas da Enable Banking. **Ainda não foi testado contra o serviço real**, porque isso exige as credenciais de cada pessoa. Se algo falhar na primeira ligação, a mensagem de erro aparece em *Ligações a bancos*; abre uma issue com ela (sem dados pessoais).

## Como funciona

Em Portugal e na UE, os bancos são obrigados (PSD2) a dar acesso aos teus movimentos a quem tu autorizares. Uma aplicação pessoal não tem licença para isso, por isso passa-se por um **fornecedor** (agregador) que já a tem. O Tostão fala com o fornecedor; o fornecedor fala com o banco.

```
Tostão ──► fornecedor (Enable Banking) ──► Millennium bcp, Crédito Agrícola, ...
```

1. Escolhes o banco. A lista vem do fornecedor, não está escrita no código.
2. És enviado para o site ou a app do banco e aprovas o acesso com o teu método habitual.
3. Voltas ao Tostão, dizes em que conta do Tostão fica cada conta do banco e a partir de que data importar.
4. Todos os dias às 07:00 (se tiveres o agendador do Laravel) ou quando carregas em *Sincronizar agora*, os movimentos novos entram.

Os movimentos passam pelo mesmo caminho da importação de CSV: as **regras** aplicam categoria e pessoa, e a proteção contra duplicados usa a referência única que o banco dá a cada movimento. Sincronizar duas vezes nunca duplica. Só entram movimentos já confirmados pelo banco (os pendentes ficam de fora até serem lançados).

O consentimento dura no máximo 180 dias. Alguns dias antes, a página mostra um aviso; *Renovar consentimento* repete a autorização e mantém as ligações às tuas contas.

## Configurar a Enable Banking

A Enable Banking tem um modo para uso pessoal e não comercial em que ligas as tuas próprias contas à tua aplicação, sem contrato.

1. Cria conta em [enablebanking.com](https://enablebanking.com) e abre o painel de controlo (*Control Panel*).
2. Regista uma aplicação em **produção**. Escolhe gerar a chave privada no browser: descarrega um ficheiro `.pem` e fica com o **Application ID**.
3. Em *redirect URLs* da aplicação, acrescenta o endereço de retorno do Tostão: `https://o-teu-endereco/banks/callback` (em casa, por exemplo `http://localhost:8000/banks/callback`). Tem de ser exatamente o mesmo que o Tostão usa; consulta o painel para ver o que aceita para endereços locais.
4. Liga as tuas contas à aplicação (modo restrito). Só vais conseguir ler as contas que ligares aqui.
5. Guarda o `.pem` **fora do repositório** (o `.gitignore` ignora `*.pem`, mas não o guardes na pasta do projeto por precaução) e põe no `.env`:

```dotenv
ENABLE_BANKING_APP_ID=o-teu-application-id
ENABLE_BANKING_KEY_PATH=C:\Users\tu\segredos\enablebanking.pem
TOSTAO_BANK_COUNTRY=PT
# Só se o endereço de retorno não for o APP_URL:
# TOSTAO_BANK_REDIRECT_URL=https://o-teu-endereco/banks/callback
```

Em alojamento onde é mais fácil usar variáveis de ambiente do que ficheiros, podes pôr o conteúdo da chave em `ENABLE_BANKING_PRIVATE_KEY` (com `\n` no lugar das quebras de linha).

Depois abre *Mais → Ligações a bancos*. Se a página disser "Ainda não está configurado", falta o Application ID ou a chave.

Que bancos aparecem, e se o Crédito Agrícola ou a Revolut estão lá, é a Enable Banking que decide: vê a lista na própria página do Tostão depois de configurar.

## Experimentar sem credenciais

```dotenv
TOSTAO_BANK_DEMO=true
```

Aparece um "Demo Bank" com duas contas e movimentos inventados, para veres o fluxo todo (ligar, escolher contas, sincronizar, renovar).

## Acrescentar outro fornecedor ou outro banco

**Outro banco** do mesmo fornecedor não exige código: aparece na lista assim que o fornecedor o suportar.

**Outro fornecedor** (outro agregador, ou a API de um banco) são duas coisas:

1. Uma classe que implementa `App\Banking\BankProvider`: lista de bancos, início e fim do consentimento, e movimentos de uma conta desde uma data. Ver `app/Banking/Providers/EnableBankingProvider.php` como exemplo e `DemoProvider.php` como o mínimo.
2. Uma linha em `config/tostao.php`, em `banks.providers`, com a classe e as credenciais lidas do `.env`.

O resto (páginas, sincronização, duplicados, regras, renovação) funciona sem alterações. Se tiveres mais de um fornecedor configurado, a página deixa escolher.

## Segurança

- O Tostão guarda só o identificador da sessão (cifrado na base de dados com a `APP_KEY`), nunca credenciais do banco.
- A chave privada da Enable Banking serve para assinar pedidos curtos (uma hora). Quem a tiver acede às contas que ligaste: trata-a como uma palavra-passe.
- Remover uma ligação apaga a sessão do Tostão, mas os movimentos já importados ficam. Para revogar também no banco, usa a área de consentimentos do banco.
