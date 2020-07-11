<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Util\BachecaUtil;


/**
 * BachecaController - gestione della bacheca
 */
class BachecaController extends AbstractController {

  /**
   * Visualizza gli avvisi destinati ai docenti
   *
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/{pagina}", name="bacheca_avvisi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisiAction(SessionInterface $session, BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    $limite = 15;
    // recupera criteri dalla sessione
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/bacheca_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/bacheca_avvisi/pagina', $pagina);
    }
    // recupera dati
    $ultimo_accesso = \DateTime::createFromFormat('d/m/Y H:i:s',
      ($session->get('/APP/UTENTE/ultimo_accesso') ? $session->get('/APP/UTENTE/ultimo_accesso') : '01/01/2018 00:00:00'));
    $dati = $bac->bachecaAvvisi($pagina, $limite, $this->getUser(), $ultimo_accesso);
    // mostra la pagina di risposta
    return $this->render('bacheca/avvisi.html.twig', array(
      'pagina_titolo' => 'page.bacheca_avvisi',
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i dettagli di un avviso destinato al docente
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/dettagli/{id}", name="bacheca_avvisi_dettagli",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisoDettagliAction(EntityManagerInterface $em, BachecaUtil $bac, $id) {
    // inizializza
    $dati = null;
    $letto = null;
    // controllo avviso
    $avviso = $em->getRepository('App:Avviso')->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if (!$bac->destinatario($avviso, $this->getUser(), $letto)) {
      // errore: non è destinatario dell'avviso
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->dettagliAvviso($avviso);
    // visualizza pagina
    return $this->render('bacheca/scheda_avviso.html.twig', array(
      'dati' => $dati,
      'lettoCoord' => $letto,
    ));
  }

  /**
   * Mostra gli avvisi destinati agli alunni della classe
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $classe ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/alunni/{classe}", name="bacheca_avvisi_alunni",
   *    requirements={"classe": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisiAlunniAction(EntityManagerInterface $em, BachecaUtil $bac, $classe) {
    // inizializza
    $dati = null;
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->bachecaAvvisiAlunni($classe);
    // visualizza pagina
    return $this->render('bacheca/scheda_avviso_alunni.html.twig', array(
      'dati' => $dati,
      'classe' => $classe,
    ));
  }

  /**
   * Conferma la lettura dell'avviso destinato agli alunni della classe
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $classe ID della classe
   * @param mixed $id ID dell'avviso o "ALL" per tutti gli avvisi della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/alunni/firma/{classe}/{id}", name="bacheca_avvisi_alunni_firma",
   *    requirements={"classe": "\d+", "id": "\d+|ALL"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisiAlunniFirmaAction(EntityManagerInterface $em, BachecaUtil $bac, $classe, $id) {
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // aggiorna firma
    $bac->letturaAvvisoAlunni($classe, $id);
    // ok: memorizza dati
    $em->flush();
    // redirect
    return $this->redirectToRoute('lezioni');
  }

  /**
   * Conferma la lettura dell'avviso destinato ai coordinatori
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/coordinatori/firma/{id}", name="bacheca_avvisi_coordinatori_firma",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisiCoordinatoriFirmaAction(EntityManagerInterface $em, BachecaUtil $bac, $id) {
    $letto = null;
    // controllo avviso
    $avviso = $em->getRepository('App:Avviso')->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if (!$bac->destinatario($avviso, $this->getUser(), $letto)) {
      // errore: non è destinatario dell'avviso
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // aggiorna firma
    if ($avviso->getDestinatariCoordinatori() && !$letto) {
      $bac->letturaAvvisoCoordinatori($avviso, $this->getUser());
      // ok: memorizza dati
      $em->flush();
    }
    // redirect
    return $this->redirectToRoute('bacheca_avvisi');
  }

}
