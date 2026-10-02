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

## Módulo `mod_lcookies`

O módulo *LCookies - Definições de cookies* mostra:
- o botão que abre as preferências (botão ou link, com texto configurável);
- a escolha atual do visitante, por exemplo "Permite cookies de Estatísticas e Marketing. Escolha feita a 1 de outubro de 2026.";
- um link para a página escolhida nas opções do componente.

O texto da escolha é preenchido no browser por `media/mod_lcookies/js/status.js`, a partir de `window.LCookies`. Muda logo que o visitante altera a escolha. O HTML do módulo é igual para todos os visitantes, por isso pode ir para cache.

Para personalizar o HTML, faz-se um override em `templates/<template>/html/mod_lcookies/default.php`. O override tem de manter `data-lcookies-open` e `data-mod-lcookies-status` (com os textos em `data-*`).

O módulo não aparece enquanto o plugin *Sistema - LCookies* estiver desligado.

## Pedidos de privacidade (`plg_privacy_lcookies`)

O plugin *Privacidade - LCookies* liga os registos de consentimento aos pedidos de *Utilizadores → Privacidade*, que é como o RGPD é tratado no Joomla.

**Que registos abrange:** só as escolhas feitas com sessão iniciada estão ligadas a uma conta (`user_id`). As escolhas feitas como visitante não podem ser associadas a uma pessoa e ficam como estão.

**Exportação:** os registos do utilizador entram no domínio `lcookies_consents`. Cada registo leva o id do consentimento, a ação, as categorias, a versão da política, a página, o idioma, a data em UTC e os hashes do IP e do browser.

**Remoção:** o que acontece depende da opção do plugin.

| Opção | Efeito | Para que serve |
|---|---|---|
| *Desligar da conta* (por omissão) | Os registos ficam guardados, sem `user_id` | Manter a prova de consentimento, que passa a ser só um id aleatório e hashes |
| *Apagar os registos* | Os registos são eliminados | Remover tudo |

## Scanner de cookies

O scanner (*Componentes → LCookies → Scanner de cookies*, permissão *Executar o scanner de cookies*) analisa o site em três passagens:

| Passagem | Como | O que encontra |
|---|---|---|
| Servidor | Pede cada página sem cookies | Cookies criados pelo servidor antes do consentimento (`Set-Cookie`) |
| Browser, sem consentimento | Abre cada página num iframe com `?lcookies_scan=none.<token>` | Cookies novos e pedidos a outros sites antes de o visitante escolher |
| Browser, tudo aceite | Abre cada página num iframe com `?lcookies_scan=all.<token>` | Todos os cookies e chaves de `localStorage`/`sessionStorage` |

**Páginas analisadas:** a página inicial, a página de privacidade, os itens de menu públicos e os endereços extra das opções (separador *Scanner de cookies*).

**Passagens no browser:** só funcionam quando o site e o backend estão no mesmo endereço (mesma origem). Caso contrário, e na tarefa agendada, só corre a passagem do servidor.

**Modo de scan:** o token é assinado com o `secret` do site e vale uma hora. Com um token válido:
- o LCookies ignora a escolha guardada no browser, tanto no JavaScript como no `ConsentHelper`;
- não mostra o banner e não grava nada (cookie, registo ou evento);
- a página não vai para cache.

Templates e extensões não precisam de fazer nada para suportar o scanner. Se uma extensão decide no servidor com o `ConsentHelper`, o scan com tudo aceite também apanha o que ela gera.

**Resultados:** cada item é comparado com os cookies declarados (mesmas regras de nome: exato, prefixo ou expressão regular) e com a biblioteca de serviços.
- Um cookie não declarado tem os botões *Declarar*, que abre o formulário já preenchido com a origem *Scanner*, e *Adicionar o serviço*, quando a biblioteca o conhece.
- É um **problema** um cookie criado antes do consentimento que não pertence a uma categoria obrigatória, ou um pedido a outro site antes do consentimento que não pertence a um serviço obrigatório.

**Tarefa agendada:** a rotina *LCookies - Analisar os cookies do site* (`plg_task_lcookies`) faz a passagem do servidor.
- Quando há problemas ou cookies não declarados, envia o template de e-mail `plg_task_lcookies.scan` (editável em *Sistema → Templates de e-mail*).
- O e-mail vai para os endereços da tarefa ou, se a tarefa não tiver endereços, para os Super Utilizadores que recebem e-mails do sistema.
- Se a tarefa correr pela linha de comandos, defina `$live_site` no `configuration.php` para os endereços das páginas ficarem certos.

## Pré-visualização nas opções

Os separadores *Aparência* e *Textos* das opções mostram uma pré-visualização do banner e das preferências. A cada alteração ao formulário, o `media/com_lcookies/js/preview.js` envia as opções ainda por guardar para `task=preview.render`.

Essa tarefa (`Administrator\Controller\PreviewController`, permissão *Opções* ou *Administrar*) devolve uma página com:
- os layouts do `plg_system_lcookies`, incluindo os overrides do template predefinido do site;
- o CSS e o JavaScript do frontend, em modo de pré-visualização (`preview: true`);
- os textos no idioma predefinido do site.

No modo de pré-visualização não se lê nem grava a escolha, não se envia registo e nenhum serviço é executado. As opções só ficam guardadas quando se carrega em *Guardar*.

A página aparece num `iframe` com `srcdoc`, por isso fica sujeita à política de segurança de conteúdo (CSP) do backend. Se o plugin *Sistema - Cabeçalhos HTTP* tiver uma CSP sem `'unsafe-inline'` para scripts, a pré-visualização fica em branco.

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
