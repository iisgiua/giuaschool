<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Classe;
use App\Entity\Staff;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;


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
   *
   * @Route("/ajax/docenti/{cognome}/{nome}/{sede}/{pagina}", name="ajax_docenti",
   *    requirements={"pagina": "\d+"},
   *    defaults={"cognome": "-", "nome": "-", "sede": "-", "pagina": "1"},
   *    methods={"POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function docentiAjaxAction(string $cognome, string $nome, string $sede,
                                    string $pagina): JsonResponse {
    // inizializza
    $search = array('cognome' => substr($cognome, 1), 'nome' => substr($nome, 1), 'sede' => array());
    $dati = array();
    // controlla sede
    if ($this->getUser()->getSede()) {
      $search['sede'] = array($this->getUser()->getSede()->getId());
    } elseif ($sede != '-') {
      // restrizione sulle sedi indicate
      $search['sede'] = explode('-', substr(substr($sede, 1), 0, -1));
    }
    // esegue la ricerca
    $docenti = $this->em->getRepository('App\Entity\Docente')->cercaSede($search, $pagina, 20);
    foreach ($docenti as $doc) {
      $dati['lista'][] = array(
        'id' => $doc->getId(),
        'nome' => $doc->getCognome().' '.$doc->getNome());
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
   *
   * @Route("/ajax/alunni/{cognome}/{nome}/{classe}/{sede}/{pagina}", name="ajax_alunni",
   *    requirements={"pagina": "\d+"},
   *    defaults={"cognome": "-", "nome": "-", "classe": "-", "sede": "-", "pagina": "1"},
   *    methods={"POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function alunniAjaxAction(string $cognome, string $nome, string $classe, string $sede,
                                   string $pagina): JsonResponse {
    // inizializza
    $search = array('cognome' => substr($cognome, 1), 'nome' => substr($nome, 1), 'classe' => substr($classe, 1),
      'sede' => array());
    $dati = array();
    // controlla sede
    if ($this->getUser() instanceOf Staff && $this->getUser()->getSede()) {
      $search['sede'] = array($this->getUser()->getSede()->getId());
    } elseif ($sede != '-') {
      // restrizione sulle sedi indicate
      $search['sede'] = explode('-', substr(substr($sede, 1), 0, -1));
    }
    // esegue la ricerca
    $alunni = $this->em->getRepository('App\Entity\Alunno')->iscritti($search, $pagina, 20);
    foreach ($alunni as $alu) {
      $dati['lista'][] = array(
        'id' => $alu->getId(),
        'nome' => ''.$alu.' '.$alu->getClasse());
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
   * @param CsrfTokenManagerInterface $tokenManager Gestione dei token CSRF
   * @param string $id Identificativo per il token da generare
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/ajax/token/{id}", name="ajax_token",
   *    requirements={"id": "authenticate"},
   *    methods={"GET"})
   */
  public function tokenAjaxAction(CsrfTokenManagerInterface $tokenManager, string $id): JsonResponse {
    // genera token
    $dati = array();
    $dati[$id] = $tokenManager->getToken($id)->getValue();
    // restituisce dati
    return new JsonResponse($dati);
  }

  /**
   * Estende il tempo di scadenza della sessione
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/ajax/sessione", name="ajax_sessione",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function sessioneAjaxAction(): JsonResponse {
    // restituisce dati
    return new JsonResponse(['ok']);
  }

  /**
   * Restituisce la lista degli alunni della classe indicata
   *
   * @param Classe $classe Classe degli alunni
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/ajax/classe/{classe}", name="ajax_classe",
   *    requirements={"classe": "\d+"},
   *    defaults={"classe": 0},
   *    methods={"POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function classeAjaxAction(Classe $classe): JsonResponse {
    // legge alunni
    $dati = $this->em->getRepository('App\Entity\Alunno')->classe($classe->getId());
    // restituisce dati
    return new JsonResponse($dati);
  }

}
