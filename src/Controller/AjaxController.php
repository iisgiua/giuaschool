<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\Materia;
use App\Entity\Staff;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


/**
 * AjaxController - gestione delle chiamate ajax
 *
 * @author Antonello Dessì
 */
class AjaxController extends BaseController {

  /**
   * Restituisce la lista dei docenti trovata in base alle impostazioni date
   *
   * @param string $cognome Cognome (anche parziale) del docente ("-" iniziale per evitare parametro vuoto)
   * @param string $nome Nome (anche parziale) del docente ("-" iniziale per evitare parametro vuoto)
   * @param string $sede Lista id sedi, separati da "-" ("-" iniziale per evitare parametro vuoto)
   * @param string $pagina Numero della pagina della lista
   *
   * @return JsonResponse Informazioni di risposta
   */
  #[Route(path: '/ajax/docenti/{cognome}/{nome}/{sede}/{pagina}', name: 'ajax_docenti', requirements: ['pagina' => '\d+'], defaults: ['cognome' => '-', 'nome' => '-', 'sede' => '-', 'pagina' => '1'], methods: ['POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function docentiAjax(string $cognome, string $nome, string $sede,
                              string $pagina): JsonResponse {
    // inizializza
    $search = ['cognome' => substr($cognome, 1), 'nome' => substr($nome, 1), 'sede' => []];
    $dati = [];
    // controlla sede
    if ($this->getUser()->getSede()) {
      $search['sede'] = [$this->getUser()->getSede()->getId()];
    } elseif ($sede != '-') {
      // restrizione sulle sedi indicate
      $search['sede'] = explode('-', substr(substr($sede, 1), 0, -1));
    }
    // esegue la ricerca
    $docenti = $this->em->getRepository(Docente::class)->cercaSede($search, $pagina, 20);
    foreach ($docenti as $doc) {
      $dati['lista'][] = [
        'id' => $doc->getId(),
        'nome' => $doc->getCognome().' '.$doc->getNome()];
    }
    // imposta paginazione
    $dati['pagina'] = $pagina;
    $dati['max'] = ceil($docenti->count() / 20);
    if ($dati['max'] <= 10) {
      $dati['inizio'] = 1;
      $dati['fine'] = $dati['max'];
    } elseif ($pagina + 5 <= $dati['max']) {
      $dati['inizio'] = max(1, $pagina - 4);
      $dati['fine'] = $dati['inizio'] + 9;
    } else {
      $dati['fine'] = min($pagina + 5, $dati['max']);
      $dati['inizio'] = $dati['fine'] - 9;
    }
    // restituisce dati
    return new JsonResponse($dati);
  }

  /**
   * Restituisce la lista degli alunni trovata in base alle impostazioni date
   *
   * @param string $cognome Cognome (anche parziale) del docente ("-" iniziale per evitare parametro vuoto)
   * @param string $nome Nome (anche parziale) del docente ("-" iniziale per evitare parametro vuoto)
   * @param string $classe Identificatore della classe degli alunni ("-" iniziale per evitare parametro vuoto)
   * @param string $sede Lista delle sedi
   * @param string $pagina Numero della pagina della lista
   *
   * @return JsonResponse Informazioni di risposta
   */
  #[Route(path: '/ajax/alunni/{cognome}/{nome}/{classe}/{sede}/{pagina}', name: 'ajax_alunni', requirements: ['pagina' => '\d+'], defaults: ['cognome' => '-', 'nome' => '-', 'classe' => '-', 'sede' => '-', 'pagina' => '1'], methods: ['POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function alunniAjax(string $cognome, string $nome, string $classe, string $sede,
                             string $pagina): JsonResponse {
    // inizializza
    $search = ['cognome' => substr($cognome, 1), 'nome' => substr($nome, 1), 'classe' => substr($classe, 1), 'sede' => []];
    $dati = [];
    // controlla sede
    if ($this->getUser() instanceOf Staff && $this->getUser()->getSede()) {
      $search['sede'] = [$this->getUser()->getSede()->getId()];
    } elseif ($sede != '-') {
      // restrizione sulle sedi indicate
      $search['sede'] = explode('-', substr(substr($sede, 1), 0, -1));
    }
    // esegue la ricerca
    $alunni = $this->em->getRepository(Alunno::class)->iscritti($search, $pagina, 20);
    foreach ($alunni as $alu) {
      $dati['lista'][] = [
        'id' => $alu->getId(),
        'nome' => ''.$alu.' '.$alu->getClasse()];
    }
    // imposta paginazione
    $dati['pagina'] = $pagina;
    $dati['max'] = ceil($alunni->count() / 20);
    if ($dati['max'] <= 10) {
      $dati['inizio'] = 1;
      $dati['fine'] = $dati['max'];
    } elseif ($pagina + 5 <= $dati['max']) {
      $dati['inizio'] = max(1, $pagina - 4);
      $dati['fine'] = $dati['inizio'] + 9;
    } else {
      $dati['fine'] = min($pagina + 5, $dati['max']);
      $dati['inizio'] = $dati['fine'] - 9;
    }
    // restituisce dati
    return new JsonResponse($dati);
  }

  /**
   * Restituisce il token per la validazione CSRF
   *
   * @param Request $request Pagina richiesta
   * @param CsrfTokenManagerInterface $tokenManager Gestione dei token CSRF
   * @param LoggerInterface $logger Gestore dei log su file
   * @param string $id Identificativo per il token da generare
   *
   * @return JsonResponse Informazioni di risposta
   */
  // TODO: da rimuovere, non più necessaria con nuova app
  #[Route(path: '/ajax/token/{id}', name: 'ajax_token', requirements: ['id' => 'authenticate'], methods: ['GET'])]
  public function tokenAjax(Request $request, CsrfTokenManagerInterface $tokenManager, LoggerInterface $logger, string $id): JsonResponse {
    // genera token
    $dati = [];
    $dati[$id] = $tokenManager->getToken($id)->getValue();
    // restituisce dati
    return new JsonResponse($dati);
  }

  /**
   * Estende il tempo di scadenza della sessione
   *
   * @return JsonResponse Informazioni di risposta
   *
   */
  #[Route(path: '/ajax/sessione', name: 'ajax_sessione', methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function sessioneAjax(): JsonResponse {
    // restituisce dati
    return new JsonResponse(['ok']);
  }

  /**
   * Restituisce la lista degli alunni della classe indicata
   *
   * @param Classe $classe Classe degli alunni
   *
   * @return JsonResponse Informazioni di risposta
   */
  #[Route(path: '/ajax/classe/{classe}', name: 'ajax_classe', requirements: ['classe' => '\d+'], defaults: ['classe' => 0], methods: ['POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function classeAjax(
                             #[MapEntity] Classe $classe
                             ): JsonResponse {
    // legge alunni
    $dati = $this->em->getRepository(Alunno::class)->classe($classe->getId());
    // restituisce dati
    return new JsonResponse($dati);
  }

  /**
   * Restituisce la lista delle cattedre del docente indicato
   *
   * @param Docente $docente Docente di cui restituire le cattedre
   *
   * @return JsonResponse Informazioni di risposta
   */
  #[Route(path: '/ajax/cattedre/{docente}', name: 'ajax_cattedre', requirements: ['docente' => '\d+'], defaults: ['docente' => 0], methods: ['POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function cattedreAjax(
                               #[MapEntity] Docente $docente
                               ): JsonResponse {
    // legge cattedre
    $dati = $this->em->getRepository(Cattedra::class)->cattedreDocente($docente, 'V');
    // restituisce lista per checkbox
    return new JsonResponse($dati);
  }

  /**
   * Restituisce la lista delle materie della classe indicata
   *
   * @param Classe $classe Classe di riferimento
   *
   * @return JsonResponse Informazioni di risposta
   */
  #[Route(path: '/ajax/materie/{classe}', name: 'ajax_materie', requirements: ['classe' => '\d+'], methods: ['POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function materieAjax(
                              #[MapEntity] Classe $classe
                              ): JsonResponse {
    // solo cattedre attive e normali, no sostegno, no ed.civ.
    $materie = $this->em->getRepository(Materia::class)->materieClasse($classe, true, false, 'V');
    // restituisce dati
    return new JsonResponse($materie);
  }

}
