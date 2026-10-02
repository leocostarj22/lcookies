# Fase 6 — Qualidade

**Data de conclusão:** 2026-10-02 · **Versão:** 1.0.0

## O que foi feito
- **Padrão de código e análise estática** (só desenvolvimento, `composer.json`):
  - `composer cs` / `composer cs-fix`: PHP-CS-Fixer com as regras do core do Joomla (`.php-cs-fixer.dist.php`);
  - `JOOMLA_PATH=… composer stan`: PHPStan nível 5 contra uma instalação do Joomla (`phpstan.neon`, `build/phpstan-bootstrap.php`).

  As ferramentas encontraram e corrigiram:
  - callbacks `'strlen'` em `array_filter()`;
  - formulários comparados com `false`, embora o `loadForm()` lance exceções;
  - um `delete()` sobre um modelo que pode ser `false` (API);
  - um código de estado HTTP passado como inteiro;
  - a aplicação estreitada para `CMSWebApplicationInterface` no plugin de sistema;
  - o componente estreitado para `MVCFactoryServiceInterface` no plugin de tarefas;
  - os tipos dos modelos declarados nas vistas.
- **CI** (`.github/workflows/ci.yml`), em cada push e pull request:
  - lint em PHP 8.1 a 8.4;
  - padrão de código e PHPStan contra o Joomla 6;
  - build;
  - e2e em Joomla 5.2.6, 5.4.9 e 6.0.0 com MariaDB 11.4, e 6.0.0 com PostgreSQL 16, em contentores de serviço.

  Cada execução e2e:
  - instala o Joomla de raiz (`tests/ci/setup-joomla.sh`);
  - atualiza a partir da última release publicada;
  - corre as suites de backoffice, API, frontend e idioma;
  - desinstala.
- **Ciclo de instalação** (`tests/install_cycle.sh`):
  - `upgrade`: instala uma versão anterior, cria dados, atualiza e verifica versões, plugins ativos, esquema, tabelas, template de e-mail, submenu e dados;
  - `uninstall`: verifica que não fica nenhuma extensão, tabela, tarefa agendada, template de e-mail, item de menu ou ficheiro de media.
- **Testes em qualquer site:**
  - `tests/sql.php` corre SQL na base de dados de um site a partir do `configuration.php`;
  - o `e2e_admin.py` aceita a raiz do Joomla em vez do nome da base de dados local.
- **Acessibilidade do backend:** o axe, com as regras WCAG 2.2 A/AA, corre no painel, nas listas, na biblioteca, nos registos, no scanner e nos formulários. Correções:
  - contraste do `text-muted` e dos badges;
  - tabelas com scroll passam a ser regiões focáveis;
  - a ligação no meio do texto passa a ser sublinhada.
- **Revisão de traduções** do backend (pt-PT e en-GB), além do teste de idioma do frontend da Fase 5.
- **Nomes dos cookies para os visitantes** (pedido do autor depois de instalar a 1.0.0 num site):
  - as preferências e a tabela da política mostravam o padrão técnico (`^[a-f0-9]{32}$`) e prefixos com `*`, e os nomes partiam a meio;
  - os cookies ganharam o campo opcional **Nome mostrado aos visitantes** (`display_name`, SQL `1.0.0.sql`; aceita constantes de idioma);
  - sem esse campo, aparece o nome; um prefixo termina com "…" e uma expressão regular nunca aparece ("Nome variável");
  - o cookie de sessão do Joomla passa a mostrar "Sessão do Joomla (nome aleatório)";
  - os nomes reais aparecem como código e só partem a seguir aos `_`;
  - o contrato ganhou `label` (`ContractBuilder::cookieLabel()`), e a API e a exportação/importação ganharam `display_name`.
- **Correção:** a desinstalação deixava o template de e-mail do scan agendado, porque o `postflight()` do pacote também corre na desinstalação e voltava a criá-lo.

## Decisões tomadas (e porquê)
- **CI com contentores de serviço em vez de um ambiente Docker próprio.** O GitHub Actions já dá MariaDB e PostgreSQL em contentores. O Joomla é instalado de raiz pelo mesmo script que se usa localmente, por isso não há imagens para manter.
- **PHPStan nível 5 sem baseline.** Só há duas regras de exceção documentadas, e são lacunas dos tipos do próprio Joomla:
  - os métodos web em `CMSApplicationInterface`;
  - `getToolbar()` em `Document`.

  A análise é feita contra o Joomla 6. No 5.2, a biblioteca de base de dados não declara `createQuery()` na interface, embora o método exista.
- **Auditoria limitada ao conteúdo do LCookies** (`#content`), com as regras WCAG. Fica de fora o `span` com `aria-labelledby` dos botões de estado desativados do core (JGrid), que existe em todas as listas do Joomla. Fica também de fora a regra de boa prática `label-title-only` do `grid.checkall` do core.
- **1.0.0:** todas as fases do plano estão concluídas, com testes automáticos em todas as versões suportadas e atualizações testadas desde a 0.4.0.

## Ficheiros/estrutura principais
```
composer.json, composer.lock, .php-cs-fixer.dist.php, phpstan.neon, build/phpstan-bootstrap.php
.github/workflows/ci.yml, tests/ci/setup-joomla.sh, tests/install_cycle.sh, tests/sql.php
```

## Problemas encontrados e soluções
- **O ensaio local da CI**, num Joomla 6 instalado de raiz, encontrou dois problemas:
  - os caminhos relativos dos pacotes deixavam de ser válidos no `install_cycle.sh`, que corre o instalador a partir da raiz do Joomla;
  - o template de e-mail ficava para trás depois da desinstalação.

  Os dois estão corrigidos.
- **O `text-muted` do Atum** não cumpre o contraste de 4,5:1 em texto pequeno. Os textos do LCookies passaram a usar a cor normal do corpo.

## Pendente / transita para a fase seguinte
- Acompanhar a primeira execução da CI no GitHub, depois do push: até agora só foi ensaiada localmente.
- Ideias depois da 1.0:
  - mais serviços na biblioteca;
  - mais idiomas;
  - consentimento registado também na API REST, para aplicações headless.

## Como testar
1. `python3 build/build.py` → `dist/pkg_lcookies-1.0.0.zip`. Atualizar a partir da 0.4.0, 0.5.0 ou 0.6.0.
2. `composer install && composer cs && JOOMLA_PATH=/caminho/joomla composer stan`.
3. `tests/install_cycle.sh upgrade <raiz> dist/pkg_lcookies-1.0.0.zip <zip anterior>` e depois as suites (ver `README.md`).

| Ambiente | Atualização → 1.0.0 | e2e_admin | e2e_api | e2e_front | e2e_lang |
|---|---|---|---|---|---|
| Joomla 6.0.0 + MariaDB 11.4 | ✅ (de 0.6.0) | 130/130 | 42/42 | 126/126 | 12/12 (pt-PT) |
| Joomla 6.0.0 + PostgreSQL 16 | ✅ (de 0.6.0) | 130/130 | 42/42 | 126/126 | 12/12 (en-GB) |
| Joomla 5.4.9 + MariaDB 11.4 | ✅ (de 0.6.0) | 130/130 | 42/42 | 126/126 | 12/12 (en-GB) |
| Joomla 5.2.6 + MariaDB 11.4 | ✅ (de 0.4.0, desinstalação e reinstalação) | 130/130 | 42/42 | 126/126 | 12/12 (en-GB) |
