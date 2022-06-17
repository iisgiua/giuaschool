<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
Use App\Entity\Staff;
Use App\Entity\Classe;


/**
 * AjaxController - gestione delle chiamate ajax
 */
class AjaxController extends AbstractController {

  /**
   * Restituisce la lista dei docenti trovata in base alle impostazioni date
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param string $cognome Cognome (anche parziale) del docente ("-" iniziale per evitare parametro vuoto)
   * @param string $nome Nome (anche parziale) del docente ("-" iniziale per evitare parametro vuoto)
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
  public function docentiAjaxAction(EntityManagerInterface $em, $cognome, $nome, $sede, $pagina) {
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
    $docenti = $em->getRepository(Docente::class)->cercaSede($search, $pagina, 20);
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param string $cognome Cognome (anche parziale) del docente ("-" iniziale per evitare parametro vuoto)
   * @param string $nome Nome (anche parziale) del docente ("-" iniziale per evitare parametro vuoto)
   * @param int $classe Identificatore della classe degli alunni ("-" iniziale per evitare parametro vuoto)
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
  public function alunniAjaxAction(EntityManagerInterface $em, $cognome, $nome, $classe, $sede, $pagina) {
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
    $alunni = $em->getRepository(Alunno::class)->iscritti($search, $pagina, 20);
    foreach ($alunni as $alu) {
      $dati['lista'][] = array(
        'id' => $alu->getId(),
        'nome' => $alu->getCognome().' '.$alu->getNome().' ('.$alu->getDataNascita()->format('d/m/Y').') '.
          $alu->getClasse()->getAnno().'ª '.$alu->getClasse()->getSezione());
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
   * @param string $id Identificativo per il token da generare
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/ajax/token/{id}", name="ajax_token",
   *    requirements={"id": "authenticate"},
   *    methods={"GET"})
   */
  public function tokenAjaxAction(CsrfTokenManagerInterface $tokenManager, $id) {
    // genera token
    $dati = array();
    $dati[$id] = $tokenManager->getToken($id)->getValue();
    // restituisce dati
    return new JsonResponse($dati);
  }

  /**
   * Estende la il tempo di scadenza della sessione
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/ajax/sessione", name="ajax_sessione",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function sessioneAjaxAction() {
    // restituisce dati
    return new JsonResponse(['ok']);
  }

  /**
   * Restituisce la lista degli alunni della classe indicata
   *
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function classeAjaxAction(EntityManagerInterface $em, Classe $classe) {
    // legge alunni
    $dati = $em->getRepository(Alunno::class)->classe($classe->getId());
    // restituisce dati
    return new JsonResponse($dati);
  }

}
