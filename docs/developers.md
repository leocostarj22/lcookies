# LCookies para programadores

Como ligar outras extensões e templates ao consentimento do visitante. Para a API REST ver [`api.md`](api.md).

## Página da política de cookies (`plg_content_lcookies`)

O plugin *Conteúdo - LCookies* substitui estes códigos em artigos, módulos *Personalizado* (com "Preparar conteúdo" ativo) e noutros conteúdos:

| Código | Resultado |
|---|---|
| `{lcookies-table}` | tabela com as categorias, os serviços e os cookies, sempre atualizada |
| `{lcookies-table statistics,marketing}` | só estas categorias (aliases) |
| `{lcookies-settings}` | botão que abre as preferências de cookies |
| `{lcookies-settings Alterar a minha escolha}` | o mesmo botão com outro texto |

- **Cache:** o HTML é igual para todos os visitantes, por isso a página pode ir para cache.
- **Aspeto:** usa as classes do template (Bootstrap no Cassiopeia).
- **Nível dos títulos:** define-se nas opções do plugin.
- **Personalizar o HTML:** override em `templates/<template>/html/layouts/lcookies/policy.php` e `settings.php`.

## Regra de ouro: cache

A forma mais segura é deixar o código sempre no HTML e deixar o LCookies bloqueá-lo:
- o HTML fica igual para todos os visitantes, por isso pode ir para cache (página, módulos, CDN);
- o JavaScript do LCookies só desbloqueia o código quando o visitante dá consentimento.

| Caso | Como fazer |
|---|---|
| Script de um serviço conhecido | Padrões de bloqueio do serviço (*Componentes → LCookies → Serviços*) |
| Script próprio | `<script type="text/plain" data-lcookies-category="statistics">…</script>` |
| Iframe | `<iframe data-lcookies-category="marketing" data-lcookies-src="https://…">` |

O `ConsentHelper` (abaixo) responde no servidor, por isso a resposta depende do visitante. Só deve ser usado em output que **não** vai para cache: o plugin *Sistema - Cache de página* e a cache de módulos/componentes guardariam a resposta do primeiro visitante para todos.

## PHP: `ConsentHelper`

```php
use Lcsilva\Component\Lcookies\Administrator\Helper\ConsentHelper;

if (ConsentHelper::has('marketing')) {
    // o visitante aceitou a categoria "marketing"
}

ConsentHelper::granted(); // ['necessary', 'statistics'] — categorias aceites, obrigatórias incluídas
ConsentHelper::get();     // ['id' => '…', 'v' => 1, 'cats' => [...], 'ts' => 1767225600] ou null
```

Usa as mesmas regras que `window.LCookies.hasConsent()` no browser:
- **Categorias obrigatórias** (ex. `necessary`): sempre aceites.
- **Lê o cookie** `lcookies_consent`.
- **Escolha ignorada:** quando é de uma versão anterior da política ou já passou o prazo de validade das opções.
- **Categorias desconhecidas ou despublicadas:** `false`.

O resultado fica guardado durante o pedido. Se mudares as opções nesse mesmo pedido, chama `ConsentHelper::reset()`.

## PHP: evento `onLCookiesConsentChange`

**Quando é disparado:** cada vez que o banner grava uma escolha, através do endpoint `task=consent.save`. O registo de consentimentos tem de estar ligado.

**Quem o recebe:** os plugins dos grupos `system` e `lcookies`.

**Classe do evento:** `Lcsilva\Component\Lcookies\Administrator\Event\ConsentChangeEvent`.

```php
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Lcsilva\Component\Lcookies\Administrator\Event\ConsentChangeEvent;

final class Exemplo extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ConsentChangeEvent::NAME => 'onConsentChange'];
    }

    public function onConsentChange(ConsentChangeEvent $event): void
    {
        if (\in_array('marketing', $event->getRevoked(), true)) {
            // ex.: apagar dados de marketing deste utilizador ($event->getUserId())
        }
    }
}
```

| Método | Valor |
|---|---|
| `getConsentId()` | id do consentimento (UUID v4), o mesmo em todas as escolhas do visitante |
| `getAction()` | `accept_all`, `reject_all`, `custom` ou `allow` (botão de um placeholder) |
| `getCategories()` | categorias aceites agora, obrigatórias incluídas |
| `getPrevious()` | categorias da escolha anterior com o mesmo id; `null` na primeira escolha |
| `getGranted()` / `getRevoked()` | categorias que passaram a ser aceites / que deixaram de o ser |
| `getPolicyVersion()` | versão da política |
| `getUserId()` | utilizador com sessão iniciada, `0` para visitantes |

O pedido é feito pelo browser em segundo plano (`fetch` com `keepalive`). Por isso o plugin deve fazer pouco trabalho e não deve enviar output nem redirecionar.

## JavaScript: `window.LCookies`

| Membro | Uso |
|---|---|
| `hasConsent(categoria)` | `true`/`false` |
| `getConsent()` | `{id, v, cats, ts}` ou `null` |
| `open()` | abre as preferências |
| `acceptAll()` / `rejectAll()` | grava a escolha |
| `save(['statistics'])` | grava só estas categorias opcionais |
| `allow('marketing')` | acrescenta uma categoria à escolha atual (como o botão de um placeholder) |
| evento `lcookies:ready` | disparado em `document` quando a API está pronta |
| evento `lcookies:change` | disparado em `document` a cada escolha; `detail`: `{action, consent, granted, revoked}` |

Qualquer elemento com `data-lcookies-open` ou um link para `#lcookies-settings` também abre as preferências.

```js
document.addEventListener('lcookies:change', (event) => {
  if (event.detail.granted.includes('statistics')) {
    // carregar algo que dependa de estatísticas
  }
});
```
