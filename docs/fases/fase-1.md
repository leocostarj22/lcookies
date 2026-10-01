# Fase 1 — Fundação

**Data de conclusão:** 2026-10-01 · **Versão:** 0.1.0

## O que foi feito
- Pacote `pkg_lcookies` (manifest, `script.php` com versões mínimas, idiomas do pacote) e build em `build/build.py`.
- `com_lcookies` (só administração por agora):
  - 3 tabelas (`categories`, `services`, `cookies`) em MySQL/MariaDB e PostgreSQL, com dados iniciais.
  - MVC completo das três entidades: listas com pesquisa, filtros, ordenação, *drag & drop* agrupado, publicar/despublicar/lixo/eliminar/check-in; formulários com guardar, guardar e novo, guardar como cópia.
  - Opções (`config.xml`) e permissões (`access.xml`), já com as definições que o frontend vai usar.
  - Idiomas en-GB e pt-PT (206 chaves cada, verificados automaticamente).
- Teste end-to-end `tests/e2e_admin.py` (34 verificações).

## Decisões tomadas (e porquê)
- **Joomla mínimo 5.2.0** (era 5.0). O Joomla 5.0/5.1 inclui o `joomla/database` 3.0, cuja reordenação (`Table::reorder`, truque `@rownum`) ignora o `ORDER BY` no MariaDB/MySQL modernos, e o *drag & drop* não grava. O bug é do core (afeta também os componentes nativos) e foi corrigido no `joomla/database` 3.2.0, a partir do Joomla 5.1.x/5.2.0. As versões 5.0/5.1 já não têm suporte.
- **Textos dos dados como constantes de idioma**, traduzidos com `LcookiesHelper::text()`. Segue o padrão dos workflows do core: os dados por omissão aparecem no idioma do visitante e são alteráveis em *Substituições de idioma*, sem colunas por idioma. O administrador pode escrever texto simples.
- **As categorias deixaram de ter coluna `language`** (consequência da decisão anterior).
- **Os cookies pertencem sempre a um serviço** e a categoria vem do serviço (evita inconsistências). Os resultados do scanner ficarão em `#__lcookies_scans` até serem classificados.
- **Duração estruturada** (`duration_value` + `duration_unit`) em vez de texto livre, para ser traduzida e pluralizada.
- **Categorias core** (`necessary`, `unclassified`): o alias, o estado e a obrigatoriedade são impostos na `Table::check()`. O valor de `core` nunca vem do formulário; o modelo recusa despublicar ou eliminar estas categorias.
- **Eliminação**: só depois de ir para o lixo. Uma categoria com serviços não pode ser eliminada. Eliminar um serviço elimina os seus cookies.
- **INSERTs iniciais idempotentes** (`INSERT IGNORE` / `ON CONFLICT DO NOTHING`, IDs explícitos, `setval` calculado), para que a instalação sobre tabelas que tenham sobrado não falhe.

## Ficheiros/estrutura principais
```
src/pkg_lcookies.xml, src/script.php, src/language/
src/com_lcookies/lcookies.xml
src/com_lcookies/admin/
  access.xml, config.xml, forms/, language/{en-GB,pt-PT}/, services/provider.php, sql/, tmpl/
  src/Extension/LcookiesComponent.php
  src/Helper/LcookiesHelper.php          constantes (GCM, tipos, unidades), text(), duration(), isValidRegex()
  src/Table/AbstractLcookiesTable.php     state/checkout/created/modified + isUnique()
  src/Model/AbstractItemModel.php         form, ordering por grupo, save2copy, trash-before-delete
  src/View/AbstractListView.php, AbstractEditView.php
  src/Field/LcookiescategoryField.php, LcookiesserviceField.php
build/build.py, tests/e2e_admin.py, tests/pgq.php
```

## Problemas encontrados e soluções
- `addControlField()` / `renderControlFields()` só existem no J6: usados os campos ocultos clássicos.
- `Table::getDatabase()` não existe no J5.0: as tabelas usam `$this->_db`.
- Um conjunto de checkboxes todo desmarcado não envia nada: `CategoryModel::save()` usa `[]` por omissão.
- No PostgreSQL, `CREATE INDEX` sem `IF NOT EXISTS` fazia falhar a reinstalação sobre tabelas existentes. Corrigido.
- Avisos do log de deprecações: são os mesmos em páginas do core (com_banners) e vêm do template, módulos e plugins do Joomla. O código do LCookies não usa APIs deprecated (verificado por grep).

## Pendente / transita para a fase seguinte
- URL do servidor de atualizações (bloco comentado em `src/pkg_lcookies.xml`).
- Batch nas listas: passou para a Fase 5.
- Na Fase 2, o ficheiro de idioma do site tem de incluir as constantes `COM_LCOOKIES_CAT_*`, `_SVC_*`, `_COOKIE_*` e `_DURATION_*`.

## Como testar
1. `python3 build/build.py` → `dist/pkg_lcookies-0.1.0.zip` → instalar em *Sistema → Instalar → Extensões*.
2. *Componentes → LCookies*: Categorias, Serviços e Cookies.
3. Teste automático contra um site com prefixo `jos_` e o utilizador `admin`:
   `python3 tests/e2e_admin.py http://127.0.0.1:8106 j6` (MySQL) ou `... j6pg` (PostgreSQL, o nome termina em `pg`).
   O teste **repõe as tabelas do LCookies**, por isso usa-o só num site de testes.

Ambiente usado nesta fase (no scratchpad, sem Docker): PHP 8.3 estático (static-php.dev), MariaDB 11.4.5 (tarball oficial), PostgreSQL 16 (binários zonky), Joomla 5.0.0, 5.4.9 e 6.0.0 instalados por `installation/joomla.php install` e servidos com `php -S`. No Joomla 6 com `php -S` é preciso definir `$live_site`, senão os redirecionamentos duplicam `/administrator`; acontece também com o core.

| Ambiente | Instalação | Atualização | Desinstalação | e2e |
|---|---|---|---|---|
| Joomla 5.4.9 + MariaDB 11.4 | ✅ | ✅ | — | 34/34 |
| Joomla 6.0.0 + MariaDB 11.4 | ✅ | ✅ | ✅ (sem restos) | 34/34 |
| Joomla 6.0.0 + PostgreSQL 16 | ✅ (também sobre tabelas que sobraram) | — | ✅ | 34/34 |
| Joomla 5.0.0 + MariaDB 11.4 | recusado (mínimo 5.2) | — | — | 33/34 antes da subida do mínimo (bug do core na reordenação) |
