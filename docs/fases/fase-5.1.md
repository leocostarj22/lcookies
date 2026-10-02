# Fase 5.1 — Extras antes da 1.0

**Data de conclusão:** 2026-10-02 · **Versão:** 0.6.0

## O que foi feito
- **Content-Security-Policy** (plugin *Sistema - Cabeçalhos HTTP*):
  - **modo nonce**, com ou sem `'strict-dynamic'`: os scripts que o LCookies ativa depois do consentimento são recriados com o nonce do script inicial (`LCookies._nonce`);
  - **modo hashes**: o `plg_system_lcookies` acrescenta o `'sha256-…'` do seu script inicial a `script-src` (ou `default-src`), exceto quando a política tem `'unsafe-inline'`;
  - **pré-visualização das opções**: recebe o nonce da página do backend, porque o `srcdoc` herda a política dessa página.
- **Consentimento partilhado entre subdomínios** (`cookie_domain`):
  - **ao guardar**: o valor é normalizado (`Helper\CookieDomain::filter()`) e validado (`Rule\CookiedomainRule`: tem de ser um nome de domínio a que o site pertence);
  - **no contrato**: o domínio só é usado quando o host do pedido lhe pertence. A chave da cache do contrato passa a incluir o host;
  - **no browser**: o JS apaga a cópia do cookie que existia só para o host antes de gravar a partilhada; se o browser recusar o domínio, grava para o host atual e avisa na consola;
  - **painel**: alerta quando o domínio não corresponde ao site.
- **Histórico das versões da política** no painel: escolhas, visitantes, primeira e última escolha e percentagens de "aceitar todos" e "rejeitar todos", por versão. Junta-se o botão **Nova versão da política** (`dashboard.newPolicy`, com confirmação), que sobe a versão e limpa a cache das opções.
- **Testes:**
  - `e2e_admin.py` com 130 verificações: validação do domínio, alerta, histórico e nova versão;
  - `e2e_front.mjs` com 116: CSP com nonce e com hashes, pré-visualização com CSP, subdomínios reais com `--host-resolver-rules` do Chromium.
- **Fixture** (`tests/fixture.php`): comandos novos `enable` e `livesite`.

## Decisões tomadas (e porquê)
- **Nonce em vez de `'unsafe-inline'`.** O LCookies nunca pede para relaxar a política: herda o nonce do Joomla. No modo hashes não há alternativa ao hash do script inicial, porque o core calcula os hashes antes de o plugin inserir o script.
- **Políticas com `'unsafe-inline'` não são tocadas.** Um hash a mais desligaria o `'unsafe-inline'` para todos os outros scripts inline da página.
- **O domínio é verificado em três sítios** (ao guardar, em cada pedido e no browser). Um cookie recusado não dá erro visível nenhum, e um banner que volta sempre é o pior resultado possível para o visitante e para a prova de consentimento.
- **O histórico vem dos registos de consentimento, sem tabela nova.** Cada registo já guarda a versão da política. Fica limitado ao período de retenção, como está escrito no painel.

## Ficheiros/estrutura principais
```
src/plg_system_lcookies/src/Extension/Lcookies.php     allowInlineScript(), chave da cache com o host
src/plg_system_lcookies/media/js/lcookies{,-head}.js   _nonce, writeCookie() com recurso ao host
src/com_lcookies/admin/src/Helper/CookieDomain.php, src/Rule/CookiedomainRule.php
src/com_lcookies/admin/src/Controller/{Dashboard,Preview}Controller.php, Model/DashboardModel.php
src/com_lcookies/admin/layouts/preview/page.php, media/js/preview.js
```

## Problemas encontrados e soluções
- **`$live_site` dos sites de teste** (necessário com `php -S`): redireciona em ciclo os pedidos de outros hosts. O teste dos subdomínios deixa-o vazio durante a secção (`fixture.php livesite`) e repõe-no no fim.
- **Reutilização de variáveis no `e2e_admin.py`:** um bloco novo deixava a variável `html` com outra página, e o teste da pré-visualização falhava. Cada secção volta a carregar a página de que precisa.

## Pendente / transita para a fase seguinte
- Fase 6 — Qualidade:
  - Docker J5/J6;
  - PHPStan e PHP-CS-Fixer;
  - testes de instalação, atualização e desinstalação no CI;
  - auditoria de acessibilidade;
  - revisão de traduções.

## Como testar
1. `python3 build/build.py` → `dist/pkg_lcookies-0.6.0.zip`; atualizar a partir da 0.5.0.
2. **CSP:** ativar *Sistema - Cabeçalhos HTTP* com CSP efetiva e nonce. O banner, os serviços aceites e a pré-visualização continuam a funcionar.
3. **Domínio partilhado:** *Opções → Geral → Domínio do cookie de consentimento*. Um domínio de outro site é recusado ao guardar.
4. **Painel:** secção *Versões da política* e botão *Nova versão da política*.

| Ambiente | Atualização 0.5.0 → 0.6.0 | e2e_admin | e2e_api | e2e_front | e2e_lang |
|---|---|---|---|---|---|
| Joomla 6.0.0 + MariaDB 11.4 | ✅ | 130/130 | 42/42 | 116/116 | 12/12 (pt-PT) |
| Joomla 6.0.0 + PostgreSQL 16 | ✅ | 130/130 | 42/42 | 116/116 | 12/12 (en-GB) |
| Joomla 5.4.9 + MariaDB 11.4 | ✅ | 130/130 | 42/42 | 116/116 | 12/12 (en-GB) |
| Joomla 5.2.6 + MariaDB 11.4 | ✅ | 130/130 | 42/42 | 116/116 | 12/12 (en-GB) |
