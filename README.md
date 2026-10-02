# LCookies

Sistema de consentimento de cookies para **Joomla 5.2+ e 6**, feito para cumprir o RGPD e a diretiva ePrivacy sem tornar o site mais lento nem impedir a cache.

## Funcionalidades

- **Banner e preferências acessíveis**, pensados para WCAG 2.2 AA e testados com axe
  - Cinco posições: barra em cima ou em baixo, caixa à esquerda ou à direita, janela modal
  - Tema claro, escuro ou automático
  - Cor e cantos à escolha
  - Botão flutuante para mudar de ideias
- **Bloqueio real:** scripts, iframes e o código dos serviços ficam bloqueados até haver consentimento.
  - O HTML é igual para todos os visitantes, por isso o site pode usar cache de página e CDN.
  - Os iframes bloqueados mostram um aviso com um botão para permitir.
- **Google Consent Mode v2** (incluindo `ads_data_redaction` e `url_passthrough`) e respeito pelo sinal **Global Privacy Control**.
- **Prova de consentimento:**
  - registo de cada escolha, com o IP truncado e com hash;
  - exportação em CSV;
  - período de retenção configurável, com limpeza automática ou por tarefa agendada;
  - versão da política: quando muda, o banner volta a aparecer.
- **Painel** com totais, aceitação por categoria nos últimos 30 dias e alertas de configuração.
- **Biblioteca de serviços:** 13 serviços prontos a usar, como GA4, GTM, Google Ads, Meta, LinkedIn, TikTok, Hotjar, Clarity, Matomo, YouTube, Vimeo, Google Maps e reCAPTCHA.
- **Importação e exportação** em JSON.
- **Edição em lote**, por exemplo para mover serviços entre categorias.
- **Página da política de cookies sempre atualizada:** `{lcookies-table}` mostra as categorias, os serviços e os cookies, e `{lcookies-settings}` um botão para reabrir as preferências.
- **Módulo** para o rodapé, com o botão de preferências e a escolha atual do visitante.
- **Privacidade (RGPD):** os consentimentos entram nos pedidos de exportação e de remoção de *Utilizadores → Privacidade*.
- **API REST** (Web Services do Joomla) e integração para programadores:
  - em PHP, `ConsentHelper::has('marketing')` e o evento `onLCookiesConsentChange`;
  - em JavaScript, `window.LCookies` e os eventos `lcookies:*`.
- **Idiomas:** pt-PT e en-GB. Os textos do banner podem ser alterados nas opções.

## O que o pacote instala

| Extensão | Função |
|---|---|
| `com_lcookies` | Componente: categorias, serviços, cookies, biblioteca, registos de consentimento, painel e opções |
| `plg_system_lcookies` | Banner, bloqueio, Consent Mode e limpeza de cookies rejeitados |
| `plg_content_lcookies` | Códigos `{lcookies-table}` e `{lcookies-settings}` |
| `plg_privacy_lcookies` | Pedidos de exportação e remoção de dados (com_privacy) |
| `plg_task_lcookies` | Tarefa agendada que elimina os registos expirados |
| `plg_webservices_lcookies` | Rotas da API REST |
| `mod_lcookies` | Módulo "Definições de cookies" |

Os plugins ficam ativos na primeira instalação.

> A release publicada é a 0.4.0. O painel, a biblioteca, a importação/exportação, a edição em lote, os códigos de conteúdo, o módulo, a integração com a Privacidade, a tarefa agendada e o `ConsentHelper` estão no ramo `main` e saem na versão 0.5.0.

## Requisitos

- **Joomla:** 5.2 ou superior, incluindo o 6.x.
- **PHP:** 8.1 ou superior.
- **Base de dados:** MySQL/MariaDB ou PostgreSQL.

## Instalação

Descarregue `pkg_lcookies-X.Y.Z.zip` da [última release](https://github.com/leocostarj22/lcookies/releases/latest) e instale-o em *Sistema → Instalar → Extensões*. As versões seguintes aparecem em *Sistema → Atualizar → Extensões*.

### Primeiros passos

1. Em *Componentes → LCookies → Opções*:
   - escolha o aspeto do banner;
   - escolha a página da política de privacidade/cookies;
   - ligue o Google Consent Mode se usar ferramentas da Google.
2. Em *Componentes → LCookies → Serviços*, use o botão *Biblioteca* para adicionar os serviços que o site usa. Para outros serviços, use *Novo*, com os padrões que identificam os scripts a bloquear.
3. No artigo da política de cookies, escreva `{lcookies-table}` e `{lcookies-settings}`.
4. Opcional: publique o módulo *LCookies - Definições de cookies* no rodapé.
5. Opcional: em *Sistema → Tarefas agendadas*, crie uma tarefa diária *LCookies - Eliminar registos de consentimento expirados*.
6. Abra o *Painel* do LCookies e corrija os alertas que aparecerem.

## Documentação

- Plano e arquitetura: [`docs/PLAN.md`](docs/PLAN.md)
- Integração (códigos de conteúdo, módulo, privacidade, PHP e JavaScript): [`docs/developers.md`](docs/developers.md)
- API REST: [`docs/api.md`](docs/api.md)
- Contrato do frontend: [`docs/contract.schema.json`](docs/contract.schema.json)
- Estado e notas de cada fase: [`TODO.md`](TODO.md), [`docs/fases/`](docs/fases/)

## Desenvolvimento

```bash
npm ci                     # esbuild (minificação) e ferramentas de teste
python3 build/build.py     # dist/pkg_lcookies-<versão>.zip e dist/pkg_lcookies.xml
```

Os testes alteram dados, por isso só devem correr em sites de teste:
- `tests/e2e_admin.py` testa o backoffice;
- `tests/e2e_api.py` testa a API;
- `tests/e2e_front.mjs` testa o frontend num browser real, com Playwright e axe.

Ver as notas em `docs/fases/`.

## Publicar uma versão

1. Subir `<version>` em `src/pkg_lcookies.xml` (e nos manifests das extensões alteradas; mudança de schema → novo `sql/updates/{mysql,postgresql}/<versão>.sql`).
2. Fazer commit (mensagens no formato [Conventional Commits](https://www.conventionalcommits.org/), ex. `chore(release): 0.5.0`), criar a tag e enviá-la:
   ```bash
   git tag v0.5.0 && git push origin main v0.5.0
   ```
3. A GitHub Action [`release.yml`](.github/workflows/release.yml):
   - confirma que a tag é igual à versão do manifesto;
   - gera o pacote;
   - cria a release com `pkg_lcookies-X.Y.Z.zip` e `pkg_lcookies.xml`.

   Versões com sufixo (`0.5.0-beta1`) ficam como *pre-release* e não são oferecidas aos sites.

O servidor de atualizações do Joomla é `https://github.com/leocostarj22/lcookies/releases/latest/download/pkg_lcookies.xml`:
- o GitHub redireciona sempre para o ficheiro da última release;
- o ficheiro indica a versão, o link do zip e o `sha512`, que o Joomla confirma antes de instalar.

O repositório tem de ser público.

## Autor

Desenvolvido por **leocostadeveloper**:
- site: [www.leocostadeveloper.com](https://www.leocostadeveloper.com);
- LinkedIn: [@leocostadeveloper](https://www.linkedin.com/in/leocostadeveloper);
- GitHub: [@leocostarj22](https://github.com/leocostarj22).

## Licença

GNU General Public License versão 2 ou posterior — ver [`LICENSE.txt`](LICENSE.txt).
