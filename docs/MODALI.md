# Modali — componente unico

L'applicazione convive con due basi grafiche: Bootstrap 3 nelle pagine degli
utenti e Bootstrap Italia (Bootstrap 4) nell'area di amministrazione. Le modali
scritte nei vari periodi si comportavano in modo diverso: X a sinistra o a
destra, header con o senza bordo, footer con margini diversi, bottoni di
dimensioni diverse, sfondi colorati nel corpo.

Da oggi l'HTML delle modali si genera con un solo componente:

| file | cosa fa |
|---|---|
| `src/Util/ModalRenderer.php` | costruisce l'HTML della modale |
| `src/Twig/ModalExtension.php` | espone la funzione Twig `render_modal` |
| `public/css/gs-standard.css` §8 | regole che impongono la resa nei due layout |

Il componente emette un markup unico e mette su ogni elemento che chiude la
modale **sia** `data-dismiss` (Bootstrap 3) **sia** `data-bs-dismiss`
(Bootstrap Italia), quindi la chiusura funziona in entrambe le aree.

## Regole garantite

- **Header**: titolo (e sottotitolo) a sinistra, X in alto a destra, linea
  sottile di separazione. L'ordine è imposto dal CSS (`.close { order: 2 }`),
  quindi non dipende dalla posizione nel markup.
- **Body**: sfondo bianco, padding 24px (16px sotto i 768px) e scorrimento
  interno quando il contenuto supera l'altezza della finestra. La modale non
  esce mai dallo schermo: header e footer restano sempre visibili.
- **Footer**: generato solo se ci sono pulsanti, linea sottile sopra, pulsanti
  a destra con 12px di spazio. I pulsanti con `position: left` vanno a sinistra
  (modali distruttive).
- **Pulsanti**: `neutral` = `btn btn-default`, `primary` = `btn btn-primary`,
  `danger` = `btn btn-danger`; altezza 40px, testo 14px, raggio 8px in entrambi
  i layout.

## API

```php
public function render(array $opzioni): string
public function renderInfo(string $id, string $titolo, string $corpo, array $opzioni = []): string
public function renderConferma(string $id, string $titolo, string $corpo, array $opzioni = []): string
public function renderDistruttiva(string $id, string $titolo, string $corpo, array $opzioni = []): string
```

Opzioni di `render()`:

| chiave | significato |
|---|---|
| `id` | identificativo (obbligatorio), usato per `id` e `aria-labelledby` |
| `title` | titolo dell'header (codificato) |
| `subtitle` | riga secondaria sotto il titolo (codificata) |
| `body` | HTML del corpo, inserito così com'è (non codificato). Accettato anche `bodyContent` |
| `scenario` | `info` (default), `confirm`, `destructive`: sceglie i pulsanti |
| `buttons` | pulsanti espliciti al posto di quelli dello scenario. Accettato anche `footerButtons` |
| `size` | `sm`, `md` (default), `lg`, `xl` |
| `static` | `true` impedisce la chiusura cliccando fuori |
| `dismissible` | `false` toglie la X (modali bloccanti) |
| `class` | classi aggiuntive sul contenitore |

Ogni pulsante accetta `label`, `style` (`neutral`/`primary`/`danger`),
`dismiss` (chiude la modale), `position` (`right`/`left`), `type`
(`button`/`submit`) e `attrs` (attributi aggiuntivi, es. `form`, `data-href`).

Pulsanti predefiniti:

| scenario | pulsanti |
|---|---|
| `info` | Chiudi (neutro) |
| `confirm` | Annulla (neutro, chiude), Conferma (primario) |
| `destructive` | Chiudi (neutro, a sinistra), Rimuovi (rosso), Gestisci (blu) |

### Apertura e chiusura

```html
<!-- normale, con Bootstrap 3 e Bootstrap Italia -->
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#gs-modal-prova">
  Apri
</button>
```

```js
// da codice: jQuery vale in entrambi i layout
$('#gs-modal-prova').modal('show');
```

## Esempio 1 — modale informativa

```php
<?php
$renderer = new \App\Util\ModalRenderer();

ob_start();
?>
<p>La domanda è stata registrata il <strong>12/03/2026</strong>.</p>
<ul class="gs-mb-0">
  <li>Stato: in attesa di risposta</li>
  <li>Destinatario: coordinatore di classe</li>
</ul>
<?php
$corpo = ob_get_clean();

echo $renderer->renderInfo('gs-modal-info-richiesta', 'Richiesta inviata', $corpo);
```

In Twig:

```twig
{{ render_modal({
     id: 'gs-modal-info-richiesta',
     title: 'Richiesta inviata',
     body: '<p>La domanda è stata registrata il <strong>12/03/2026</strong>.</p>',
     scenario: 'info'
   }) }}
```

## Esempio 2 — modale di conferma con form

```php
<?php
$renderer = new \App\Util\ModalRenderer();
// token CSRF generato dall'applicazione (nel controller)
$csrf = $this->container->get('security.csrf.token_manager')->getToken('delete');

ob_start();
?>
<p>Vuoi archiviare la circolare <strong>n. 214</strong>? L'operazione non è
reversibile.</p>
<form id="form-archivia" method="post" action="<?= $this->generateUrl('circolari_archivia', ['id' => $id]) ?>">
  <input type="hidden" name="_csrf_token" value="<?= $csrf->getValue() ?>">
</form>
<?php
$corpo = ob_get_clean();

echo $renderer->render([
  'id' => 'gs-modal-archivia',
  'title' => 'Archivia circolare',
  'subtitle' => 'Circolare n. 214 del 12/03/2026',
  'body' => $corpo,
  'buttons' => [
    ['label' => 'Annulla', 'style' => 'neutral', 'dismiss' => true],
    ['label' => 'Conferma', 'style' => 'primary', 'type' => 'submit', 'attrs' => ['form' => 'form-archivia']],
  ],
]);
```

Con lo scenario predefinito i due pulsanti si ottengono senza elencarli:

```php
echo $renderer->renderConferma('gs-modal-archivia', 'Archivia circolare', $corpo);
```

## Esempio 3 — modale complessa (Chiudi a sinistra, Rimuovi e Gestisci a destra)

```php
<?php
$renderer = new \App\Util\ModalRenderer();
$csrf = $this->container->get('security.csrf.token_manager')->getToken('delete');

ob_start();
?>
<p>La nota disciplinare è collegata a due provvedimenti:</p>
<ul class="gs-mb-0">
  <li>ammonizione scritta del 12/02/2026</li>
  <li>sospensione dalle lezioni del 19/02/2026</li>
</ul>
<form id="form-rimuovi-nota" method="post" action="<?= $this->generateUrl('note_rimuovi', ['id' => $id]) ?>">
  <input type="hidden" name="_csrf_token" value="<?= $csrf->getValue() ?>">
</form>
<?php
$corpo = ob_get_clean();

echo $renderer->render([
  'id' => 'gs-modal-nota',
  'title' => 'Gestione della nota',
  'subtitle' => 'Classe 1ª A — registro del 12/03/2026',
  'body' => $corpo,
  'buttons' => [
    ['label' => 'Chiudi', 'style' => 'neutral', 'dismiss' => true, 'position' => 'left'],
    ['label' => 'Rimuovi', 'style' => 'danger', 'type' => 'submit', 'attrs' => ['form' => 'form-rimuovi-nota']],
    ['label' => 'Gestisci', 'style' => 'primary', 'attrs' => ['data-href' => $this->generateUrl('note_gestisci', ['id' => $id])]],
  ],
]);
```

Lo stesso risultato con lo scenario predefinito:

```php
echo $renderer->renderDistruttiva('gs-modal-nota', 'Gestione della nota', $corpo, [
  'subtitle' => 'Classe 1ª A — registro del 12/03/2026',
]);
```

## Note

- I testi passati all'helper (titolo, sottotitolo, etichette, attributi) sono
  codificati; il corpo è HTML già pronto e va costruito da codice fidato.
- Il componente aggiunge la classe `gs-modal` al contenitore: le regole
  specifiche (larghezze `sm`/`lg`/`xl`, corpo scorrevole) vivono in
  `public/css/gs-standard.css` §8 e valgono nei due layout.
- Le modali storiche (`templates/include/modal-*.twig`,
  `templates/admin/include/modal-*.twig`) restano funzionanti e hanno già la
  stessa resa di header, corpo, footer e pulsanti; vanno sostituite con
  `render_modal` quando si tocca la pagina che le usa.
