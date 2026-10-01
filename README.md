# LCookies

Sistema de consentimento de cookies para **Joomla 5.2+ e 6**: banner e preferências acessíveis, bloqueio de scripts e iframes até haver consentimento, Google Consent Mode v2, Global Privacy Control, registo de consentimentos (prova) e API Web Services.

- Plano e arquitetura: [`docs/PLAN.md`](docs/PLAN.md)
- Contrato do frontend: [`docs/contract.schema.json`](docs/contract.schema.json)
- API REST: [`docs/api.md`](docs/api.md)
- Integração (PHP `ConsentHelper`, evento `onLCookiesConsentChange`, API JavaScript): [`docs/developers.md`](docs/developers.md)
- Estado e notas de cada fase: [`TODO.md`](TODO.md), [`docs/fases/`](docs/fases/)

## Instalação

Descarregue `pkg_lcookies-X.Y.Z.zip` da [última release](https://github.com/leocostarj22/lcookies/releases/latest) e instale-o em *Sistema → Instalar → Extensões*. As versões seguintes aparecem em *Sistema → Atualizar → Extensões*.

## Desenvolvimento

```bash
npm ci                     # esbuild (minificação) e ferramentas de teste
python3 build/build.py     # dist/pkg_lcookies-<versão>.zip e dist/pkg_lcookies.xml
```

Testes (só em sites de teste, alteram dados): `tests/e2e_admin.py`, `tests/e2e_api.py`, `tests/e2e_front.mjs`. Ver as notas em `docs/fases/`.

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
