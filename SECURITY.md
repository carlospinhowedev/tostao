# Segurança

O Tostão trata dados financeiros pessoais, por isso levamos a sério os relatos de vulnerabilidades.

**Não abras um issue público** para um problema de segurança. Usa o separador *Security → Report a vulnerability* do repositório no GitHub (relato privado). Responde-se assim que possível.

Inclui os passos para reproduzir e a versão. Não incluas dados bancários reais.

## Boas práticas para quem aloja

- Usa HTTPS.
- Mantém `APP_DEBUG=false` e `APP_ENV=production` fora do teu computador.
- Faz cópias de segurança da base de dados e guarda-as com cuidado.
- Mantém PHP e as dependências atualizadas (`composer update`, `npm audit`).
