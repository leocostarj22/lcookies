# LCookies — Plano do projeto

Sistema completo de gestão de consentimento de cookies para **Joomla 5.x e 6.x**.
Princípio central: **o backend é a única fonte de verdade** e gera um contrato JSON que o frontend consome.

## 1. Decisões tomadas

| Tema | Decisão |
|---|---|
| Joomla | **5.2+** e 6.x — um único código base (5.0/5.1 excluídos: bug do core na reordenação, ver `docs/fases/fase-1.md`) |
| PHP | Sintaxe compatível com 8.1; testado em 8.3+ (mínimo do J6) |
| Namespace | `Lcsilva\Component\Lcookies`, `Lcsilva\Plugin\<Grupo>\Lcookies`, `Lcsilva\Module\Lcookies` |
| APIs proibidas | Nada *deprecated*: sem `CMSObject`, `JFactory`, `JText`, aliases de classes legadas. Tem de funcionar com os plugins *Backward Compatibility* desativados |
| Plugins | `SubscriberInterface` + classes de evento concretas; DI via `services/provider.php` |
| Assets | `WebAssetManager` + `joomla.asset.json`; JS ES module vanilla (sem jQuery) |
| IAB TCF v2.2 | **Fora do âmbito** (sem redes de anúncios programáticos) |
| Registo de consentimento | IP truncado (IPv4 /24, IPv6 /48) + hash SHA-256 com salt do site |
| Geo-targeting | **Fora do âmbito** — banner mostrado a todos (ver justificação abaixo) |
| Idiomas | pt-PT e en-GB |
| Contrato | Gerado no componente (`ContractBuilder`), reutilizado pelo plugin, API e pré-visualização; textos da interface no idioma do site do componente |
| Textos dos dados | Títulos/descrições guardados como constantes de idioma (ex. `COM_LCOOKIES_CAT_STATISTICS`) e traduzidos na apresentação; o admin pode escrever texto simples. Alteráveis em *Substituições de idioma* (padrão do core, como nos workflows) |

**Geo-targeting — porque não:** o RGPD/ePrivacy aplica-se a qualquer visitante da UE; um site português tem essencialmente público da UE.
Geolocalizar exige base de dados IP (MaxMind, com licença), falha com VPN/proxies, e quebra a cache de página (a resposta variaria por país).
Mostrar o banner a todos é a opção mais segura e mais simples.

## 2. Extensões do pacote

```
pkg_lcookies
├── com_lcookies              Componente (admin + site + api)
├── plg_system_lcookies       Banner, bloqueio de scripts, Consent Mode v2, limpeza de cookies
├── plg_webservices_lcookies  Rotas da Web Services API
├── plg_task_lcookies         Scheduler: purga de logs, scan periódico
├── plg_privacy_lcookies      Integração com com_privacy (exportar/apagar consentimentos)
├── plg_content_lcookies      {lcookies-table} e {lcookies-settings} em artigos
└── mod_lcookies              Botão/link "Preferências de cookies"
```

## 3. Modelo de dados

| Tabela | Campos principais |
|---|---|
| `#__lcookies_categories` | `alias` (necessary, preferences, statistics, marketing, unclassified), título, descrição, `required`, `core` (necessary/unclassified: alias, estado e obrigatoriedade bloqueados), `gcm_types` (JSON), state, ordering, created/modified, checkout |
| `#__lcookies_services` | `category_id`, `alias` (único), título, fornecedor, URL política privacidade, `block_patterns` (um por linha; `/.../` = regex), `head_code`, `body_code`, state, ordering |
| `#__lcookies_cookies` | `service_id` (categoria herdada do serviço), nome, `match_type` (exact/prefix/regex), `type` (cookie/local/session/pixel), domínio, `duration_value` + `duration_unit` (session/minute/hour/day/month/year), descrição, `source` (manual/scanner), state, ordering |
| `#__lcookies_consents` | `consent_uuid`, `action` (`accept_all`/`reject_all`/`custom`/`allow`), `categories` (JSON de aliases), `policy_version`, `user_id` (nullable), `ip_hash`, `ua_hash` (HMAC-SHA256 com o `secret` do site), `url` (sem query string), `language`, `created` (UTC) |
| `#__lcookies_scans` | *(Fase 5)* URL, cookies encontrados (JSON), estado, datas |

Regras: só se elimina o que está no lixo; categorias com serviços não podem ser eliminadas; eliminar um serviço elimina os seus cookies.

Configuração global em `config.xml`: layout, cores, textos, versão da política, validade do consentimento, Consent Mode, retenção de logs, GPC, etc.

## 4. Backend

1. Dashboard — taxa de aceitação por categoria, consentimentos 30 dias, alertas (cookies não classificados).
2. CRUD Categorias / Serviços / Cookies (MVC Joomla, filtros, batch, drag & drop).
3. Biblioteca de presets (GA4, GTM, Meta Pixel, YouTube, Vimeo, Google Maps, Hotjar, Matomo, Clarity…) + import/export JSON.
4. Scanner — servidor (`Joomla\Http`, cabeçalhos `Set-Cookie`) e cliente (iframe mesma origem, `document.cookie`/storage).
5. Registo de consentimentos — filtros, exportação CSV, prova por UUID.
6. Aparência com pré-visualização em tempo real (mesmo JS do frontend).
7. Versão da política — incrementar força novo consentimento.
8. ACL (`access.xml`): `core.manage`, `lcookies.consents.view`, `lcookies.consents.export`, `lcookies.scan`.

## 5. Frontend

- **Layouts JLayout** substituíveis pelo template: barra (topo/fundo), caixa, modal.
- **Modal de preferências**: toggles por categoria (nunca pré-marcados) + tabela de cookies por serviço.
- **Reabrir preferências**: botão flutuante, `mod_lcookies`, `{lcookies-settings}`.
- **Acessibilidade WCAG 2.2**: focus trap, `aria-*`, teclado, "Rejeitar" e "Aceitar" com o mesmo destaque.
- **Bloqueio de scripts**: `onAfterRender` reescreve `<script>` → `type="text/plain" data-lcookies="<categoria>"`; `<iframe>` `src` → `data-src` + placeholder.
  Bloqueia **sempre** no servidor e desbloqueia no cliente → compatível com cache de página/CDN.
  `MutationObserver` como rede de segurança.
- **Google Consent Mode v2**: `consent default` (denied) inline no `<head>` antes de qualquer tag; `consent update` após escolha.
- **API JS**: `LCookies.hasConsent(cat)`, `getConsent()`, `open()`, `acceptAll()`, `rejectAll()`, `save(cats)`, `allow(cat)`; eventos `lcookies:ready` e `lcookies:change`; `[data-lcookies-open]` / `#lcookies-settings` abrem as preferências.
- **Armazenamento**: cookie `lcookies_consent` = `{id, v, cats, ts}`, validade por omissão 180 dias.
- **Retirada de consentimento**: JS apaga cookies/storage conhecidos; PHP expira-os no pedido seguinte.
- **GPC**: se `Sec-GPC: 1` / `navigator.globalPrivacyControl`, tratar marketing como recusado (configurável).

## 6. Contrato backend ↔ frontend

Gerado pelo `plg_system` via `$doc->addScriptOptions('lcookies', …)`, lido com `Joomla.getOptions('lcookies')`.
Schema versionado em `docs/contract.schema.json`. Layouts PHP, JS do site e pré-visualização do backend usam **apenas** este contrato.

```json
{
  "schema": 1,
  "policyVersion": 3,
  "expiryDays": 180,
  "cookie": { "name": "lcookies_consent", "domain": "" },
  "endpoint": "/index.php?option=com_lcookies&task=consent.save&format=json",
  "layout": "box-bottom-left",
  "theme": "auto",
  "floatingButton": "left",
  "privacyUrl": "/politica-de-privacidade",
  "respectGpc": true,
  "autoblock": true,
  "iframePlaceholder": true,
  "gcm": { "waitForUpdate": 500, "adsDataRedaction": true, "urlPassthrough": false },
  "categories": [
    { "alias": "statistics", "title": "...", "description": "...", "required": false,
      "gcm": ["analytics_storage"], "gpcOptOut": false,
      "services": [
        { "id": 4, "alias": "ga4", "title": "Google Analytics", "provider": "Google", "description": "...",
          "privacyUrl": "...", "patterns": ["googletagmanager.com/gtag"],
          "cookies": [{ "name": "_ga", "match": "prefix", "type": "cookie", "domain": "", "duration": "2 anos", "description": "..." }] }
      ]
    }
  ],
  "texts": { "title": "...", "message": "...", "acceptAll": "...", "rejectAll": "...", "settings": "...", "save": "...", "…": "…" }
}
```

## 7. APIs

| Canal | Uso | Autenticação |
|---|---|---|
| Site controller JSON (`task=consent.save`) | Gravar consentimento a partir do banner | Público, sem token CSRF (compatível com cache); só POST `application/json` same-origin (`Sec-Fetch-Site`), validação estrita, 30 registos/min por IP truncado |
| Web Services `/api/index.php/v1/lcookies/...` | `categories`, `services`, `cookies` (CRUD), `consents` (GET), `config` (GET público) | Token API Joomla, exceto `config` (público); ver `docs/api.md` |
| PHP | `ConsentHelper::has('marketing')`, evento `onLCookiesConsentChange` | — |

## 8. Tarefas agendadas e privacidade

- `plg_task`: purga de logs com mais de X meses; scan semanal com aviso por email.
- `plg_privacy`: inclui/apaga consentimentos do utilizador em pedidos `com_privacy`.

## 9. Fases

| Fase | Entregável |
|---|---|
| 1. Fundação | Estrutura do pacote, instalação/SQL, MVC categorias/serviços/cookies, `config.xml`, idiomas |
| 2. Frontend MVP | `plg_system`: contrato JSON, banner + modal, cookie de consentimento, bloqueio, Consent Mode v2 |
| 3. Prova de consentimento | Endpoint de gravação, tabela de logs, listagem/exportação, versão da política |
| 4. Web Services API | `plg_webservices` + controllers/views em `api/` |
| 5. Extras | Scanner, presets, dashboard, módulo, plugin de conteúdo, task, privacy |
| 6. Qualidade | Testes J5.x/J6.x (Docker), PHPStan, auditoria de acessibilidade, revisão de traduções |

Progresso detalhado: [`TODO.md`](../TODO.md). Notas de cada fase: [`docs/fases/`](fases/).
