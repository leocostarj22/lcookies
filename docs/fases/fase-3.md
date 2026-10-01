# Fase 3 — Prova de consentimento

**Data de conclusão:** 2026-10-01 · **Versão:** 0.3.0

## O que foi feito
- **Tabela `#__lcookies_consents`**
  - Criada no install e em `sql/updates/{mysql,postgresql}/0.3.0.sql`; removida no uninstall.
  - Campos: `consent_uuid`, `action`, `categories` (JSON de aliases), `policy_version`, `user_id` (NULL para visitantes), `ip_hash`, `ua_hash`, `url`, `language`, `created` (UTC).
  - Índices: UUID, data, `(ip_hash, created)` (para o limite de pedidos) e utilizador.
- **`Administrator\Consent\`**, partilhado pelo site, pelo admin e pela futura API:
  - `Anonymizer`: IP truncado (IPv4 /24, IPv6 /48; IPv4 mapeado em IPv6 tratado como IPv4) e hash HMAC-SHA256 com o `secret` do site; hash do user agent.
  - `ConsentLog`:
    - validação estrita: UUID, versão da política igual à atual, ação conhecida, categorias publicadas sem repetições e com todas as obrigatórias;
    - URL do mesmo host, guardado sem query string nem fragmento;
    - limite de 30 registos por minuto por IP truncado;
    - gravação dos registos;
    - limpeza dos registos fora do período de conservação, automática em 1 de cada 100 gravações.
  - `ConsentException`: o código da exceção é o estado HTTP da resposta.
- **Endpoint `Site\Controller\ConsentController::save`**
  - URL: `index.php?option=com_lcookies&task=consent.save&format=json`.
  - Só aceita POST com `Content-Type: application/json`, recusa `Sec-Fetch-Site` diferente de `same-origin` e corpos acima de 4 KB.
  - Respostas: `JsonResponse` com os estados 200, 403, 404 (registo desligado), 405, 413, 415, 422 e 429.
  - O site não tem páginas: o `Site\Controller\DisplayController` responde 404.
- **Contrato**: o `endpoint` passa a ser `Uri::root(true) . '/index.php?option=com_lcookies&task=consent.save&format=json'` quando "Registar consentimentos" está ligado; caso contrário é `null`. O JS da Fase 2 não precisou de alterações.
- **Backend → Registos de consentimento** (`view=consents`, só leitura):
  - filtros: pesquisa (UUID com ou sem o prefixo `uuid:`, `id:`, URL), escolha, categoria aceite, versão da política, visitante/registado e intervalo de datas (no fuso do utilizador);
  - clicar no UUID mostra o histórico desse consentimento; `<details>` com os hashes e o URL completo;
  - **Exportar CSV**:
    - respeita os filtros ativos;
    - UTF-8 com BOM e datas UTC ISO 8601;
    - protegido contra fórmulas (CSV injection);
    - lido em blocos de 1000 registos (`setLimit`).
  - **Eliminar expirados** (`core.delete`).
  - Aviso quando o registo está desligado.
  - Novo item de submenu.
- **Permissões**: a lista exige `lcookies.consents.view`, a exportação exige `lcookies.consents.export` (o botão só aparece com essa permissão).
- **Idiomas**: as novas constantes existem em en-GB e pt-PT (admin e `.sys.ini`).
- **Testes**:
  - `e2e_front.mjs`: 64 verificações (+10) — envio ao endpoint, um registo por escolha com o mesmo UUID, categorias, versão, apenas hashes, URL sem query, registo desligado (contrato com `null`, nada enviado, resposta 404).
  - `e2e_admin.py`: 57 verificações (+23) — endpoint, lista, filtros, histórico por UUID, CSV (cabeçalho, BOM, filtros, fórmulas, UTC, token), limpeza, permissões com um utilizador Manager.

## Decisões tomadas (e porquê)
- **Sem token CSRF no endpoint.** O HTML tem de ser igual para todos, por causa da cache. Em vez do token:
  - um formulário de outro site não consegue enviar `application/json` sem *preflight* CORS, que o Joomla não autoriza;
  - os browsers indicam em `Sec-Fetch-Site` os pedidos de outro site, e esses são recusados;
  - os dados são validados com rigor e há limite de pedidos.
- **HMAC-SHA256 com o `secret` do site**, em vez de um SHA-256 simples com salt. Um /24 tem só 2²⁴ redes, e um hash sem chave secreta seria revertido por força bruta. Consequência: se o `secret` mudar, os hashes antigos deixam de corresponder aos novos. Não afeta a prova, porque o UUID continua a identificar o consentimento.
- **O limite de pedidos usa a própria tabela**, contando os registos recentes do mesmo hash de IP; não é preciso tabela nem cache novas. O limite é de 30 por minuto e não mais baixo, porque um /24 pode ser uma empresa inteira atrás do mesmo endereço.
- **A versão da política tem de ser a atual.** Um pedido com outra versão (página aberta antes da mudança) é recusado com 422, e o visitante volta a ver o banner na página seguinte.
- **O URL é guardado sem query string nem fragmento**, porque podem conter dados pessoais (e-mails, tokens). O idioma é o do pedido (o do site, pelo cookie de idioma em sites multilingues).
- **Endpoint não roteado** (sem SEF): o URL é igual em todas as páginas e o contrato continua cacheável. O plugin SEF só redireciona pedidos GET com `format=html`, por isso o POST JSON nunca é redirecionado.
- **Registos só de leitura**, sem editar nem apagar um a um, para não comprometer a prova. Só se eliminam os expirados. O apagamento a pedido do titular fica para o `plg_privacy` (Fase 6).
- **Exportação por link GET com token** (`checkToken('get')`) em vez de submeter o formulário da lista. Submeter o formulário deixava `task` preenchido e voltava a descarregar o ficheiro ao mudar um filtro (o core usa um segundo formulário para evitar isto). Antes de escrever, os buffers de saída são esvaziados: o ficheiro é a resposta inteira e não fica em memória.
- **`ConsentLog` vive no admin** (como o `ContractBuilder`), porque o site, o backend e a API (Fase 4) usam a mesma lógica.

## Ficheiros/estrutura principais
```
src/com_lcookies/admin/sql/updates/{mysql,postgresql}/0.3.0.sql   tabela de consentimentos
src/com_lcookies/admin/src/Consent/{Anonymizer,ConsentLog,ConsentException}.php
src/com_lcookies/site/src/Controller/{ConsentController,DisplayController}.php
src/com_lcookies/admin/src/Controller/ConsentsController.php   export (CSV), purge
src/com_lcookies/admin/src/Model/ConsentsModel.php             lista, filtros, getExportRows(), purge()
src/com_lcookies/admin/src/View/Consents/HtmlView.php, tmpl/consents/default.php, forms/filter_consents.xml
src/com_lcookies/admin/src/Field/LcookiescategoryField.php     + key="alias"
src/com_lcookies/admin/src/Contract/ContractBuilder.php        + ENDPOINT
```

## Problemas encontrados e soluções
- **`DatabaseDriver::setQuery($query, $offset, $limit)` está deprecated** (joomla/database 2.0). Foi detetado ao depurar a exportação e substituído por `$query->setLimit()`.
- **O CSV chegava vazio ao teste.** O código estava correto: o helper Python fechava a ligação antes de ler a resposta, e o PHP aborta a escrita quando o cliente desliga. Corrigido no teste.
- **O Playwright não consegue ler o corpo de pedidos `fetch` com `keepalive`** (`response.json()` fica pendurado). O teste verifica o estado HTTP e confirma os dados na base de dados.
- **Por omissão, o grupo Manager não tem `core.manage` no componente** (no root só o Administrator tem). O teste de permissões concede primeiro `core.manage` e depois `lcookies.consents.view`.
- **Log de deprecações (J6):** comparando a lista de serviços com a de registos, não há tipos novos. A diferença é só em `Factory::getLanguage()`, chamado pelo `Text::_()` do core (já visto na Fase 2).
- **CLI do Joomla 5.2.6 com `$live_site` definido** dá "Could not parse the requested URI". É um problema do core, também na Fase 2: limpar `$live_site` durante a instalação por CLI.

## Pendente / transita para a fase seguinte
- Fase 4: expor `consents` (GET) na API Web Services reutilizando `ConsentsModel`/`ConsentLog`.
- Fase 6 (`plg_privacy`): exportar/apagar os registos de um utilizador (`user_id`).
- Limpeza agendada com um plugin `task` do Joomla, opcional; já existem a limpeza automática (1 em cada 100 gravações) e o botão.
- O URL do servidor de atualizações continua pendente. A validação visual do pt-PT continua para a Fase 5.

## Como testar
1. `python3 build/build.py` → `dist/pkg_lcookies-0.3.0.zip`; instalar/atualizar (cria a tabela).
2. No site, escolher no banner → *Componentes → LCookies → Registos de consentimento* mostra o registo. O UUID também está no cookie `lcookies_consent`.
3. Testes automáticos (só em sites de teste):
   - `python3 tests/e2e_admin.py http://127.0.0.1:8106 j6`
   - `node tests/e2e_front.mjs http://127.0.0.1:8106 /caminho/do/joomla`
   - não correr os dois ao mesmo tempo no mesmo site.

| Ambiente | Instalação/atualização | e2e_admin | e2e_front |
|---|---|---|---|
| Joomla 6.0.0 + MariaDB 11.4 | ✅ atualização 0.2.0 → 0.3.0 | 57/57 | 64/64 |
| Joomla 6.0.0 + PostgreSQL 16 | ✅ atualização | 57/57 | 64/64 |
| Joomla 5.4.9 + MariaDB 11.4 | ✅ atualização | 57/57 | 64/64 |
| Joomla 5.2.6 + MariaDB 11.4 | ✅ atualização | 57/57 | 64/64 |
