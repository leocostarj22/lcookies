# Fase 4 — Web Services API

**Data de conclusão:** 2026-10-01 · **Versão:** 0.4.0

## O que foi feito
- **`plg_webservices_lcookies`** (SubscriberInterface, `BeforeApiRouteEvent`, provider com o mesmo ramo de versão do construtor do plugin de sistema). Rotas:
  - CRUD de `v1/lcookies/categories`, `/services` e `/cookies`;
  - GET de `v1/lcookies/consents` e `/consents/:id`;
  - GET público de `v1/lcookies/config`.

  O pacote ativa o plugin na primeira instalação (`$plugins` em `src/script.php`).
- **`com_lcookies/api/src`** (manifest `<api>`):
  - `Controller\AbstractLcookiesController`:
    - carrega o idioma do admin, para as mensagens de erro dos modelos e tabelas;
    - verifica as permissões da mesma forma em todas as versões;
    - aceita filtros `filter[...]` e ordenação `list[ordering|direction]`, ambos em lista branca;
    - `delete()` só apaga o que está no lixo e responde 409 nos outros casos.
  - `CategoriesController`, `ServicesController`, `CookiesController`: reutilizam os modelos, formulários e tabelas do admin através do `ApiMVCFactory` (recorre a `Administrator`).
  - `ConsentsController`: só leitura (`lcookies.consents.view`); POST, PATCH e DELETE dão 403.
  - `ConfigController` + `Model\ConfigModel`: o contrato do frontend, com `?language=` validado contra os idiomas instalados no site.
  - `Model\ConsentModel`: um registo.
  - `View\*\JsonapiView`: campos de item e de lista; `gcm_types` e `categories` devolvidos como listas.
- **Backend**:
  - `CategoryModel::delete()` lança `DeleteRefusedException` quando nenhuma categoria pode ser apagada (antes devolvia `true` sem apagar nada);
  - o `CategoriesController` do admin apanha a exceção e mostra o motivo;
  - `gcm_types` com `validate="options"`: um tipo desconhecido passa a ser recusado em vez de ignorado.
- **Documentação**: `docs/api.md` (rotas, permissões, filtros, campos, exemplos curl, erros).
- **Teste**: `tests/e2e_api.py`, com 42 verificações:
  - autenticação;
  - `config` público, traduzido e com 404 para idiomas desconhecidos;
  - listas, filtros, ordenação, paginação e item;
  - criar, PATCH parcial e as regras de validação;
  - categoria de sistema protegida;
  - regras de apagar (409);
  - apagar um serviço apaga os seus cookies;
  - as alterações chegam ao contrato (cache limpa);
  - consentimentos: filtros, item e só leitura;
  - permissões com um utilizador Manager e um token.

  O `tests/fixture.php` ganhou os comandos `token`, `asset`, `plugin` e `deluser`.

## Decisões tomadas (e porquê)
- **As permissões são verificadas no nosso controller base, não no `ApiController` do core.**
  - O core não verifica a leitura.
  - As regras de escrita mudam entre versões (5.4 passou a exigir `core.manage`).
  - No **Joomla 5.4.9, `ApiController::allowEdit()` usa `InflectorFactory` sem `use`**, o que dá erro fatal em qualquer PATCH. O bug também afeta as APIs do core.

  Regra adotada: ler exige `core.manage` (consentimentos: `lcookies.consents.view`); escrever exige `core.manage` mais `core.create`, `core.edit` ou `core.delete`.
- **`delete()` próprio.**
  - No 5.4.9, o core responde 204 quando o modelo recusa sem `setError()`, ou seja, com sucesso falso.
  - O nosso responde 409 com o motivo: o registo não está no lixo, a categoria tem serviços ou é de sistema.
  - O nome do modelo vem de `$itemModel`, em vez de passar pelo `Inflector`.
- **Erros novos com exceções, e não com `setError()`** (deprecated, "Throw an Exception instead"). A `DeleteRefusedException` herda de `Controller\Exception\Save`, por isso a API usa o código 409 e a mensagem; no admin, o controller apanha-a. As tabelas continuam a usar `setError()` em `check()`, porque é o contrato que o `AdminModel::save()` do core ainda lê (J5 e J6).
- **Valores devolvidos como estão guardados** (constantes de idioma nos dados por omissão), para que um GET seguido de PATCH não estrague nada. O texto traduzido está no `config`.
- **`config` é público**: tem exatamente o que as páginas do site já publicam em `Joomla.getOptions('lcookies')`. Serve integrações headless e aplicações. A tradução noutro idioma troca o idioma da aplicação como faz o plugin de filtro de idioma do core (`loadLanguage()` + `Factory::$language`).
- **Os consentimentos não são graváveis pela API.** A prova vem do banner, pelo endpoint do site da Fase 3, com rate-limit e hash do IP do visitante, que não seria o do cliente da API.

## Ficheiros/estrutura principais
```
src/plg_webservices_lcookies/   lcookies.xml, services/provider.php, src/Extension/Lcookies.php, language/
src/com_lcookies/api/src/Controller/{AbstractLcookies,Categories,Services,Cookies,Consents,Config}Controller.php
src/com_lcookies/api/src/Model/{Config,Consent}Model.php
src/com_lcookies/api/src/View/{Categories,Services,Cookies,Consents,Config}/JsonapiView.php
src/com_lcookies/admin/src/Exception/DeleteRefusedException.php
docs/api.md, tests/e2e_api.py
```

## Problemas encontrados e soluções
- **A `JsonApiView` do core exige um modelo** (`$this->get('Errors')` sobre o modelo por omissão). Ao passar o item diretamente dava `count(null)`. Solução: `Api\Model\ConfigModel` e o fluxo normal do `ApiController::displayItem()`.
- **Joomla 5.4.9**: `allowEdit()` com erro fatal e `delete()` com 204 falso. Resolvidos no controller base (ver decisões).
- **Os tokens da API do Joomla só funcionam para os grupos permitidos no plugin "Utilizador - Token da API Joomla"** (por omissão, só Super Utilizadores). Para testar com o grupo Manager, o teste altera temporariamente esse parâmetro e o `core.login.api` do root, e repõe tudo no fim.
- **Ordem dos testes**: o `e2e_api.py` cria e apaga os seus registos (`lcapi-*`). Se falhar a meio, os restos afetam o `e2e_front.mjs`, porque aparece uma categoria a mais no banner. Para limpar, apagar `lcapi-*` e `api-service`.

## Pendente / transita para a fase seguinte
- Fase 5 (extras):
  - scanner, presets e dashboard;
  - `plg_task` (limpeza agendada e scan);
  - `plg_privacy` (exportar e apagar os consentimentos de um `user_id`);
  - `plg_content`.
- Validação visual do pt-PT; URL do servidor de atualizações.

## Como testar
1. `python3 build/build.py` → `dist/pkg_lcookies-0.4.0.zip`; instalar/atualizar (o plugin "Serviços Web - LCookies" fica ativo).
2. Criar um token: *Utilizadores → (Super Utilizador) → Token da API Joomla*, depois `curl -H "Authorization: Bearer <token>" https://site/api/index.php/v1/lcookies/categories` (ver `docs/api.md`).
3. Testes automáticos (só em sites de teste, um de cada vez por site):
   - `python3 tests/e2e_admin.py <url> <db>`
   - `python3 tests/e2e_api.py <url> <raiz joomla>`
   - `node tests/e2e_front.mjs <url> <raiz joomla>`

| Ambiente | Atualização 0.3.0 → 0.4.0 | e2e_admin | e2e_api | e2e_front |
|---|---|---|---|---|
| Joomla 6.0.0 + MariaDB 11.4 | ✅ | 57/57 | 42/42 | 64/64 |
| Joomla 6.0.0 + PostgreSQL 16 | ✅ | 57/57 | 42/42 | 64/64 |
| Joomla 5.4.9 + MariaDB 11.4 | ✅ | 57/57 | 42/42 | 64/64 |
| Joomla 5.2.6 + MariaDB 11.4 | ✅ | 57/57 | 42/42 | 64/64 |
