# TODO — LCookies

Legenda: `[ ]` por fazer · `[~]` em curso · `[x]` concluído
Ao fechar uma fase: marcar tudo, preencher `docs/fases/fase-N.md` e atualizar o estado em `CLAUDE.md`.

## Fase 0 — Planeamento
- [x] Definir recursos backend/frontend
- [x] Decidir namespace, âmbito (sem TCF, sem geo), registo de IP, idiomas
- [x] Criar `docs/PLAN.md`, `TODO.md`, `CLAUDE.md`, `docs/fases/`

## Fase 1 — Fundação ✅ (2026-10-01)
- [x] Estrutura do pacote `pkg_lcookies` (manifest, script de instalação, build `build/build.py`)
- [x] Servidor de atualizações: asset `pkg_lcookies.xml` de cada release do GitHub (`releases/latest/download/pkg_lcookies.xml`), gerado pelo `build.py` e publicado pela Action `release.yml` (testado: deteção, instalação e sha512 errado recusado, J5.2.6 e J6)
- [x] `com_lcookies`: manifest, `services/provider.php`, Extension class
- [x] SQL de instalação/desinstalação + `sql/updates/` (MySQL/MariaDB e PostgreSQL)
- [x] Dados iniciais: 5 categorias, serviço "Este site" e 4 cookies do Joomla/LCookies
- [x] MVC admin: Categorias (lista + formulário)
- [x] MVC admin: Serviços (lista + formulário)
- [x] MVC admin: Cookies (lista + formulário)
- [x] `config.xml` (Geral, Bloqueio, Aparência, Textos, Consent Mode, Registos, Permissões)
- [x] `access.xml` (ACL)
- [x] Ficheiros de idioma pt-PT e en-GB (admin + sys + pacote)
- [x] Submenu do componente
- [x] Teste de instalação em J5 e J6 (5.4.9 e 6.0.0, MariaDB 11.4 e PostgreSQL 16)

## Fase 2 — Frontend MVP ✅ (2026-10-01)
- [x] `plg_system_lcookies` (SubscriberInterface, DI; construtor compatível com 5.2 e 5.3+)
- [x] Gerar contrato JSON (`ContractBuilder` no componente, com cache) + `docs/contract.schema.json`
- [x] JLayouts: banner (barra topo/fundo, caixa esq./dir., modal), preferências, botão flutuante, placeholder
- [x] JS ES module: estado, cookie `lcookies_consent`, API pública, eventos `lcookies:ready` / `lcookies:change`
- [x] CSS com custom properties (tema claro/escuro/auto, cor e raio das opções)
- [x] Bloqueio servidor de `<script>` e `<iframe>` + placeholders; código dos serviços em `<template>`
- [x] Desbloqueio cliente + `MutationObserver` + proteção de `createElement` (script do head)
- [x] Google Consent Mode v2 (default + update + evento `dataLayer`)
- [x] Limpeza de cookies ao retirar consentimento (JS: cookies + storage; PHP: expira no pedido seguinte)
- [x] GPC
- [x] Acessibilidade (`<dialog>` nativo, teclado, aria, axe-core sem violações)
- [x] Idiomas pt-PT / en-GB (site) — constantes `COM_LCOOKIES_CAT_*`, `_SVC_*`, `_COOKIE_*`, `_DURATION_*` + `COM_LCOOKIES_UI_*`
- [x] Minificação no build (esbuild) e teste no browser `tests/e2e_front.mjs`

## Fase 3 — Prova de consentimento ✅ (v0.3.0, ver `docs/fases/fase-3.md`)
- [x] Site controller `consent.save` (POST JSON same-origin, validação estrita, rate-limit 30/min por IP truncado)
- [x] Truncagem de IP (/24, /48) + HMAC-SHA256 com o `secret` do site (IP e user agent)
- [x] Tabela `#__lcookies_consents` + `ConsentLog` + `ConsentsModel`
- [x] Vista admin de registos (filtros, histórico por UUID, ACL `lcookies.consents.view`)
- [x] Exportação CSV (filtros ativos, UTF-8 BOM, anti-fórmulas, ACL `lcookies.consents.export`)
- [x] Versão da política guardada em cada registo (pedidos com versão antiga recusados)
- [x] Retenção: limpeza automática (1 em cada 100 gravações) + botão "Eliminar expirados"
- [ ] (opcional) Plugin `task` para limpeza agendada

## Fase 4 — Web Services API ✅ (v0.4.0, ver `docs/fases/fase-4.md` e `docs/api.md`)
- [x] `plg_webservices_lcookies` (rotas `v1/lcookies/...`, ativado na primeira instalação)
- [x] `api/` controllers + JSON:API views: categories, services, cookies (CRUD com as regras do backend)
- [x] `consents` (GET, `lcookies.consents.view`, só leitura)
- [x] `config` (GET público = contrato, `?language=`)
- [x] Documentação dos endpoints (`docs/api.md`)
- [x] Teste `tests/e2e_api.py`

## Fase 5 — Extras
- [x] Biblioteca de presets (13 serviços: GA4, GTM, Google Ads, Meta, LinkedIn, TikTok, Hotjar, Clarity, Matomo, YouTube, Vimeo, Maps, reCAPTCHA) + import/export JSON (mesmo formato; `TransferModel`)
- [x] Batch nas listas (mover serviços entre categorias, diálogo `joomla-dialog-batch` do core)
- [ ] Scanner (servidor + cliente)
- [ ] Dashboard com estatísticas
- [ ] Pré-visualização em tempo real no backend
- [ ] Validar as traduções pt-PT do frontend num site com o pacote de idioma pt-PT instalado
- [ ] `mod_lcookies`
- [ ] `plg_content_lcookies` ({lcookies-table}, {lcookies-settings})
- [ ] `plg_task_lcookies` (purga + scan agendado)
- [ ] `plg_privacy_lcookies`
- [ ] `ConsentHelper::has()` + evento `onLCookiesConsentChange`

## Fase 6 — Qualidade
- [ ] Ambiente Docker J5.x e J6.x
- [ ] PHPStan / PHP-CS-Fixer (padrão Joomla)
- [ ] Testes de instalação, atualização e desinstalação
- [ ] Auditoria de acessibilidade
- [ ] Revisão de traduções
- [ ] Build do pacote (script) + release
