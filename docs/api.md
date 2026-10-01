# LCookies — API Web Services

Rotas registadas pelo plugin **Serviços Web - LCookies** (`plg_webservices_lcookies`), servidas pela API do Joomla (`/api/index.php`) no formato [JSON:API](https://jsonapi.org/).

Base: `https://exemplo.pt/api/index.php/v1/lcookies`

## Autenticação e permissões

- As rotas, exceto `config`, exigem autenticação da API do Joomla. O mais comum é um token: *Utilizadores → Editar → Token da API Joomla*, enviado como `Authorization: Bearer <token>`.
- Por omissão, o plugin "Utilizador - Token da API Joomla" só emite tokens para Super Utilizadores. Para outros grupos, acrescente-os em "Grupos de utilizadores permitidos" e dê-lhes `core.login.api`.
- Permissões do componente (*Componentes → LCookies → Opções → Permissões*):

| Operação | Permissão |
|---|---|
| Ler categorias, serviços e cookies | `core.manage` |
| Criar / editar / apagar | `core.create` / `core.edit` / `core.delete` |
| Ler registos de consentimento | `lcookies.consents.view` |

As escritas usam os mesmos modelos, formulários e tabelas do backend, por isso as regras são as mesmas:
- as categorias de sistema mantêm o alias, ficam obrigatórias e publicadas;
- aliases únicos;
- padrões e expressões regulares validados;
- só se apaga o que está no lixo (`state = -2`);
- uma categoria com serviços não se apaga (409);
- apagar um serviço apaga os cookies dele.

Cada alteração limpa a cache do contrato do frontend.

## Rotas

| Método | Rota | Descrição |
|---|---|---|
| GET | `/categories` | Lista (filtros: `search`, `state`) |
| GET | `/categories/{id}` | Categoria |
| POST | `/categories` | Criar |
| PATCH | `/categories/{id}` | Alterar (só os campos enviados) |
| DELETE | `/categories/{id}` | Apagar (tem de estar no lixo) |
| GET/POST | `/services`, `/services/{id}` | Igual; filtros `search`, `state`, `category` |
| PATCH/DELETE | `/services/{id}` | |
| GET/POST | `/cookies`, `/cookies/{id}` | Igual; filtros `search`, `state`, `service`, `category`, `type`, `source` |
| PATCH/DELETE | `/cookies/{id}` | |
| GET | `/consents` | Registos de consentimento (só leitura); filtros `search` (UUID, `id:N` ou parte do URL), `action`, `category`, `policy_version`, `user` (`guest`/`registered`), `from`/`to` (`AAAA-MM-DD`) |
| GET | `/consents/{id}` | Um registo |
| GET | `/config` | **Público.** Contrato do frontend (`docs/contract.schema.json`); `?language=pt-PT` escolhe o idioma dos textos (idioma instalado no site, senão o idioma por omissão do site); 404 para idiomas desconhecidos |

Parâmetros comuns das listas:
- `filter[...]`;
- `list[ordering]`, só com as colunas permitidas (`a.id`, `a.ordering`, `a.title`, …);
- `list[direction]`: `asc` ou `desc`;
- `page[offset]` e `page[limit]` (paginação JSON:API, com 20 registos por omissão).

`filter[state]` aceita `1`, `0`, `-2` ou `*`; sem ele, a lista mostra os publicados e os despublicados.

## Campos

Os valores são devolvidos como estão guardados. Os dados por omissão usam constantes de idioma, por exemplo `"title": "COM_LCOOKIES_CAT_STATISTICS"`; o texto traduzido está no `config`.

- **categories**: `id`, `alias`, `title`, `description`, `required`, `core`, `gcm_types` (lista de tipos do Consent Mode v2), `state`, `ordering`, datas/autores, `count_services` (lista).
- **services**: `id`, `category_id`, `alias`, `title`, `provider`, `privacy_url`, `description`, `block_patterns` (um padrão por linha), `head_code`, `body_code`, `state`, `ordering`; a lista inclui `category_alias`, `category_title`, `count_cookies`.
- **cookies**: `id`, `service_id`, `name`, `match_type` (`exact`/`prefix`/`regex`), `type` (`cookie`/`local`/`session`/`pixel`), `domain`, `duration_value`, `duration_unit` (`session`/`minute`/`hour`/`day`/`month`/`year`), `description`, `source`, `state`, `ordering`.
- **consents**: `id`, `consent_uuid`, `action` (`accept_all`/`reject_all`/`custom`/`allow`), `categories` (lista de aliases), `policy_version`, `user_id`, `ip_hash`, `ua_hash`, `url`, `language`, `created` (UTC); a lista inclui `user_name`.
- **config**: `id` = idioma; os atributos são o contrato.

## Exemplos

```bash
TOKEN=...   # token da API do utilizador
API=https://exemplo.pt/api/index.php/v1/lcookies

# Contrato (público)
curl "$API/config?language=pt-PT"

# Criar um serviço na categoria 3 (Estatísticas) com bloqueio automático
curl -X POST "$API/services" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"title":"Google Analytics","category_id":3,"provider":"Google","privacy_url":"https://policies.google.com/privacy","block_patterns":"googletagmanager.com\n/gtag\\(/","state":1}'

# Histórico de um consentimento (o UUID está no cookie lcookies_consent do visitante)
curl -g "$API/consents?filter[search]=2f1c7a3e-5b6d-4e8f-9a0b-1c2d3e4f5a6b" -H "Authorization: Bearer $TOKEN"

# Apagar um cookie: primeiro para o lixo, depois apagar
curl -X PATCH "$API/cookies/12" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"state":-2}'
curl -X DELETE "$API/cookies/12" -H "Authorization: Bearer $TOKEN"
```

## Erros

Erros no formato JSON:API (`{"errors":[{"title": "...", "code": ...}]}`):

| Código | Quando |
|---|---|
| 400 | Dados inválidos (mensagem traduzida no idioma do utilizador da API) |
| 401 | Sem autenticação |
| 403 | Sem permissão |
| 404 | Rota ou registo inexistente |
| 409 | Apagar um registo que não está no lixo, uma categoria com serviços ou de sistema |

O registo de consentimentos feito pelo banner não passa por esta API: usa o endpoint do site `index.php?option=com_lcookies&task=consent.save&format=json` (ver `docs/fases/fase-3.md`).
