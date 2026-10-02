# LCookies — contexto do projeto

Sistema de consentimento de cookies para Joomla 5.x e 6.x. Plano completo: `docs/PLAN.md`. Tarefas: `TODO.md`.

## Estado atual
- **Todas as fases do plano concluídas — v1.0.0** (Fase 6 — Qualidade; ver `docs/fases/fase-6.md`). Antes: 5.1 (v0.6.0), 5 (v0.5.0), 0.1.0–0.4.0; servidor de atualizações ativo.
- **Testes:** admin 130/130, API 42/42, front 126/126, idioma 12/12 nos 4 sites de teste; atualizações 0.4.0/0.6.0 → 1.0.0, desinstalação e instalação de raiz verdes; CI em `.github/workflows/ci.yml`.
- **Onde parámos (2026-10-02):** commit `chore(release): 1.0.0` feito; o autor envia o código e cria a tag `v1.0.0` (e, se quiser, `v0.5.0`/`v0.6.0`). A seguir: acompanhar a primeira execução da CI no GitHub; ideias pós-1.0 em `docs/fases/fase-6.md`.
  - Testes e desenvolvimento só neste ambiente (sem testes em sites externos).

## Fluxo de trabalho por fase
1. Antes de começar: ler `TODO.md` e a nota da fase anterior em `docs/fases/`.
2. Durante: marcar itens em `TODO.md` (`[~]` em curso, `[x]` concluído).
3. Ao fechar: criar `docs/fases/fase-N.md` a partir de `docs/fases/_modelo.md` e atualizar "Estado atual" acima.

## Estrutura
- `src/` — código das extensões (`src/com_lcookies` com `admin/`, `site/`, `api/`; `src/plg_system_lcookies`, `src/plg_webservices_lcookies`, `src/plg_content_lcookies`, `src/plg_privacy_lcookies`, `src/plg_task_lcookies`, `src/mod_lcookies`, futuros `src/plg_*`) + manifest do pacote. Plugins novos: listar em `src/pkg_lcookies.xml` e em `$plugins` de `src/script.php` (ativação na 1.ª instalação).
- Releases: tag `vX.Y.Z` igual a `<version>` de `src/pkg_lcookies.xml` → `.github/workflows/release.yml` cria a release no GitHub (`leocostarj22/lcookies`, público) com o zip e `pkg_lcookies.xml` (servidor de atualizações: `releases/latest/download/pkg_lcookies.xml`, sha512). Ver `README.md`.
- `build/build.py` — gera `dist/pkg_lcookies-<versão>.zip` e `dist/pkg_lcookies.xml` (cada pasta `com_/plg_/mod_` vira `packages/<nome>.zip`; tem de estar listada em `src/pkg_lcookies.xml`). Minifica os `.js/.css` de `media/` com esbuild → precisa de `npm install` (ver `package.json`).
- `tests/e2e_admin.py` — teste end-to-end do backend (ver `docs/fases/fase-1.md`).
- `tests/e2e_front.mjs` + `tests/fixture.php` — teste do frontend num browser real, Playwright + axe-core (ver `docs/fases/fase-2.md`).
- `tests/e2e_api.py` — teste da API Web Services (tokens/permissões via `tests/fixture.php`; ver `docs/api.md`).
- `tests/install_cycle.sh upgrade|uninstall <root> …` — ciclo de instalação com verificações; `tests/sql.php <root>` corre SQL na base de dados do site (MySQL/PostgreSQL); `tests/ci/setup-joomla.sh` instala um Joomla de raiz (CI e local).
- Qualidade: `composer cs` (regras do core do Joomla, `.php-cs-fixer.dist.php`) e `JOOMLA_PATH=… composer stan` (PHPStan nível 5, `phpstan.neon`; as exceções documentadas são só lacunas de tipos do Joomla). CI: `.github/workflows/ci.yml`.
- `tests/e2e_lang.mjs <url> <root> <pt-PT|en-GB>` — o frontend só mostra textos do idioma do site (sem constantes nem restos do outro idioma); o site tem de estar nesse idioma.

## Convenções
- Namespaces: `Lcsilva\Component\Lcookies\{Administrator,Site,Api}`, `Lcsilva\Plugin\<Grupo>\Lcookies`, `Lcsilva\Module\Lcookies`.
- Joomla mínimo 5.2.0 (`src/script.php`); PHP: sintaxe 8.1, sem `declare(strict_types=1)` (segue o core); testar em 8.3+.
- Queries: `$db->createQuery()` (nunca `getQuery(true)`); nas tabelas usar `$this->_db` (existe em J5 e J6).
- Formulários: campos ocultos `task`/`boxchecked` + `HTMLHelper::_('form.token')` (não usar `addControlField`/`renderControlFields`, só existem no J6).
- Valores guardados que sejam constantes de idioma passam por `LcookiesHelper::text()` na apresentação.
- Cada mudança de schema: novo `sql/updates/{mysql,postgresql}/<versão>.sql` + subir `<version>` nos manifests.
- Proibido: APIs deprecated (`CMSObject`, `Factory::getDbo/getUser/getLanguage/getDocument`, `JText`, `JFactory`, aliases legados). Usar `getDatabase()`, `getCurrentUser()`, `getDocument()`, `Factory::getApplication()->getIdentity()`, `Text::_()`.
- Plugins: `SubscriberInterface` + classes de evento concretas.
- Queries: sempre `ParameterType` e `bind()`.
- Assets: `joomla.asset.json` + WebAssetManager; JS vanilla ES module.
- Idiomas: pt-PT e en-GB; prefixos `COM_LCOOKIES_`, `PLG_SYSTEM_LCOOKIES_`, `MOD_LCOOKIES_`.
- Frontend e backend falam **apenas** através do contrato JSON (`docs/contract.schema.json`), gerado por `Administrator\Contract\ContractBuilder`. Mudança incompatível → subir `ContractBuilder::SCHEMA` e o schema.
- Textos do frontend: idioma do site do componente (`src/com_lcookies/site/language/*/com_lcookies.ini`, `COM_LCOOKIES_UI_*`), carregado com `LcookiesHelper::loadSiteLanguage()`. Constantes de dados novas vão para os `.ini` do admin **e** do site.
- Frontend: o servidor bloqueia sempre (HTML igual para todos → cache-safe) e o JS desbloqueia; layouts em `plg_system_lcookies/layouts/lcookies/` (override `templates/<t>/html/layouts/lcookies/`), a interface depende dos atributos `data-lcookies-*`.
- `CMSPlugin`: o construtor recebe o dispatcher só em J < 5.3 (ver `services/provider.php` do plugin); repetir este padrão nos plugins novos.
- Registos de consentimento: lógica partilhada em `Administrator\Consent\ConsentLog` (validação, rate-limit, gravação, retenção) e `Anonymizer` (IP truncado + HMAC com o `secret`); nunca guardar IP, user agent ou query string em claro; registos só de leitura.
- Listas com `getExportRows()`/grandes leituras: `$query->setLimit()` (o `setQuery($q, $offset, $limit)` é deprecated).
- API (`api/src`): controllers herdam `Api\Controller\AbstractLcookiesController` (permissão de leitura, filtros e ordenação em lista branca); reutiliza os modelos/formulários/tabelas do admin (o `ApiMVCFactory` recorre a `Administrator`).
- Erros novos em modelos: lançar exceção (ex. `Administrator\Exception\DeleteRefusedException`, 409 na API) em vez de `setError()` (deprecated); os controllers admin apanham-na.
- Commits: [Conventional Commits](https://www.conventionalcommits.org/) em inglês — `tipo(âmbito): resumo` no imperativo, ≤ 72 caracteres (tipos `feat`, `fix`, `docs`, `test`, `refactor`, `perf`, `build`, `ci`, `chore`; âmbitos `component`, `system`, `webservices`, `api`, `build`, `release`…), corpo a explicar o porquê, `BREAKING CHANGE:` quando aplicável. Um commit por alteração lógica. **Sem assinaturas/linhas Co-Authored-By.** Autor/créditos: leocostadeveloper (www.leocostadeveloper.com).
- Consentimento no servidor: `Administrator\Helper\ConsentHelper` (mesmas regras que `LCookies.hasConsent()`); escolhas gravadas disparam `onLCookiesConsentChange` (`Administrator\Event\ConsentChangeEvent`, grupos `system` e `lcookies`). Documentado em `docs/developers.md`.
- Scanner: `Administrator\Scanner\Scanner` (páginas, passagem do servidor, token `?lcookies_scan=<none|all>.<token>`, classificação) + `ScannerModel` (`#__lcookies_scans`) + `media/com_lcookies/js/scanner.js` (passagens no browser, iframe da mesma origem). O modo de scan é respeitado por `plg_system_lcookies` (PHP e JS) e pelo `ConsentHelper`; documentado em `docs/developers.md`.
- Testes no browser do backend do J6: contexto com `reducedMotion: 'reduce'` (as view transitions do Atum param o Chromium headless) e `fixture.php enable system guidedtours 0`.
- Nomes de cookies para visitantes: `label` do contrato (`ContractBuilder::cookieLabel()`; campo `display_name`), apresentado com `LcookiesHelper::cookieLabelHtml()` nos layouts — nunca mostrar `name` diretamente (pode ser um padrão).
- Fora do âmbito: IAB TCF, geo-targeting.
