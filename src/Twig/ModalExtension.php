<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace App\Twig;

use App\Util\ModalRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * ModalExtension - fornisce la funzione Twig "render_modal".
 *
 * Stampa l'HTML della modale prodotto da ModalRenderer (src/Util): così i
 * template usano lo stesso componente del codice PHP, senza ricopiare il
 * markup e senza dipendere da Bootstrap 3 o Bootstrap Italia.
 *
 * Uso:
 *   {{ render_modal({
 *        id: 'gs-modal-esempio',
 *        title: 'Titolo',
 *        body: '<p>Testo</p>',
 *        scenario: 'confirm'
 *      }) }}
 *
 * @author Antonello Dessì
 */
class ModalExtension extends AbstractExtension {

  /**
   * Costruttore
   *
   * @param ModalRenderer $renderer Generatore dell'HTML delle modali
   */
  public function __construct(private readonly ModalRenderer $renderer) {
  }

  /**
   * Restituisce le funzioni fornite dall'estensione.
   *
   * @return TwigFunction[] Lista delle funzioni Twig
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('render_modal', $this->renderModal(...), ['is_safe' => ['html']]),
    ];
  }

  /**
   * Stampa l'HTML della modale.
   *
   * @param array $opzioni Opzioni della modale (vedi ModalRenderer::render)
   *
   * @return string HTML della modale
   */
  public function renderModal(array $opzioni): string {
    return $this->renderer->render($opzioni);
  }

}
