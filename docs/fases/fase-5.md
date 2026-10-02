# Fase 5 — Extras

**Data de conclusão:** 2026-10-02 · **Versão:** 0.5.0

## O que foi feito
- **Biblioteca de serviços** (`admin/presets/presets.json`, 13 serviços: GA4, GTM, Google Ads, Meta, LinkedIn, TikTok, Hotjar, Clarity, Matomo, YouTube, Vimeo, Google Maps, reCAPTCHA) e **importação/exportação em JSON** no mesmo formato (`TransferModel`).
- **Edição em lote:** mover serviços entre categorias, com o diálogo `joomla-dialog-batch` do core.
- **Painel** (vista por omissão):
  - totais;
  - escolhas e aceitação por categoria nos últimos 30 dias (só com a permissão de ver registos);
  - alertas de configuração: plugin desligado, serviços não classificados, serviços sem bloqueio, registo desligado, sem página de privacidade, e o resultado do último scan.
- **Integração para programadores:**
  - `ConsentHelper::has()/granted()/get()`, com as mesmas regras que `LCookies.hasConsent()`;
  - evento `onLCookiesConsentChange` (`ConsentChangeEvent`, grupos `system` e `lcookies`, com `getGranted()`/`getRevoked()`);
  - documentação em `docs/developers.md`.
- **`plg_content_lcookies`:**
  - `{lcookies-table [aliases]}` mostra uma tabela acessível com as categorias, os serviços e os cookies;
  - `{lcookies-settings [texto]}` mostra o botão das preferências;
  - layouts substituíveis pelo template.
- **`mod_lcookies`:** botão ou link para as preferências, a escolha atual preenchida em JavaScript (`Intl.ListFormat`/`DateTimeFormat`) e um link para a política. O HTML pode ir para cache.
- **`plg_privacy_lcookies`:** os registos com `user_id` entram nos pedidos de exportação e de remoção do com_privacy. A remoção desliga os registos da conta (por omissão) ou apaga-os.
- **`plg_task_lcookies`:**
  - rotina `lcookies.purge`: elimina os registos expirados;
  - rotina `lcookies.scan`: passagem do servidor do scanner e e-mail pelo template `plg_task_lcookies.scan`.

  O `script.php` do pacote cria o template de e-mail e, ao desinstalar, apaga as tarefas e os templates.
- **Scanner de cookies** (*Componentes → LCookies → Scanner de cookies*, permissão `lcookies.scan`):
  - **passagem do servidor:** `Set-Cookie` sem cookies;
  - **duas passagens no browser** (iframe da mesma origem com `?lcookies_scan=<none|all>.<token>`): pedidos a outros sites e cookies novos sem consentimento; todos os cookies e chaves de storage com tudo aceite;
  - **classificação:** compara com os cookies e padrões declarados e com a biblioteca;
  - **ações nos resultados:** *Declarar* (formulário preenchido, origem *Scanner*) e *Adicionar o serviço*;
  - **histórico:** `#__lcookies_scans`, com os últimos 20.
- **Pré-visualização em tempo real** nos separadores *Aparência* e *Textos* das opções:
  - usa os layouts, o CSS e o JS do frontend em modo `preview`;
  - ecrã de computador (1280 px à escala) ou de telemóvel;
  - banner ou preferências.
- **Idiomas:** revisão dos textos do frontend em pt-PT e en-GB:
  - "site" em vez de "website";
  - "ligação" em vez de "link";
  - as estatísticas deixam de prometer anonimato.
- **Testes:**
  - `e2e_admin.py` 124 verificações;
  - `e2e_front.mjs` 106, incluindo o scan completo no browser e a pré-visualização;
  - novo `e2e_lang.mjs`, 12 por idioma.

## Decisões tomadas (e porquê)
- **Modo de scan com token assinado** (HMAC com o `secret`, válido 1 h), em vez de um parâmetro simples.
  - Sem token, qualquer visitante podia esconder o banner e aceitar tudo só com um parâmetro no URL.
  - No modo de scan não se grava nada, a página não vai para cache (`allowCache(false)` + `onPageCacheIsExcluded`) e o `ConsentHelper` responde como o browser. Assim, o código que depende do consentimento no PHP também é analisado.
- **Passagens no browser sem consentimento primeiro, com tudo aceite depois.** Depois de uma página com tudo aceite, os cookies já existem e esconderiam os que aparecem antes do consentimento.
- **Pedidos a outros sites antes do consentimento contam como problema**, mesmo sem cookies (exemplos: Google Fonts, scripts não bloqueados): cada pedido envia o IP do visitante. Só não contam os que pertencem a serviços de categorias obrigatórias.
- **Tarefa agendada só com a passagem do servidor:** a passagem no browser precisa de um browser. A página de resultados diz isso.
- **Template de e-mail do Joomla (`#__mail_templates`) em vez de texto fixo:** o administrador edita-o em *Sistema → Templates de e-mail*. A linha é inserida pelo `script.php`, porque o `MailTemplate::createTemplate()` do core usa `Factory::getDbo()`.
- **A pré-visualização é gerada no servidor**, com os layouts do frontend e os overrides do template do site, em vez de imitada no JavaScript do backend. Mostra exatamente o que o site vai mostrar e não duplica código. Usa `srcdoc`, por isso não muda a página e não guarda nada.
- **`ConsentHelper` estático e só para output fora da cache.** A forma recomendada continua a ser bloquear no HTML (cache-safe); isto está documentado.
- **`plg_privacy`: desligar da conta por omissão.** Guarda a prova de consentimento (id aleatório e hashes) e cumpre o pedido de remoção.

## Ficheiros/estrutura principais
```
src/com_lcookies/admin/src/Scanner/Scanner.php            páginas, passagem do servidor, token, classificação
src/com_lcookies/admin/src/Model/ScannerModel.php          scans (#__lcookies_scans, sql/updates/*/0.5.0.sql)
src/com_lcookies/admin/src/Controller/{Scan,Preview,Transfer}Controller.php
src/com_lcookies/admin/src/{View/Scanner,View/Dashboard,View/Presets}/, tmpl/{scanner,dashboard,presets}/
src/com_lcookies/admin/src/Field/LcookiespreviewField.php, layouts/preview/page.php
src/com_lcookies/admin/src/Helper/ConsentHelper.php, src/Event/ConsentChangeEvent.php
src/com_lcookies/media/js/{scanner,preview}.js, media/joomla.asset.json
src/plg_content_lcookies/, src/mod_lcookies/, src/plg_privacy_lcookies/, src/plg_task_lcookies/
docs/developers.md, tests/e2e_lang.mjs
```

## Problemas encontrados e soluções
- **O servidor do scan pede o próprio site:** com `php -S` de um só processo, o pedido ficava bloqueado. Nos sites de teste usa-se `PHP_CLI_SERVER_WORKERS=4`. Em produção não é problema.
- **Testes no browser do backend do Joomla 6:**
  - as *view transitions* do Atum (`@view-transition`) impedem o Chromium headless de pintar a página, por isso os cliques e as capturas ficam à espera para sempre. Solução: contexto com `reducedMotion: 'reduce'`;
  - a visita guiada redireciona a página. Solução: `fixture.php enable system guidedtours 0` durante o teste.
- **`Input::getInt()` devolve `null` quando o parâmetro não existe** (J6, com o tipo `int` nas assinaturas). Solução: passar sempre o valor por omissão (`getInt('id', 0)`).
- **Nomes de host de uma só parte** (`localhost`) eram recusados na validação dos resultados do browser.
- **Desinstalar o pacote deixava tarefas agendadas órfãs.** Solução: `uninstall()` no `script.php`.

## Pendente / transita para a fase seguinte
- Decidir com o autor que "extras modernos" entram antes da 1.0:
  - suporte a nonce de CSP: a pré-visualização em `srcdoc` herda a CSP do backend;
  - consentimento partilhado entre subdomínios: rever a UX da opção `cookie_domain`;
  - histórico das versões da política no painel.
- Fase 6 — qualidade:
  - Docker J5/J6;
  - PHPStan e PHP-CS-Fixer;
  - testes de instalação, atualização e desinstalação no CI;
  - auditoria de acessibilidade;
  - revisão de traduções.

## Como testar
1. `npm ci && python3 build/build.py` → `dist/pkg_lcookies-0.5.0.zip`; instalar ou atualizar a partir da 0.4.0. Os plugins novos ficam ativos e o template de e-mail é criado.
2. *Componentes → LCookies → Scanner de cookies → Iniciar scan*; *Opções → Aparência*: a pré-visualização acompanha o formulário.
3. Testes automáticos, só em sites de teste e um de cada vez por site:
   - `python3 tests/e2e_admin.py <url> <db>`
   - `python3 tests/e2e_api.py <url> <raiz joomla>`
   - `node tests/e2e_front.mjs <url> <raiz joomla>`
   - `node tests/e2e_lang.mjs <url> <raiz joomla> <pt-PT|en-GB>`

| Ambiente | Atualização 0.4.0 → 0.5.0 | e2e_admin | e2e_api | e2e_front | e2e_lang |
|---|---|---|---|---|---|
| Joomla 6.0.0 + MariaDB 11.4 | ✅ (via 0.5.0-dev) | 124/124 | 42/42 | 106/106 | 12/12 (pt-PT) |
| Joomla 6.0.0 + PostgreSQL 16 | ✅ | 124/124 | 42/42 | 106/106 | 12/12 (en-GB) |
| Joomla 5.4.9 + MariaDB 11.4 | ✅ (via 0.5.0-dev) | 124/124 | 42/42 | 106/106 | 12/12 (en-GB) |
| Joomla 5.2.6 + MariaDB 11.4 | ✅ | 124/124 | 42/42 | 106/106 | 12/12 (en-GB) |
