# Instalar e manter

O Tostão foi pensado para correr no teu computador ou num servidor pequeno. Escolhe o que te for mais fácil.

## No teu computador

### Com Laravel Herd (Windows, macOS)

```sh
git clone https://github.com/<utilizador>/tostao.git
cd tostao
composer setup
herd link tostao        # http://tostao.test
```

### Só com PHP

```sh
composer setup
php artisan serve       # http://localhost:8000
```

## Base de dados

### SQLite (por defeito)

Um ficheiro, `database/database.sqlite`. Não precisas de instalar nada. Para fazer uma **cópia de segurança**, copia esse ficheiro (com a aplicação parada, ou depois de `sqlite3 database/database.sqlite ".backup copia.sqlite"`).

### MySQL (por exemplo com DBngin)

1. Cria uma base de dados vazia (`tostao`) no DBngin ou no teu MySQL.
2. No `.env`:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=tostao
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. `php artisan migrate`

A suite de testes foi corrida também contra MySQL 9. PostgreSQL deve funcionar (não há SQL específico de um motor) mas não foi testado.

## Num servidor

Requisitos: PHP 8.4+ com `pdo_sqlite` (ou `pdo_mysql`), `intl`, `mbstring`, `fileinfo`, Composer, Node (apenas para o build).

```sh
git clone … && cd tostao
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Aponta o servidor web (Nginx, Apache, Caddy) para a pasta `public/` e garante que o utilizador do servidor consegue escrever em `storage/` e `bootstrap/cache/`.

No `.env` de produção:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://contas.exemplo.pt
```

**Usa HTTPS.** São dados financeiros: não os deixes passar em texto simples. Um proxy como Caddy trata do certificado sozinho.

### Agendador (opcional)

O Tostão cria os movimentos recorrentes quando alguém abre o painel, por isso não precisas de agendador. Se quiseres que sejam criados todas as manhãs e que os ficheiros de importação abandonados sejam limpos, adiciona ao cron:

```cron
* * * * * cd /caminho/para/tostao && php artisan schedule:run >> /dev/null 2>&1
```

## Atualizar

```sh
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
```

## Perguntas frequentes

**Esqueci-me da palavra-passe.** A recuperação por email só funciona se configurares o correio no `.env` (`MAIL_MAILER`, `MAIL_HOST`, …). Por defeito os emails vão para `storage/logs/laravel.log`. Sem correio, usa `php artisan tinker` e `User::where('email', '…')->first()->update(['password' => 'nova-palavra-passe'])` (o hash é aplicado automaticamente).

**Posso ter só uma pessoa?** Sim. O acerto de contas só aparece com dois ou mais membros.

**Como mudo a moeda?** `TOSTAO_CURRENCY=USD` no `.env`. Os montantes são sempre guardados em cêntimos da moeda escolhida; não há conversão.

**Como acrescento outro idioma?** Copia `lang/pt.json` para `lang/<código>.json`, traduz os valores e acrescenta o código em `config/tostao.php`.
