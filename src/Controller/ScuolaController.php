<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Classe;
use App\Entity\Corso;
use App\Entity\DefinizioneRichiesta;
use App\Entity\DefinizioneScrutinio;
use App\Entity\Festivita;
use App\Entity\Istituto;
use App\Entity\Materia;
use App\Entity\Orario;
use App\Entity\Preside;
use App\Entity\ScansioneOraria;
use App\Entity\Sede;
use App\Form\AmministratoreType;
use App\Form\ClasseType;
use App\Form\CorsoType;
use App\Form\DefinizioneRichiestaType;
use App\Form\DefinizioneScrutinioType;
use App\Form\FestivitaType;
use App\Form\IstitutoType;
use App\Form\MateriaType;
use App\Form\OrarioType;
use App\Form\PresideType;
use App\Form\ScansioneOrariaSettimanaleType;
use App\Form\SedeType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * ScuolaController - gestione dei dati della scuola
 *
 * @author Antonello Dessì
 */
class ScuolaController extends BaseController {

  /**
   * Gestisce la modifica dei dati degli scrutini
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param string $periodo Periodo dello scrutinio
   *
   * @Route("/scuola/scrutini/{periodo}", name="scuola_scrutini",
   *    requirements={"periodo": "P|S|F|G|R|X"},
   *    defaults={"periodo": ""},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function scrutini(Request $request, TranslatorInterface $trans, string $periodo): Response {
    // init
    $dati = [];
    $info = [];
    // lista periodi scrutinio
    $info['listaPeriodi'] = $this->em->getRepository(\App\Entity\Configurazione::class)->infoScrutini();
    $info['listaPeriodi']['G'] = $trans->trans('label.scrutini_periodo_G');
    $info['listaPeriodi']['R'] = $trans->trans('label.scrutini_periodo_R');
    $info['listaPeriodi']['X'] = $trans->trans('label.scrutini_periodo_X');
    // periodo predefinito
    if (empty($periodo)) {
      // ultimo periodo configurato
      $periodo = $this->em->getRepository(\App\Entity\DefinizioneScrutinio::class)->ultimo();
    }
    $info['periodo'] = $periodo;
    // legge dati
    $definizione = $this->em->getRepository(\App\Entity\DefinizioneScrutinio::class)->findOneByPeriodo($periodo);
    if (!$definizione) {
      // nuova definizione
      $argomenti[1] = $trans->trans('label.verbale_scrutinio_'.$periodo,
        ['periodo' => ($periodo == 'P' ? $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_nome') :
        ($periodo == 'S' ? $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_nome') : ''))]);
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
      $this->em->persist($definizione);
    }
    // form
    $form = $this->createForm(DefinizioneScrutinioType::class, $definizione,
      ['return_url' => $this->generateUrl('scuola_scrutini'),
      'values' => $definizione->getClassiVisibili()]);
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
      $subquery = $this->em->getRepository(\App\Entity\Classe::class)->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.anno=:anno')
        ->getDQL();
      for ($cl = 1; $cl <= 5; $cl++) {
        $risultato = $this->em->getRepository(\App\Entity\Scrutinio::class)->createQueryBuilder('s')
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
      $this->em->flush();
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
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/amministratore", name="scuola_amministratore",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function amministratore(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $amministratore = $this->em->getRepository(\App\Entity\Amministratore::class)->findOneBy([]);
    if (!$amministratore) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // form
    $form = $this->createForm(AmministratoreType::class, $amministratore,
      ['return_url' => $this->generateUrl('scuola_amministratore')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'amministratore', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Modifica dei dati del Dirigente Scolastico (un solo utente possibile)
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/dirigente", name="scuola_dirigente",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function dirigente(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $preside = $this->em->getRepository(\App\Entity\Preside::class)->findOneBy([]);
    if (!$preside) {
      // crea nuovo utente
      $preside = (new Preside())
        ->setPassword('NOPASSWORD')
        ->setAbilitato(true);
      $this->em->persist($preside);
    }
    // assicura che l'utente sia abilitato
    $preside->setAbilitato(true);
    // form
    $form = $this->createForm(PresideType::class, $preside, ['return_url' => $this->generateUrl('scuola_dirigente')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'dirigente', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Modifica dei dati dell'istituto (un solo istituto possibile)
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/istituto", name="scuola_istituto",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function istituto(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $istituto = $this->em->getRepository(\App\Entity\Istituto::class)->findOneBy([]);
    if (!$istituto) {
      // crea nuovo utente
      $istituto = new Istituto();
      $this->em->persist($istituto);
    }
    // form
    $form = $this->createForm(IstitutoType::class, $istituto, ['return_url' => $this->generateUrl('scuola_istituto')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'istituto', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Modifica dei dati delle sedi scolastiche
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/sedi", name="scuola_sedi",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function sedi(): Response
  {
      // init
      $dati = [];
      $info = [];
      // recupera dati
      $dati = $this->em->getRepository(\App\Entity\Sede::class)->findBY([], ['ordinamento' => 'ASC']);
      // mostra la pagina di risposta
      return $this->renderHtml('scuola', 'sedi', $dati, $info);
  }

  /**
   * Modifica dei dati di una sede scolastica
   *
   * @param Request $request Pagina richiesta
   * @param int $id Identificativo della sede
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
  public function sediEdit(Request $request, int $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $sede = $this->em->getRepository(\App\Entity\Sede::class)->find($id);
      if (!$sede) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $sede = new Sede();
      $this->em->persist($sede);
    }
    // form
    $form = $this->createForm(SedeType::class, $sede, ['return_url' => $this->generateUrl('scuola_sedi')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
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
   * @param int $id Identificativo della sede
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/sedi/delete/{id}", name="scuola_sedi_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function sediDelete(int $id): Response {
    // controlla sede
    $sede = $this->em->getRepository(\App\Entity\Sede::class)->find($id);
    if (!$sede) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella sede
      $this->em->remove($sede);
      // memorizza modifiche
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_sedi');
  }

  /**
   * Modifica dei dati dei corsi scolastici
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/corsi", name="scuola_corsi",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function corsi(): Response
  {
      // init
      $dati = [];
      $info = [];
      // recupera dati
      $dati = $this->em->getRepository(\App\Entity\Corso::class)->findBY([], ['nome' => 'ASC']);
      // mostra la pagina di risposta
      return $this->renderHtml('scuola', 'corsi', $dati, $info);
  }

  /**
   * Modifica dei dati di un corso scolastico
   *
   * @param Request $request Pagina richiesta
   * @param int $id Identificativo della classe
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
  public function corsiEdit(Request $request, int $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $corso = $this->em->getRepository(\App\Entity\Corso::class)->find($id);
      if (!$corso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $corso = new Corso();
      $this->em->persist($corso);
    }
    // form
    $form = $this->createForm(CorsoType::class, $corso, ['return_url' => $this->generateUrl('scuola_corsi')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
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
   * @param int $id Identificativo del corso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/corsi/delete/{id}", name="scuola_corsi_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function corsiDelete(int $id): Response {
    // controlla corso
    $corso = $this->em->getRepository(\App\Entity\Corso::class)->find($id);
    if (!$corso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella corso
      $this->em->remove($corso);
      // memorizza modifiche
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_corsi');
  }

  /**
   * Modifica dei dati delle materie scolastiche
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/materie", name="scuola_materie",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function materie(): Response
  {
      // init
      $dati = [];
      $info = [];
      // recupera dati
      $dati = $this->em->getRepository(\App\Entity\Materia::class)->findBY([], ['ordinamento' => 'ASC', 'nome' => 'ASC']);
      // mostra la pagina di risposta
      return $this->renderHtml('scuola', 'materie', $dati, $info);
  }

  /**
   * Modifica dati di una materia scolastica
   *
   * @param Request $request Pagina richiesta
   * @param int $id Identificativo della classe
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
  public function materieEdit(Request $request, int $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $materia = $this->em->getRepository(\App\Entity\Materia::class)->find($id);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $materia = new Materia();
      $this->em->persist($materia);
    }
    // form
    $form = $this->createForm(MateriaType::class, $materia, ['return_url' => $this->generateUrl('scuola_materie')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
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
   * @param int $id Identificativo della materia
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/materie/delete/{id}", name="scuola_materie_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function materieDelete(int $id): Response {
    // controlla materia
    $materia = $this->em->getRepository(\App\Entity\Materia::class)->find($id);
    if (!$materia) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella materia
      $this->em->remove($materia);
      // memorizza modifiche
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_materie');
  }

  /**
   * Modifica dei dati delle classi
   *
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
  public function classi(int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera pagina di visualizzazione
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/scuola_classi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/scuola_classi/pagina', $pagina);
    }
    // recupera dati
    $dati = $this->em->getRepository(\App\Entity\Classe::class)->cerca($pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'classi', $dati, $info);
  }

  /**
   * Modifica dati di una classe
   *
   * @param Request $request Pagina richiesta
   * @param int $id Identificativo della classe
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
  public function classiEdit(Request $request, int $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $classe = $this->em->getRepository(\App\Entity\Classe::class)->find($id);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $classe = new Classe();
      $this->em->persist($classe);
    }
    // form
    $opzioniCorsi = $this->em->getRepository(\App\Entity\Corso::class)->opzioni();
    $opzioniSedi = $this->em->getRepository(\App\Entity\Sede::class)->opzioni();
    $opzioniDocenti = $this->em->getRepository(\App\Entity\Docente::class)->opzioni();
    $form = $this->createForm(ClasseType::class, $classe, [
      'return_url' => $this->generateUrl('scuola_classi'),
      'values' => [$opzioniCorsi, $opzioniSedi, $opzioniDocenti]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
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
   * @param int $id Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/classi/delete/{id}", name="scuola_classi_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function classiDelete(int $id): Response {
    // controlla classe
    $classe = $this->em->getRepository(\App\Entity\Classe::class)->find($id);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella classe
      $this->em->remove($classe);
      // memorizza modifiche
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_classi');
  }

  /**
   * Modifica dei dati delle festività
   *
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
  public function festivita(int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera pagina di visualizzazione
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/scuola_festivita/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/scuola_festivita/pagina', $pagina);
    }
    // recupera dati
    $dati = $this->em->getRepository(\App\Entity\Festivita::class)->cerca($pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'festivita', $dati, $info);
  }

  /**
   * Modifica dati di una festività
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id Identificativo della classe
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
  public function festivitaEdit(Request $request, TranslatorInterface $trans, int $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $festivita = $this->em->getRepository(\App\Entity\Festivita::class)->find($id);
      if (!$festivita) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $festivita = (new Festivita())
        ->setData(new \DateTime('today'));
      $this->em->persist($festivita);
    }
    // form
    $form = $this->createForm(FestivitaType::class, $festivita, [
      'return_url' => $this->generateUrl('scuola_festivita'),
      'form_mode' => ($id ? 'singolo' : 'multiplo')]);
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
          $this->em->remove($festivita);
          $data = $form->get('dataInizio')->getData();
          while ($data <= $form->get('dataFine')->getData()) {
            $festivita = (new Festivita())
              ->setData(clone $data)
              ->setDescrizione($form->get('descrizione')->getData());
            $this->em->persist($festivita);
            $data->modify('+1 day');
          }
        }
        // memorizza modifiche
        $this->em->flush();
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
   * @param int $id Identificativo della festività
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/festivita/delete/{id}", name="scuola_festivita_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function festivitaDelete(int $id): Response {
    // controlla festività
    $festivita = $this->em->getRepository(\App\Entity\Festivita::class)->find($id);
    if (!$festivita) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella festivita
      $this->em->remove($festivita);
      // memorizza modifiche
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_festivita');
  }

  /**
   * Modifica dei dati degli orari scolastici
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/orario", name="scuola_orario",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function orario(): Response
  {
      // init
      $dati = [];
      $info = [];
      // recupera dati
      $dati = $this->em->getRepository(\App\Entity\Orario::class)->createQueryBuilder('o')
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
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id Identificativo dell'orario
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
  public function orarioEdit(Request $request, TranslatorInterface $trans, int $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $orario = $this->em->getRepository(\App\Entity\Orario::class)->find($id);
      if (!$orario) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $orario = new Orario();
      $this->em->persist($orario);
    }
    // form
    $opzioniSedi = $this->em->getRepository(\App\Entity\Sede::class)->opzioni();
    $form = $this->createForm(OrarioType::class, $orario, [
      'return_url' => $this->generateUrl('scuola_orario'), 'values' => [$opzioniSedi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // controlli aggiuntivi
      if ($form->get('inizio')->getData() > $form->get('fine')->getData()) {
        // errore: intervallo non valido
        $form->addError(new FormError($trans->trans('exception.intervallo_date_invalido')));
      } elseif ($this->em->getRepository(\App\Entity\Orario::class)->sovrapposizioni($orario)) {
        // errore: sovrapposizione con un periodo esistente
        $form->addError(new FormError($trans->trans('exception.periodo_sovrapposto')));
      }
      if ($form->isValid()) {
        // memorizza modifiche
        $this->em->flush();
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
   * @param int $id Identificativo dell'orario
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/orario/delete/{id}", name="scuola_orario_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function orarioDelete(int $id): Response {
    // controlla orario
    $orario = $this->em->getRepository(\App\Entity\Orario::class)->find($id);
    if (!$orario) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella orario
      $this->em->remove($orario);
      // memorizza modifiche
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception) {
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
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id Identificativo della scansione oraria
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
  public function orarioScansione(Request $request, TranslatorInterface $trans, int $id): Response {
    // init
    $dati = [];
    $info = [];
    // controlla orario
    $orario = $this->em->getRepository(\App\Entity\Orario::class)->find($id);
    if (!$orario) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge scansione oraria
    $scansione = $this->em->getRepository(\App\Entity\ScansioneOraria::class)->orario($orario);
    // form
    $form = $this->createForm(ScansioneOrariaSettimanaleType::class, null,
      ['return_url' => $this->generateUrl('scuola_orario'), 'values' => $scansione]);
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
          $this->em->persist($scansioneNuova[$giorno][$ora]);
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
            $this->em->remove($ora);
          }
        }
        // memorizza modifiche
        $this->em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('scuola_orario');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'orario_scansione', $dati, $info, [$form->createView(), 'message.scansione_oraria']);
  }

  /**
   * Visualizza i moduli di richiesta definiti
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/moduli", name="scuola_moduli",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function moduli(): Response
  {
      // init
      $dati = [];
      $info = [];
      // recupera dati
      $dati = $this->em->getRepository(\App\Entity\DefinizioneRichiesta::class)->findBY([], ['nome' => 'ASC']);
      // mostra la pagina di risposta
      return $this->renderHtml('scuola', 'moduli', $dati, $info);
  }

  /**
   * Modifica i dati di un modulo di richiesta
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id Identificativo del modulo
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/moduli/edit/{id}", name="scuola_moduli_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function moduliEdit(Request $request, TranslatorInterface $trans, int $id): Response {
    // init
    $fs = new Filesystem();
    $finder = new Finder();
    $path = $this->getParameter('kernel.project_dir').'/PERSONAL/data/moduli';
    $dati = [];
    $info = [];
    // controlla azione
    $campi = [];
    if ($id > 0) {
      // azione edit
      $modulo = $this->em->getRepository(\App\Entity\DefinizioneRichiesta::class)->find($id);
      if (!$modulo) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      foreach ($modulo->getCampi() as $key=>$val) {
        $campi[] = ['nome_campo' => $key, 'tipo_campo' => $val[0], 'campo_obbligatorio' => $val[1]];
      }
    } else {
      // azione add
      $modulo = new DefinizioneRichiesta();
      $this->em->persist($modulo);
    }
    // determina lista moduli
    $lista = [];
    if ($fs->exists($path)) {
      $finder->files()->in($path)->name('*.html.twig')->sortByName();
      foreach ($finder as $file) {
        $lista[substr($file->getFilename(), 0, -10)] = $file->getFilename();
      }
    }
    // form
    $opzioniSedi = $this->em->getRepository(\App\Entity\Sede::class)->opzioni();
    $form = $this->createForm(DefinizioneRichiestaType::class, $modulo, [
      'return_url' => $this->generateUrl('scuola_moduli'), 'values' => [$opzioniSedi, $campi, $lista]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla campi
      $listaCampi = [];
      foreach ($form->get('campi')->getData() as $campo) {
        $listaCampi[$campo['nome_campo']] = [$campo['tipo_campo'], $campo['campo_obbligatorio']];
        if (empty($campo['nome_campo'])) {
          // errore: nome campo duplicato
          $form->addError(new FormError($trans->trans('exception.modulo_campo_senza_nome')));
        } elseif ($campo['nome_campo'] == 'data') {
          // errore: nome campo riservato
          $form->addError(new FormError($trans->trans('exception.modulo_campo_nome_riservato')));
        }
        if (empty($campo['tipo_campo'])) {
          // errore: nome campo duplicato
          $form->addError(new FormError($trans->trans('exception.modulo_campo_senza_tipo')));
        }
      }
      if (count($listaCampi) != count($form->get('campi')->getData())) {
        // errore: nome campo duplicato
        $form->addError(new FormError($trans->trans('exception.modulo_campo_duplicato')));
      }
      if ($form->isValid()) {
        // constrollo richiedenti
        $richiedenti = explode(',', $modulo->getRichiedenti());
        if (in_array('DN', $richiedenti, true) && !in_array('SN', $richiedenti, true)) {
          $richiedenti[] = 'SN';
          $modulo->setRichiedenti(implode(',', $richiedenti));
        }
        // memorizza modifiche
        $modulo->setCampi($listaCampi);
        $this->em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('scuola_moduli');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'moduli_edit', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

  /**
   * Cancella un modulo definito
   *
   * @param int $id Identificativo del modulo
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/moduli/delete/{id}", name="scuola_moduli_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function moduliDelete(int $id): Response {
    // controlla modulo
    $modulo = $this->em->getRepository(\App\Entity\DefinizioneRichiesta::class)->find($id);
    if (!$modulo) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    try {
      // cancella modulo
      $this->em->remove($modulo);
      // memorizza modifiche
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.delete_ok');
    } catch (\Exception) {
      // errore: violazione vincolo di integrità referenziale
      $this->addFlash('danger', 'exception.delete_errors');
    }
    // redirect
    return $this->redirectToRoute('scuola_moduli');
  }

  /**
   * Abilitazione o disabilitazione di un modulo di richiesta
   *
   * @param int $id ID del modulo di richiesta
   * @param int $abilita Vale 1 per abilitare, 0 per disabilitare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/moduli/abilita/{id}/{abilita}", name="scuola_moduli_abilita",
   *    requirements={"id": "\d+", "abilita": "0|1"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function moduliAbilita(int $id, int $abilita): Response {
    // controlla modulo
    $modulo = $this->em->getRepository(\App\Entity\DefinizioneRichiesta::class)->find($id);
    if (!$modulo) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // abilita o disabilita
    $modulo->setAbilitata($abilita == 1);
    // memorizza modifiche
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('scuola_moduli');
  }

}
