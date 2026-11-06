<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Util\ConfigLoader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * ErrorController - pagina di errore generica del registro elettronico
 *
 * Sostituisce il controller predefinito del framework: le richieste non
 * soddisfatte (404) vengono gestite con la pagina dedicata 'error/404.html.twig',
 * mentre tutti gli altri errori sono affidati al renderer predefinito del
 * framework, che mostra le pagine di errore del progetto.
 *
 * @author Antonello Dessì
 */
class ErrorController extends AbstractController {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param ErrorRendererInterface $errorRenderer Renderer predefinito del framework
   * @param TranslatorInterface $translator Traduttore dei messaggi
   * @param ConfigLoader $config Gestore della configurazione su database
   */
  public function __construct(
    protected ErrorRendererInterface $errorRenderer,
    protected TranslatorInterface $translator,
    protected ConfigLoader $config)
  {
  }

  /**
   * Gestisce l'eccezione sollevata durante la gestione della richiesta.
   *
   * @param \Throwable $exception Eccezione da gestire
   *
   * @return Response Pagina di risposta
   */
  public function __invoke(\Throwable $exception): Response {
    // appiattisce l'eccezione per ricavarne lo stato HTTP
    $flattened = FlattenException::createFromThrowable($exception);
    // errori diversi da 'non trovato': delega al renderer predefinito del framework
    if ($flattened->getStatusCode() !== Response::HTTP_NOT_FOUND) {
      $rendered = $this->errorRenderer->render($exception);
      return new Response($rendered->getAsString(), $rendered->getStatusCode(), $rendered->getHeaders());
    }
    // carica la configurazione di sistema solo se assente (necessaria per l'intestazione
    // delle pagine): nelle sotto-richieste di routing l'utente non è ancora disponibile,
    // quindi un ricaricamento sovrascriverebbe tema e menu della sessione autenticata
    try {
      $this->config->caricaSeNecessario();
    } catch (\Throwable $e) {
      // la configurazione non e' disponibile: la pagina viene mostrata senza intestazione
    }
    // usa il messaggio dell'errore solo se corrisponde ad un messaggio del progetto
    // (le chiavi di traduzione sono le uniche ammesse, per non esporre dettagli tecnici)
    $messaggio = null;
    $testo = $flattened->getMessage();
    if ($testo !== '' && !str_contains($testo, '{')) {
      // i messaggi con segnaposto non sono traducibili e vengono ignorati
      $traduzione = $this->translator->trans($testo);
      $messaggio = ($traduzione !== $testo) ? $traduzione : null;
    }
    // visualizza pagina di errore
    return $this->render('error/404.html.twig', [
      'pagina_titolo' => 'page.error.404',
      'titolo' => 'title.error.404',
      'messaggio' => $messaggio,
      'autenticato' => $this->isGranted('IS_AUTHENTICATED_FULLY'),
      'rotta_principale' => $this->isGranted('ROLE_UTENTE') ? 'login_home' : 'login_form'],
      new Response('', $flattened->getStatusCode(), $flattened->getHeaders()));
  }

}