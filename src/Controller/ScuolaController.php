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

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\FormError;
use App\Entity\DefinizioneScrutinio;
use App\Entity\Preside;
use App\Entity\Istituto;
use App\Entity\Sede;
use App\Entity\Corso;
use App\Entity\Materia;
use App\Entity\Classe;
use App\Entity\Festivita;
use App\Entity\Orario;
use App\Entity\ScansioneOraria;
use App\Entity\Amministratore;
use App\Entity\Configurazione;
use App\Entity\Scrutinio;
use App\Form\DefinizioneScrutinioType;
use App\Form\AmministratoreType;
use App\Form\PresideType;
use App\Form\IstitutoType;
use App\Form\SedeType;
use App\Form\CorsoType;
use App\Form\MateriaType;
use App\Form\ClasseType;
use App\Form\FestivitaType;
use App\Form\OrarioType;
use App\Form\ScansioneOrariaSettimanaleType;


/**
 * ScuolaController - gestione dei dati della scuola
 */
class ScuolaController extends BaseController {

  /**
   * Gestisce la modifica dei dati degli scrutini
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param string $periodo Periodo dello scrutinio
   *
   * @Route("/scuola/scrutini/{periodo}", name="scuola_scrutini",
   *    requirements={"periodo": "P|S|F|E|U|X"},
   *    defaults={"periodo": ""},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function scrutiniAction(Request $request, EntityManagerInterface $em,
                                 SessionInterface $session, TranslatorInterface $trans,
                                 $periodo): Response {
    // init
    $dati = [];
    $info = [];
    // lista periodi scrutinio
    $info['listaPeriodi'] = $em->getRepository('App\Entity\Configurazione')->infoScrutini();
    $info['listaPeriodi']['E'] = $trans->trans('label.scrutini_periodo_E');
    $info['listaPeriodi']['U'] = $trans->trans('label.scrutini_periodo_U');
    // periodo predefinito
    if (empty($periodo)) {
      // ultimo periodo configurato
      $periodo = $em->getRepository('App\Entity\DefinizioneScrutinio')->ultimo();
    }
    $info['periodo'] = $periodo;
    // legge dati
    $definizione = $em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo($periodo);
    if ($definizione) {
      // controlla dati mancanti
      $argomenti[1] = $trans->trans('label.verbale_scrutinio_'.$periodo,
        ['periodo' => ($periodo == 'P' ? $session->get('/CONFIG/SCUOLA/periodo1_nome') :
        ($periodo == 'S' ? $session->get('/CONFIG/SCUOLA/periodo2_nome') : ''))]);
      $argomenti[2] = $trans->trans('label.verbale_situazioni_particolari');
      $struttura[1] = ['ScrutinioInizio', false, []];
      $struttura[2] = ['ScrutinioSvolgimento', false, ['sezione' => 'Punto primo', 'argomento' => 1]];
      $struttura[3] = ['Argomento', true, ['sezione' => 'Punto secondo', 'argomento' => 2,
        'obbligatorio' => false, 'inizio' => '', 'seVuoto' => '', 'default' => '', 'fine' => '']];
      $struttura[4] = ['ScrutinioFine', false, []];
      $definizione
        ->setArgomenti($argomenti)
        ->setStruttura($struttura);
    } else {
      // nuova definizione
      $argomenti[1] = $trans->trans('label.verbale_scrutinio_'.$periodo,
        ['periodo' => ($periodo == 'P' ? $session->get('/CONFIG/SCUOLA/periodo1_nome') :
        ($periodo == 'S' ? $session->get('/CONFIG/SCUOLA/periodo2_nome') : ''))]);
      $argomenti[2] = $trans->trans('label.verbale_situazioni_particolari');
      $struttura[1] = ['ScrutinioInizio', false, []];
      $struttura[2] = ['ScrutinioSvolgimento', false, ['sezione' => 'Punto primo', 'argomento' => 1]];
      $struttura[3] = ['Argomento', true, ['sezione' => 'Punto secondo', 'argomento' => 2,
        'obbligatorio' => false, 'inizio' => '', 'seVuoto' => '', 'default' => '', 'fine' => '']];
      $struttura[4] = ['ScrutinioFine', false, []];
      $definizione = (new DefinizioneScrutinio())
        ->setData(new \DateTime('today'))
        ->setDataProposte(new \DateTime('today'))
        ->setPeriodo($periodo)
        ->setArgomenti($argomenti)
        ->setStruttura($struttura);
      $em->persist($definizione);
    }
    // form
    $form = $this->createForm(DefinizioneScrutinioType::class, $definizione,
      ['returnUrl' => $this->generateUrl('scuola_scrutini'), 'dati' => $definizione->getClassiVisibili()]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // classi visibili
      $classiVisibili = $definizione->getClassiVisibili();
      for ($cl = 1; $cl <= 5; $cl++) {
        if ($classiVisibili[$cl] && ($ora = $form->get('classiVisibiliOra'.$cl)->getData())) {
          // aggiunge ora
          $classiVisibili[$cl]->setTime($ora->format('H'), $ora->format('i'));
        }
      }
      $definizione->setClassiVisibili($classiVisibili);
      // aggiorna classi visibili di scrutini
      $subquery = $em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.anno=:anno')
        ->getDQL();
      for ($cl = 1; $cl <= 5; $cl++) {
        $risultato = $em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
          ->update()
          ->set('s.modificato', ':modificato')
          ->set('s.visibile', ':visibile')
          ->where('s.periodo=:periodo AND s.classe IN ('.$subquery.')')
          ->setParameters(['modificato' => new \DateTime(), 'visibile' => $classiVisibili[$cl],
            'periodo' => $periodo, 'anno' => $cl])
          ->getQuery()
          ->getResult();
      }
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'scrutini', $dati, $info, [$form->createView(), 'message.definizione_scrutinio']);
  }

  /**
   * Modifica dei dati dell'amministratore (un solo utente possibile)
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/amministratore", name="scuola_amministratore",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function amministratoreAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $amministratore = $em->getRepository('App\Entity\Amministratore')->findOneBy([]);
    if (!$amministratore) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // form
    $form = $this->createForm(AmministratoreType::class, $amministratore,
      ['returnUrl' => $this->generateUrl('scuola_amministratore')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'amministratore', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Modifica dei dati del Dirigente Scolastico (un solo utente possibile)
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/dirigente", name="scuola_dirigente",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function dirigenteAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $preside = $em->getRepository('App\Entity\Preside')->findOneBy([]);
    if (!$preside) {
      // crea nuovo utente
      $preside = (new Preside())
        ->setAbilitato(true);
      $em->persist($preside);
    }
    // assicura che l'utente sia abilitato
    $preside->setAbilitato(true);
    // form
    $form = $this->createForm(PresideType::class, $preside, ['returnUrl' => $this->generateUrl('scuola_dirigente')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'dirigente', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Modifica dei dati dell'istituto (un solo istituto possibile)
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/istituto", name="scuola_istituto",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function istitutoAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $istituto = $em->getRepository('App\Entity\Istituto')->findOneBy([]);
    if (!$istituto) {
      // crea nuovo utente
      $istituto = new Istituto();
      $em->persist($istituto);
    }
    // form
    $form = $this->createForm(IstitutoType::class, $istituto, ['returnUrl' => $this->generateUrl('scuola_istituto')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'istituto', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Modifica dei dati delle sedi scolastiche
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/sedi", name="scuola_sedi",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function sediAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // recupera dati
    $dati = $em->getRepository('App\Entity\Sede')->findBY([], ['ordinamento' => 'ASC']);
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'sedi', $dati, $info);
  }

  /**
   * Modifica dei dati di una sede scolastica
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/sedi/edit/{id}", name="scuola_sedi_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function sediEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $sede = $em->getRepository('App\Entity\Sede')->find($id);
      if (!$sede) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $sede = new Sede();
      $em->persist($sede);
    }
    // form
    $form = $this->createForm(SedeType::class, $sede, ['returnUrl' => $this->generateUrl('scuola_sedi')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('scuola_sedi');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'sedi_edit', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Cancella una sede scolastica
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/sedi/delete/{id}", name="scuola_sedi_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function sediDeleteAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla sede
    $sede = $em->getRepository('App\Entity\Sede')->find($id);
    if (!$sede) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella sede
      $em->remove($sede);
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception $e) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_sedi');
  }

  /**
   * Modifica dei dati dei corsi scolastici
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/corsi", name="scuola_corsi",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function corsiAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // recupera dati
    $dati = $em->getRepository('App\Entity\Corso')->findBY([], ['nome' => 'ASC']);
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'corsi', $dati, $info);
  }

  /**
   * Modifica dei dati di un corso scolastico
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/corsi/edit/{id}", name="scuola_corsi_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function corsiEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $corso = $em->getRepository('App\Entity\Corso')->find($id);
      if (!$corso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $corso = new Corso();
      $em->persist($corso);
    }
    // form
    $form = $this->createForm(CorsoType::class, $corso, ['returnUrl' => $this->generateUrl('scuola_corsi')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('scuola_corsi');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'corsi_edit', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Cancella un corso scolastico
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/corsi/delete/{id}", name="scuola_corsi_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function corsiDeleteAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla corso
    $corso = $em->getRepository('App\Entity\Corso')->find($id);
    if (!$corso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella corso
      $em->remove($corso);
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception $e) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_corsi');
  }

  /**
   * Modifica dei dati delle materie scolastiche
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/materie", name="scuola_materie",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function materieAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // recupera dati
    $dati = $em->getRepository('App\Entity\Materia')->findBY([], ['ordinamento' => 'ASC', 'nome' => 'ASC']);
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'materie', $dati, $info);
  }

  /**
   * Modifica dati di una materia scolastica
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/materie/edit/{id}", name="scuola_materie_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function materieEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $materia = $em->getRepository('App\Entity\Materia')->find($id);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $materia = new Materia();
      $em->persist($materia);
    }
    // form
    $form = $this->createForm(MateriaType::class, $materia, ['returnUrl' => $this->generateUrl('scuola_materie')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('scuola_materie');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'materie_edit', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Cancella una materia scolastica
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/materie/delete/{id}", name="scuola_materie_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function materieDeleteAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla materia
    $materia = $em->getRepository('App\Entity\Materia')->find($id);
    if (!$materia) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella materia
      $em->remove($materia);
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception $e) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_materie');
  }

  /**
   * Modifica dei dati delle classi
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/classi/{pagina}", name="scuola_classi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function classiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                               $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera pagina di visualizzazione
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/scuola_classi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/scuola_classi/pagina', $pagina);
    }
    // recupera dati
    $dati = $em->getRepository('App\Entity\Classe')->cerca($pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'classi', $dati, $info);
  }

  /**
   * Modifica dati di una classe
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/classi/edit/{id}", name="scuola_classi_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function classiEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $classe = $em->getRepository('App\Entity\Classe')->find($id);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $classe = new Classe();
      $em->persist($classe);
    }
    // form
    $form = $this->createForm(ClasseType::class, $classe, ['returnUrl' => $this->generateUrl('scuola_classi')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('scuola_classi');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'classi_edit', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Cancella una classe
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/classi/delete/{id}", name="scuola_classi_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function classiDeleteAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla classe
    $classe = $em->getRepository('App\Entity\Classe')->find($id);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella classe
      $em->remove($classe);
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception $e) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_classi');
  }

  /**
   * Modifica dei dati delle festività
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/festivita/{pagina}", name="scuola_festivita",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function festivitaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                  $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera pagina di visualizzazione
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/scuola_festivita/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/scuola_festivita/pagina', $pagina);
    }
    // recupera dati
    $dati = $em->getRepository('App\Entity\Festivita')->cerca($pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'festivita', $dati, $info);
  }

  /**
   * Modifica dati di una festività
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/festivita/edit/{id}", name="scuola_festivita_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function festivitaEditAction(Request $request, EntityManagerInterface $em,
                                      TranslatorInterface $trans, $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $festivita = $em->getRepository('App\Entity\Festivita')->find($id);
      if (!$festivita) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $festivita = (new Festivita())
        ->setData(new \DateTime('today'));
      $em->persist($festivita);
    }
    // form
    $form = $this->createForm(FestivitaType::class, $festivita, [
      'returnUrl' => $this->generateUrl('scuola_festivita'), 'formMode' => ($id ? 'singolo' : 'multiplo')]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // controlli aggiuntivi
      if (!$id && $form->get('dataInizio')->getData() > $form->get('dataFine')->getData()) {
        // errore: intervallo non valido
        $form->addError(new FormError($trans->trans('exception.intervallo_date_invalido')));
      }
      if ($form->isValid()) {
        if (!$id) {
          // imposta festività in intervallo di date
          $em->remove($festivita);
          $data = $form->get('dataInizio')->getData();
          while ($data <= $form->get('dataFine')->getData()) {
            $festivita = (new Festivita())
              ->setData(clone $data)
              ->setDescrizione($form->get('descrizione')->getData());
            $em->persist($festivita);
            $data->modify('+1 day');
          }
        }
        // memorizza modifiche
        $em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('scuola_festivita');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'festivita_edit', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Cancella una festività
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/festivita/delete/{id}", name="scuola_festivita_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function festivitaDeleteAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla festività
    $festivita = $em->getRepository('App\Entity\Festivita')->find($id);
    if (!$festivita) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella festivita
      $em->remove($festivita);
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception $e) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_festivita');
  }

  /**
   * Modifica dei dati degli orari scolastici
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/orario", name="scuola_orario",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function orarioAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // recupera dati
    $dati = $em->getRepository('App\Entity\Orario')->createQueryBuilder('o')
      ->join('o.sede', 's')
      ->orderBy('o.inizio,s.ordinamento', 'ASC')
      ->getQuery()
      ->getResult();
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'orario', $dati, $info);
  }

  /**
   * Modifica dei dati di un orario scolastico
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/orario/edit/{id}", name="scuola_orario_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function orarioEditAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                   $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $orario = $em->getRepository('App\Entity\Orario')->find($id);
      if (!$orario) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $orario = new Orario();
      $em->persist($orario);
    }
    // form
    $form = $this->createForm(OrarioType::class, $orario, ['returnUrl' => $this->generateUrl('scuola_orario')]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // controlli aggiuntivi
      if ($form->get('inizio')->getData() > $form->get('fine')->getData()) {
        // errore: intervallo non valido
        $form->addError(new FormError($trans->trans('exception.intervallo_date_invalido')));
      } elseif ($em->getRepository('App\Entity\Orario')->sovrapposizioni($orario)) {
        // errore: sovrapposizione con un periodo esistente
        $form->addError(new FormError($trans->trans('exception.periodo_sovrapposto')));
      }
      if ($form->isValid()) {
        // memorizza modifiche
        $em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('scuola_orario');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'orario_edit', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Cancella un orario scolastico
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/orario/delete/{id}", name="scuola_orario_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function orarioDeleteAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla orario
    $orario = $em->getRepository('App\Entity\Orario')->find($id);
    if (!$orario) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella orario
      $em->remove($orario);
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception $e) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_orario');
  }

  /**
   * Modifica dei dati della scansione oraria relativa ad un dato orario scolastico
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/orario/scansione/{id}", name="scuola_orario_scansione",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function orarioScansioneAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                        $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla orario
    $orario = $em->getRepository('App\Entity\Orario')->find($id);
    if (!$orario) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge scansione oraria
    $scansione = $em->getRepository('App\Entity\ScansioneOraria')->orario($orario);
    // form
    $form = $this->createForm(ScansioneOrariaSettimanaleType::class, null,
      ['returnUrl' => $this->generateUrl('scuola_orario'), 'data' => $scansione]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // legge dati
      $scansioneNuova = [];
      for ($giorno = 1; $giorno <= 6; $giorno++) {
        $scansioneNuova[$giorno] = [];
        $ora = 1;
        $prec = null;
        foreach($form->get('giorno_'.$giorno) as $datiOra) {
          if ($datiOra->get('visibile')->getData() == 0) {
            // fine dei dati da inserire
            break;
          }
          $scansioneNuova[$giorno][$ora] = (new ScansioneOraria())
            ->setOrario($orario)
            ->setGiorno($giorno)
            ->setOra($ora)
            ->setInizio($datiOra->get('inizio')->getData())
            ->setFine($datiOra->get('fine')->getData())
            ->setDurata($datiOra->get('durata')->getData());
          $em->persist($scansioneNuova[$giorno][$ora]);
          // controlla orari
          if ($datiOra->get('inizio')->getData() > $datiOra->get('fine')->getData()) {
            // errore: intervallo ore sbagliato
            $datiOra->get('fine')->addError(new FormError($trans->trans('exception.intervallo_ore_invalido')));
          }
          if ($prec && $datiOra->get('inizio')->getData() < $prec) {
            // errore: sovrapposizione intervalli ore
            $datiOra->get('inizio')->addError(new FormError($trans->trans('exception.orari_sovrapposti')));
          }
          $ora++;
          $prec = $datiOra->get('fine')->getData();
        }
      }
      if ($form->isValid()) {
        // rimuove dati inutili
        foreach ($scansione as $giorno) {
          foreach ($giorno as $ora) {
            $em->remove($ora);
          }
        }
        // memorizza modifiche
        $em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('scuola_orario');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'orario_scansione', $dati, $info, [$form->createView(), 'message.scansione_oraria']);
  }

}
