# Segurança

## Reportar uma vulnerabilidade

Não abra uma *issue* pública. Use o reporte privado do GitHub: *Security → Report a vulnerability* em [github.com/leocostarj22/lcookies](https://github.com/leocostarj22/lcookies/security). Em alternativa, use o contacto em [www.leocostadeveloper.com](https://www.leocostadeveloper.com).

Indique:
- a versão do LCookies, do Joomla e do PHP;
- os passos para reproduzir;
- o impacto.

A resposta chega em poucos dias. A correção sai numa versão nova, distribuída pelo servidor de atualizações do Joomla.

Só a última versão recebe correções de segurança.

## O que o LCookies faz para se proteger

**Acesso e pedidos do backend**
- Todas as ações do backend verificam o token do formulário (CSRF) e as permissões do Joomla:
  - `core.manage`, `core.create`, `core.edit`, `core.delete` e `core.options`;
  - `lcookies.consents.view`, `lcookies.consents.export` e `lcookies.scan`.
- A API usa as mesmas regras.

**Código dos serviços**
- O código dos serviços (cabeçalho e fim da página) corre no site com os mesmos direitos que o backend.
- Por isso só pode ser alterado ou importado por Super Utilizadores e pelos grupos com *Sem filtragem* em *Configuração Global → Filtros de texto*, como o HTML sem filtro nos artigos.
- Para os outros grupos, os campos ficam só de leitura; na API e na importação, o código enviado é ignorado.

**Registo de consentimentos**
- Só aceita POST JSON da mesma origem (`Sec-Fetch-Site`), com tamanho máximo.
- A validação é estrita: só aceita a versão atual da política, as ações conhecidas e as categorias publicadas.
- Há um limite de 30 registos por minuto por rede.
- Não guarda o IP nem o browser em claro: guarda um hash HMAC-SHA256, com o `secret` do site, do IP truncado (/24 ou /48) e do user agent.
- O URL da página fica sem query string nem fragmento.

**Base de dados e saída HTML**
- Todas as queries usam parâmetros (`bind()`); a ordenação das listas vem de uma lista branca.
- Toda a saída é escapada nos layouts e no JavaScript.
- A exportação CSV protege contra fórmulas (CSV injection).

**Scanner**
- O modo de scan só se ativa com um token HMAC que vale uma hora; o token sai do endereço logo no arranque da página.
- Esse modo não grava nada e a página não vai para a cache.
- O servidor só pede páginas do próprio site e não segue redirecionamentos (SSRF).

**Pré-visualização das opções**
- Corre num iframe `sandbox` sem `allow-same-origin`, com uma origem opaca, separada da sessão do backend.

**Compatibilidade com Content-Security-Policy**
- Funciona com nonces, com `'strict-dynamic'` e com hashes.

**Ficheiros**
- Todos os ficheiros PHP recusam o acesso direto (`_JEXEC`).
- A importação JSON aceita só ficheiros enviados pelo formulário, com tamanho máximo, e valida o formato.

**Testes automáticos** (`.github/workflows/ci.yml`)
- PHPStan, padrão de código e testes end-to-end em Joomla 5.2, 5.4 e 6.0.
- Os testes verificam as permissões, os tokens e a validação do endpoint público.
