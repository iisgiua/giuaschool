<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace App\Util;

use InvalidArgumentException;

/**
 * ModalRenderer - genera l'HTML di una finestra modale con uno standard unico.
 *
 * L'applicazione usa due basi grafiche diverse (Bootstrap 3 nelle pagine degli
 * utenti, Bootstrap Italia nell'area di amministrazione): il componente emette
 * un solo markup e gestisce da sé le differenze che contano.
 *
 * - chiusura: ogni pulsante che chiude riporta sia data-dismiss (BS3) sia
 *   data-bs-dismiss (Bootstrap Italia), così funziona con entrambe le librerie;
 * - header: titolo (e sottotitolo) a sinistra, X in alto a destra. L'ordine è
 *   imposto dal CSS (.gs-modal-close con order: 2), non dalla posizione nel
 *   markup;
 * - body: sfondo bianco, padding 24px e scorrimento interno quando il
 *   contenuto supera l'altezza della finestra;
 * - footer: generato solo se ci sono pulsanti; linea sottile di separazione,
 *   pulsanti a destra e distanziati. I pulsanti con position "left" finiscono
 *   a sinistra (caso delle modali distruttive).
 *
 * I testi (titolo, sottotitolo, etichette) vengono codificati; il corpo è HTML
 * già pronto e viene inserito così com'è (va costruito da codice fidato).
 *
 * Uso tipico:
 *   $html = $renderer->render([
 *     'id' => 'gs-modal-esempio',
 *     'title' => 'Titolo',
 *     'body' => $corpoHtml,
 *     'scenario' => ModalRenderer::SCENARIO_CONFIRM,
 *   ]);
 *
 * Nei template Twig la stessa cosa si scrive {{ render_modal({...}) }}.
 *
 * @author Antonello Dessì
 */
final class ModalRenderer {

  public const SCENARIO_INFO = 'info';
  public const SCENARIO_CONFIRM = 'confirm';
  public const SCENARIO_DESTRUCTIVE = 'destructive';

  /**
   * Classi dei pulsanti: gli stili valgono sia per Bootstrap 3 sia per
   * Bootstrap Italia (gs-standard.css normalizza btn-default e btn-secondary
   * allo stesso modo).
   */
  private const CLASSI_STILE = [
    'neutral' => 'btn btn-default gs-modal-btn',
    'primary' => 'btn btn-primary gs-modal-btn',
    'danger' => 'btn btn-danger gs-modal-btn',
  ];

  /**
   * Classi delle dimensioni: Bootstrap 3 non conosce modal-xl, la misura è
   * definita in gs-standard.css per i soli .gs-modal.
   */
  private const CLASSI_DIMENSIONE = [
    'sm' => ' modal-sm',
    'md' => '',
    'lg' => ' modal-lg',
    'xl' => ' modal-xl',
  ];

  /**
   * Genera l'HTML completo della modale.
   *
   * @param array $opzioni Opzioni della modale:
   *   id          (obbligatorio) identificativo, usato anche per aria-labelledby;
   *   title       titolo dell'header;
   *   subtitle    riga secondaria sotto il titolo;
   *   body        HTML del corpo (non viene codificato); accettato anche
   *               come bodyContent, il nome usato negli esempi d'uso;
   *   scenario    info (default), confirm o destructive: decide i pulsanti;
   *   buttons     pulsanti espliciti (accettati anche come footerButtons), al
   *               posto di quelli dello scenario; lista di
   *               ['label' => ..., 'style' => neutral|primary|danger,
   *                'dismiss' => bool, 'position' => right|left,
   *                'type' => button|submit (default button),
   *                'attrs' => ['nome' => 'valore', ...]];
   *               con type submit e l'attributo form il pulsante invia il form
   *               scritto nel corpo della modale;
   *   size        sm, md (default), lg o xl;
   *   static      true per impedire la chiusura cliccando fuori (default false);
   *   dismissible false per togliere la X di chiusura (default true);
   *   class       classi aggiuntive sul contenitore .modal.
   *
   * @return string HTML della modale
   */
  public function render(array $opzioni): string {
    // identificativo: solo caratteri sicuri, serve per id e aria-labelledby
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($opzioni['id'] ?? ''));
    if ($id === null || $id === '') {
      throw new InvalidArgumentException('ModalRenderer: manca l\'identificativo della modale.');
    }
    // testi codificati, corpo già in HTML
    $titolo = $this->testo($opzioni['title'] ?? '');
    $sottotitolo = $this->testo($opzioni['subtitle'] ?? '');
    $corpo = (string) ($opzioni['body'] ?? $opzioni['bodyContent'] ?? '');
    // scenario e pulsanti (buttons oppure footerButtons)
    $scenario = (string) ($opzioni['scenario'] ?? self::SCENARIO_INFO);
    if (array_key_exists('buttons', $opzioni)) {
      $bottoni = (array) $opzioni['buttons'];
    } elseif (array_key_exists('footerButtons', $opzioni)) {
      $bottoni = (array) $opzioni['footerButtons'];
    } else {
      $bottoni = $this->bottoniScenario($scenario);
    }
    // dimensioni e comportamenti
    $dimensione = self::CLASSI_DIMENSIONE[(string) ($opzioni['size'] ?? 'md')] ?? '';
    $statica = !empty($opzioni['static']);
    $chiudibile = ($opzioni['dismissible'] ?? true) !== false;
    $classi = $this->testo(trim('modal fade gs-modal '.(string) ($opzioni['class'] ?? '')));
    // attributi del contenitore: la modale bloccante non si chiude da fuori
    $attributi = $statica ? ' data-backdrop="static" data-keyboard="false"' : '';
    // intestazione: titolo a sinistra, X a destra (l'ordine lo impone il CSS)
    $testi = '<div class="gs-modal-titles">'.
      '<h3 class="modal-title" id="'.$id.'-label">'.$titolo.'</h3>'.
      ($sottotitolo !== '' ? '<p class="gs-modal-subtitle">'.$sottotitolo.'</p>' : '').
      '</div>';
    $chiusura = $chiudibile
      ? '<button type="button" class="close gs-modal-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Chiudi">'.
        '<span aria-hidden="true">&times;</span></button>'
      : '';
    // footer: solo se ci sono pulsanti
    $footer = $this->footer($bottoni);
    // markup completo
    return '<div class="'.$classi.'" id="'.$id.'" tabindex="-1" role="dialog" aria-labelledby="'.$id.'-label"'.$attributi.'>'.
      '<div class="modal-dialog'.$dimensione.'" role="document">'.
      '<div class="modal-content">'.
      '<div class="modal-header">'.$testi.$chiusura.'</div>'.
      '<div class="modal-body">'.$corpo.'</div>'.
      $footer.
      '</div></div></div>';
  }

  /**
   * Modale informativa: un solo pulsante "Chiudi" neutro.
   *
   * @param string $id Identificativo della modale
   * @param string $titolo Titolo dell'header
   * @param string $corpo HTML del corpo
   * @param array $opzioni Altre opzioni di render()
   *
   * @return string HTML della modale
   */
  public function renderInfo(string $id, string $titolo, string $corpo, array $opzioni = []): string {
    return $this->render([
      'id' => $id,
      'title' => $titolo,
      'body' => $corpo,
      'scenario' => self::SCENARIO_INFO,
    ] + $opzioni);
  }

  /**
   * Modale di azione/conferma: "Annulla" neutro e "Conferma" primario.
   *
   * @param string $id Identificativo della modale
   * @param string $titolo Titolo dell'header
   * @param string $corpo HTML del corpo
   * @param array $opzioni Altre opzioni di render()
   *
   * @return string HTML della modale
   */
  public function renderConferma(string $id, string $titolo, string $corpo, array $opzioni = []): string {
    return $this->render([
      'id' => $id,
      'title' => $titolo,
      'body' => $corpo,
      'scenario' => self::SCENARIO_CONFIRM,
    ] + $opzioni);
  }

  /**
   * Modale distruttiva/complessa: "Chiudi" a sinistra, "Rimuovi" e "Gestisci" a
   * destra.
   *
   * @param string $id Identificativo della modale
   * @param string $titolo Titolo dell'header
   * @param string $corpo HTML del corpo
   * @param array $opzioni Altre opzioni di render()
   *
   * @return string HTML della modale
   */
  public function renderDistruttiva(string $id, string $titolo, string $corpo, array $opzioni = []): string {
    return $this->render([
      'id' => $id,
      'title' => $titolo,
      'body' => $corpo,
      'scenario' => self::SCENARIO_DESTRUCTIVE,
    ] + $opzioni);
  }

  /**
   * Pulsanti predefiniti dei tre scenari.
   *
   * @param string $scenario Scenario richiesto
   *
   * @return array Lista dei pulsanti
   */
  private function bottoniScenario(string $scenario): array {
    return match ($scenario) {
      self::SCENARIO_CONFIRM => [
        ['label' => 'Annulla', 'style' => 'neutral', 'dismiss' => true],
        ['label' => 'Conferma', 'style' => 'primary'],
      ],
      self::SCENARIO_DESTRUCTIVE => [
        ['label' => 'Chiudi', 'style' => 'neutral', 'dismiss' => true, 'position' => 'left'],
        ['label' => 'Rimuovi', 'style' => 'danger'],
        ['label' => 'Gestisci', 'style' => 'primary'],
      ],
      default => [
        ['label' => 'Chiudi', 'style' => 'neutral', 'dismiss' => true],
      ],
    };
  }

  /**
   * Costruisce il footer, oppure restituisce stringa vuota se non ci sono
   * pulsanti da mostrare.
   *
   * @param array $bottoni Lista dei pulsanti
   *
   * @return string HTML del footer
   */
  private function footer(array $bottoni): string {
    if ($bottoni === []) {
      return '';
    }
    // separa i pulsanti per lato (le modali distruttive mettono "Chiudi" a sinistra)
    $sinistra = '';
    $destra = '';
    foreach ($bottoni as $bottone) {
      $html = $this->bottone((array) $bottone);
      if (($bottone['position'] ?? 'right') === 'left') {
        $sinistra .= $html;
      } else {
        $destra .= $html;
      }
    }
    return '<div class="modal-footer">'.
      ($sinistra !== '' ? '<div class="gs-modal-actions-left">'.$sinistra.'</div>' : '').
      ($destra !== '' ? '<div class="gs-modal-actions-right">'.$destra.'</div>' : '').
      '</div>';
  }

  /**
   * Genera un singolo pulsante del footer.
   *
   * @param array $bottone Dati del pulsante
   *
   * @return string HTML del pulsante
   */
  private function bottone(array $bottone): string {
    $etichetta = $this->testo($bottone['label'] ?? '');
    $stile = (string) ($bottone['style'] ?? 'neutral');
    $classi = self::CLASSI_STILE[$stile] ?? self::CLASSI_STILE['neutral'];
    // chiusura valida per entrambe le librerie
    $chiusura = !empty($bottone['dismiss']) ? ' data-dismiss="modal" data-bs-dismiss="modal"' : '';
    // tipo: button, oppure submit per inviare il form del corpo (attributo form)
    $tipo = (($bottone['type'] ?? 'button') === 'submit') ? 'submit' : 'button';
    // attributi aggiuntivi (form, data-href, data-confirm, id, ...)
    $attributi = '';
    foreach ((array) ($bottone['attrs'] ?? []) as $nome => $valore) {
      $attributi .= ' '.$this->testo($nome).'="'.$this->testo($valore).'"';
    }
    return '<button type="'.$tipo.'" class="'.$classi.'"'.$chiusura.$attributi.'>'.$etichetta.'</button>';
  }

  /**
   * Codifica un testo per l'inserimento in un attributo o in un nodo HTML.
   *
   * @param mixed $valore Testo da codificare
   *
   * @return string Testo sicuro
   */
  private function testo(mixed $valore): string {
    return htmlspecialchars((string) $valore, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

}
