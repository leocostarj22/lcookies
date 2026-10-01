# Fase 2 — Frontend MVP

**Data de conclusão:** 2026-10-01 · **Versão:** 0.2.0

## O que foi feito
- **Contrato JSON**
  - `Administrator\Contract\ContractBuilder` gera o contrato a partir das tabelas e das opções do componente.
  - O formato está descrito em `docs/contract.schema.json` (`schema: 1`).
  - É publicado com `addScriptOptions('lcookies', …)`.
- **`plg_system_lcookies`** (SubscriberInterface, DI, `DatabaseAwareTrait`):
  - `onBeforeCompileHead`:
    - carrega o idioma do site;
    - lê o contrato e o código dos serviços, a partir da cache;
    - publica o contrato e os assets (preset WAM `plg_system_lcookies.lcookies`) e as variáveis CSS das opções;
    - expira no servidor os cookies das categorias recusadas.
  - `onAfterRender` (prioridade mínima, para apanhar o que outros plugins injetam):
    - bloqueia `<script>`/`<iframe>` com `Html\Blocker`;
    - insere o script de arranque no topo do `<head>`, o código dos serviços e a marcação do banner a seguir a `<body>` (fica primeiro na ordem de tabulação).
- **Layouts JLayout** (`layouts/lcookies/`):
  - `banner`: barra em cima ou em baixo, caixa à esquerda ou à direita, modal;
  - `preferences`: `<dialog>` com interruptores por categoria e tabela de cookies por serviço;
  - `floating`: botão flutuante para reabrir as preferências;
  - `placeholder`: aviso no lugar dos iframes bloqueados.
- **`lcookies-head.js`** (inline, antes de qualquer outro script):
  - lê o consentimento guardado;
  - define o *default* do Consent Mode v2 e faz já o *update* se houver consentimento;
  - protege `document.createElement` (scripts e iframes criados por outros scripts) e usa um `MutationObserver` como rede de segurança;
  - define `window.LCookies.hasConsent()`/`getConsent()`.
- **`lcookies.js`** (ES module):
  - interface do banner e das preferências;
  - grava o cookie `lcookies_consent` = `{id, v, cats, ts}`;
  - desbloqueia por ordem: scripts clássicos externos em sequência, módulos, `<template>` com o código dos serviços, iframes;
  - placeholders, também para iframes dinâmicos;
  - limpeza de cookies e de `localStorage`/`sessionStorage` das categorias recusadas;
  - recarrega a página se for retirada uma categoria cujo código já correu;
  - GPC; Consent Mode *update* e evento `lcookies_consent_update` no `dataLayer`;
  - API: `open()`, `acceptAll()`, `rejectAll()`, `save(cats)`, `allow(cat)`;
  - eventos `lcookies:ready` e `lcookies:change`;
  - qualquer `[data-lcookies-open]` ou link `#lcookies-settings` abre as preferências.
- **CSS** com custom properties `--lcookies-*`:
  - temas claro, escuro e automático (`prefers-color-scheme`);
  - adaptado a ecrãs pequenos, `prefers-reduced-motion` e `forced-colors`.
- **Idioma do site** `com_lcookies.ini` (en-GB e pt-PT): constantes dos dados por omissão e textos da interface `COM_LCOOKIES_UI_*`.
- **Pacote**:
  - o script ativa os plugins na primeira instalação; as atualizações respeitam a escolha do administrador;
  - o build minifica os assets com esbuild (`.min.js`/`.min.css`; no modo debug o Joomla usa as versões normais).
- **Teste no browser** `tests/e2e_front.mjs` (54 verificações, Playwright + axe-core), com dados de teste de `tests/fixture.php`.

## Decisões tomadas (e porquê)
- **O contrato vive no componente**, não no plugin. A API (Fase 4) e a pré-visualização (Fase 5) vão precisar dele. Os textos da interface ficam por isso no idioma do site do componente (`COM_LCOOKIES_UI_*`), e o plugin só tem os `.ini` do nome e da descrição.
- **O bloqueio é feito sempre no servidor e o desbloqueio no cliente.** A saída HTML é igual para todos os visitantes, por isso é compatível com cache de página e CDN. O consentimento nunca altera o HTML gerado.
- **Scripts ficam com `type="text/plain"` mantendo o `src`.** O browser não descarrega nem executa scripts de tipo desconhecido. Ao desbloquear, o JS cria um elemento novo, porque mudar o `type` de um elemento existente não o executa.
- **O código dos serviços (`head_code`/`body_code`) vai dentro de `<template>`**, que é inerte, em vez de reescrever cada tag. Funciona para qualquer HTML colado pelo administrador. O de categorias obrigatórias é inserido diretamente.
- **O script do head é inline e corre antes de tudo.** O Consent Mode exige o *default* antes de qualquer tag Google, e a proteção do `createElement` tem de existir antes de outros scripts correrem. Leva o `nonce` CSP do Joomla quando o plugin HTTP Headers o define.
- **Proteção do `createElement`:** um `MutationObserver` sozinho não impede scripts dinâmicos, porque o pedido começa quando o elemento é inserido. A técnica é a do yett/Cookiebot.
- **Categorias opcionais sem serviços publicados não aparecem.** Não há nada a consentir, e evita toggles vazios.
- **Categorias e serviços despublicados ficam fora do contrato e do bloqueio.** Despublicar é desligar, não bloquear para sempre.
- **GPC:** com `navigator.globalPrivacyControl`, as categorias com `gpcOptOut` (alias `marketing` ou tipos `ad_*`) ficam recusadas e o interruptor desativado com aviso. Não se usa o cabeçalho `Sec-GPC` no servidor, para não quebrar a cache.
- **Banner modal:** o Escape não o fecha, porque é preciso escolher; "Rejeitar" está sempre visível com o mesmo destaque que "Aceitar". Se o browser o fechar à força, o botão flutuante continua disponível.
- **Joomla 5.2:** o `CMSPlugin` exige o dispatcher no construtor até ao 5.2. No 5.3+ passá-lo é deprecated. O `services/provider.php` escolhe pela versão (`JVERSION`) e mantém-se o mínimo 5.2.0.
- **Cache do contrato:** controlador `output`, grupo `com_lcookies` (os modelos do admin já limpam este grupo ao gravar). A chave inclui o idioma, o URL base e as opções.
- **`endpoint: null`** até à Fase 3; o JS já envia `{consent, action, url}` quando houver URL.

## Ficheiros/estrutura principais
```
src/com_lcookies/admin/src/Contract/ContractBuilder.php   contrato (docs/contract.schema.json)
src/com_lcookies/admin/src/Helper/LcookiesHelper.php      + loadSiteLanguage()
src/com_lcookies/site/language/{en-GB,pt-PT}/com_lcookies.ini
src/plg_system_lcookies/
  lcookies.xml, services/provider.php, language/
  src/Extension/Lcookies.php     eventos, cache, head script, limpeza servidor, inserção no HTML
  src/Html/Blocker.php           bloqueio de <script>/<iframe> por padrões
  layouts/lcookies/{banner,preferences,floating,placeholder}.php
  media/joomla.asset.json, js/lcookies-head.js, js/lcookies.js, css/lcookies.css
src/script.php                   + ativação dos plugins na primeira instalação
package.json                     esbuild, playwright-core, axe-core (desenvolvimento)
tests/e2e_front.mjs, tests/fixture.php
```
Override dos layouts: `templates/<template>/html/layouts/lcookies/<layout>.php` (manter os atributos `data-lcookies-*`).

## Problemas encontrados e soluções
- **O `aspect-ratio` do placeholder cortava o texto em iframes pequenos.** O JS passa a definir também `min-height: auto`, para a caixa crescer.
- **O carregador de teste continha o próprio padrão no código inline e era bloqueado pelo servidor.** É o comportamento correto, porque os padrões aplicam-se também ao código inline. O fixture passou a construir o URL por concatenação para testar a proteção.
- **Restos do `e2e_admin.py`** (categoria `statistics` editada) alteravam o mapa GCM. O fixture repõe essa categoria.
- **Log de deprecações:** com o plugin ligado aparecem mais avisos, todos gerados dentro do core:
  - `Text::_()` chama `Factory::getLanguage()`;
  - o `PluginHelper` chama `setDispatcher()` em todos os plugins;
  - o `WebAssetItem` usa `HTMLHelper` (*mediapath*).

  Foi confirmado com um rastreio temporário no core. O código do LCookies não usa APIs deprecated.
- **Os processos em segundo plano do ambiente de teste param ao fim de algum tempo.** Basta reiniciá-los (MariaDB e `php -S`).

## Pendente / transita para a fase seguinte
- Fase 3: endpoint `consent.save` (o JS já está preparado) e registo da versão da política.
- O URL do servidor de atualizações continua pendente.
- Validar o pt-PT do frontend num site com o pacote de idioma pt-PT (os sites de teste só têm en-GB; os `.ini` foram verificados por parse e as chaves comparadas).

## Como testar
1. `npm install` (uma vez), depois `python3 build/build.py` → `dist/pkg_lcookies-0.2.0.zip`; instalar ou atualizar.
2. Abrir o site: aparece o banner. Configurar em *Componentes → LCookies → Opções* (aparência, textos, Consent Mode, bloqueio).
3. Num serviço, definir padrões de bloqueio (ex. `googletagmanager.com`) e/ou código no head/body.
4. Testes automáticos (só em sites de teste, alteram dados e opções):
   - `node tests/e2e_front.mjs http://127.0.0.1:8106 /caminho/do/joomla`
   - `python3 tests/e2e_admin.py http://127.0.0.1:8106 j6` (regressão do backend).

| Ambiente | Instalação/atualização | e2e_front | e2e_admin |
|---|---|---|---|
| Joomla 6.0.0 + MariaDB 11.4 | ✅ atualização 0.1.0 → 0.2.0, plugin ativado | 54/54 | 34/34 |
| Joomla 6.0.0 + PostgreSQL 16 | ✅ atualização | 54/54 | 34/34 |
| Joomla 5.4.9 + MariaDB 11.4 | ✅ atualização | 54/54 | 34/34 |
| Joomla 5.2.6 + MariaDB 11.4 | ✅ instalação nova (construtor antigo do plugin) | 54/54 | 34/34 |
