<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use App\Entity\Avviso;
use App\Entity\Notifica;
use App\Util\RegistroUtil;
use App\Util\BachecaUtil;
use App\Util\AgendaUtil;
use App\Util\LogHandler;


/**
 * AgendaController - gestione dell'agenda
 */
class AgendaController extends AbstractController {

  /**
   * Visualizza gli eventi destinati ai docenti
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param string $mese Anno e mese della pagina da visualizzare dell'agenda
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/eventi/{mese}", name="agenda_eventi",
   *    requirements={"mese": "\d\d\d\d-\d\d"},
   *    defaults={"mese": "0000-00"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function eventiAction(EntityManagerInterface $em, SessionInterface $session, AgendaUtil $age, $mese) {
    $dati = null;
    $info = null;
    // parametro data
    if ($mese == '0000-00') {
      // mese non specificato
      if ($session->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/agenda_eventi/mese').'-01');
      } else {
        // imposta data odierna
        $mese = (new \DateTime())->modify('first day of this month');
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $mese = \DateTime::createFromFormat('Y-m-d', $mese.'-01');
      $session->set('/APP/ROUTE/agenda_eventi/mese', $mese->format('Y-m'));
    }
    // nome/url mese
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('MMMM yyyy');
    $info['mese'] =  ucfirst($formatter->format($mese));
    // data prec/succ
    $data_inizio = \DateTime::createFromFormat('Y-m-d', $mese->format('Y-m-01'));
    $data_fine = clone $data_inizio;
    $data_fine->modify('last day of this month');
    $data_succ = (clone $data_fine);
    $data_succ = $em->getRepository('App:Festivita')->giornoSuccessivo($data_succ);
    $info['url_succ'] = ($data_succ ? $data_succ->format('Y-m') : null);
    $data_prec = (clone $data_inizio);
    $data_prec = $em->getRepository('App:Festivita')->giornoPrecedente($data_prec);
    $info['url_prec'] = ($data_prec ? $data_prec->format('Y-m') : null);
    // presentazione calendario
    $info['inizio'] = (intval($mese->format('w')) - 1);
    $m = clone $mese;
    $info['ultimo_giorno'] = $m->modify('last day of this month')->format('j');
    $info['fine'] = (intval($m->format('w')) == 0 ? 0 : 6 - intval($m->format('w')));
    // recupera dati
    $dati = $age->agendaEventi($this->getUser(), $mese);
    // mostra la pagina di risposta
    return $this->render('agenda/eventi.html.twig', array(
      'pagina_titolo' => 'page.agenda_eventi',
      'mese' => $mese,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i dettagli di un evento destinato al docente
   *
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param string $data Data dell'evento (AAAA-MM-GG)
   * @param string $tipo Tipo dell'evento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/eventi/dettagli/{data}/{tipo}", name="agenda_eventi_dettagli",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "tipo": "C|A|V|P"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function eventoDettagliAction(AgendaUtil $age, $data, $tipo) {
    // inizializza
    $dati = null;
    // data
    $data = \DateTime::createFromFormat('Y-m-d', $data);
    // legge dati
    $dati = $age->dettagliEvento($this->getUser(), $data, $tipo);
    // visualizza pagina
    return $this->render('agenda/scheda_evento_'.$tipo.'.html.twig', array(
      'dati' => $dati,
      'data' => $data,
    ));
  }

  /**
   * Aggiunge o modifica una verifica
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/verifica/edit/{id}", name="agenda_verifica_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function verificaEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                      TranslatorInterface $trans, RegistroUtil $reg, BachecaUtil $bac, AgendaUtil $age, LogHandler $dblogger, $id) {
    // inizializza
    $lista_festivi = null;
    $dati = array();
    $verifiche = array();
    $docente = $this->getUser();
    $scelta_materia = null;
    if ($request->isMethod('GET')) {
      // inizializza sessione
      $session->set('/APP/ROUTE/agenda_verifica_edit/conferma', 0);
    }
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => 'V']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // legge destinatari di filtri
      $dest_filtro = $age->filtriVerificheCompiti($avviso);
      $avviso_old = clone $avviso;
      // controlla sostegno
      $sostegno = $em->getRepository('App:Cattedra')->findOneBy(['docente' => $docente,
        'classe' => $avviso->getCattedra()->getClasse(),
        'alunno' => (count($dest_filtro['utenti']) > 0 ? $dest_filtro['utenti'][0] : 0),
        'attiva' => 1]);
      if ($sostegno) {
        // cattedra di sostegno: modifica dati
        $scelta_materia = $avviso->getCattedra()->getId();
        $avviso->setCattedra($sostegno);
      }
    } else {
      // azione add
      $oggi = new \DateTime();
      $mese = $oggi;
      if ($session->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese_sessione = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/agenda_eventi/mese').'-01');
        if ($mese_sessione > $oggi) {
          // ultimo giorno di mese precedente
          $mese = $mese_sessione;
          $mese->modify('-1 day');
        }
      }
      $mese = $em->getRepository('App:Festivita')->giornoSuccessivo($mese);
      $avviso = (new Avviso())
        ->setTipo('V')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(false)
        ->setDestinatariAlunni(false)
        ->setDestinatariIndividuali(false)
        ->setData($mese)
        ->setOggetto('__TEMP__'); // valore temporaneo
      $em->persist($avviso);
      // destinatari di filtri
      $dest_filtro['classi'] = array();
      $dest_filtro['utenti'] = array();
    }
    // recupera festivi per calendario
    $lista_festivi = $age->festivi();
    // imposta autore dell'avviso
    $avviso->setDocente($docente);
    // opzione scelta filtro
    $scelta_filtro = 'T';
    $scelta_filtro_individuale = array();
    if ($avviso->getDestinatariIndividuali()) {
      $scelta_filtro = 'I';
      $scelta_filtro_individuale = array_column($dest_filtro['utenti'], 'alunno');
    }
    // form di inserimento
    $dati = $em->getRepository('App:Cattedra')->cattedreDocente($docente);
    $form = $this->container->get('form.factory')->createNamedBuilder('verifica_edit', FormType::class, $avviso)
      ->add('data', DateType::class, array('label' => 'label.data_verifica',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('cattedra', ChoiceType::class, array('label' => 'label.cattedra_verifica',
        'choices' => $dati['choice'],
        'expanded' => false,
        'multiple' => false,
        'choice_translation_domain' => false,
        'mapped' => true,
        'required' => true))
      ->add('materia',  HiddenType::class, array('label' => false,
        'data' => $scelta_materia,
        'mapped' => false,
        'required' => false))
      ->add('testo', TextareaType::class, array(
        'label' => 'label.descrizione_verifica',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('filtro', ChoiceType::class, array('label' => false,
        'data' => $scelta_filtro,
        'choices' => ['label.filtro_tutti' => 'T', 'label.filtro_alunno' => 'I'],
        'expanded' => false,
        'multiple' => false,
        'mapped' => false,
        'required' => true))
      ->add('filtroIndividuale',  HiddenType::class, array('label' => false,
        'data' => implode(',', $scelta_filtro_individuale),
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('agenda_eventi')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_filtro_id = array();
      if ($form->get('filtro')->getData() == 'T') {
        // classe
        $val_filtro = 'C';
        if ($avviso->getCattedra()) {
          $val_filtro_id = [$avviso->getCattedra()->getClasse()->getId()];
        }
      } else {
        // individuali
        $val_filtro = 'I';
        $val_filtro_id = array_filter(explode(',', $form->get('filtroIndividuale')->getData()), function($v){
          return ($v != ''); });
      }
      // controllo errori
      if ($val_filtro == 'I' && empty($val_filtro_id)) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.destinatari_filtro_mancanti')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($trans->trans('exception.data_festiva')));
      }
      // controllo cattedra
      if (!$avviso->getCattedra()) {
        // errore: cattedra non specificata
        $form->addError(new FormError($trans->trans('exception.cattedra_mancante')));
      }
      // controllo sostegno
      $materia = null;
      if ($avviso->getCattedra() && $avviso->getCattedra()->getMateria()->getTipo() == 'S') {
        // legge materia scelta
        $materia = $em->getRepository('App:Cattedra')->findOneBy(['id' => $form->get('materia')->getData(),
          'classe' => $avviso->getCattedra()->getClasse(), 'attiva' => 1]);
        if (!$materia || $avviso->getCattedra()->getAlunno()->getId() != $val_filtro_id[0]) {
          // errore: dati inconsistenti
          $form->addError(new FormError($trans->trans('exception.cattedra_non_valida')));
        }
      }
      // controllo permessi
      if (!$age->azioneEvento(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($trans->trans('exception.avviso_non_permesso')));
      }
      if (!$reg->azioneAnnotazione('add', $avviso->getData(), $this->getUser(), null, null)) {
        // errore: nuova annotazione non permessa
        $form->addError(new FormError($trans->trans('exception.annotazione_non_permessa')));
      }
      if (count($avviso->getAnnotazioni()) > 0) {
        $a = $avviso->getAnnotazioni()[0];
        if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
          // errore: cancellazione annotazione non permessa
          $form->addError(new FormError($trans->trans('exception.annotazione_non_permessa')));
        }
      }
      // modifica dati
      if ($form->isValid()) {
        if ($materia) {
          // verifica sostegno: modifica dati cattedra
          $avviso->setCattedra($materia);
        }
        // controllo verifiche esistenti
        $verifiche = $age->controlloVerifiche($avviso);
        $data_classe = $avviso->getData()->format('Y-m-d').'|'.$avviso->getCattedra()->getClasse()->getId();
        if (count($verifiche) > 0 && $session->get('/APP/ROUTE/agenda_verifica_edit/conferma', 0) != $data_classe) {
          // richiede conferma
          $session->set('/APP/ROUTE/agenda_verifica_edit/conferma', $data_classe);
        } else {
          // oggetto
          $avviso->setOggetto($trans->trans('message.verifica_oggetto',
            ['materia' => $avviso->getCattedra()->getMateria()->getNomeBreve()]));
          // destinatari
          $age->modificaFiltriVerificheCompiti($avviso, $dest_filtro, $val_filtro, $val_filtro_id);
          // annotazione
          $log_annotazioni['delete'] = array();
          if ($id) {
            // cancella annotazioni
            foreach ($avviso->getAnnotazioni() as $a) {
              $log_annotazioni['delete'][] = $a->getId();
              $em->remove($a);
            }
            $avviso->setAnnotazioni(new ArrayCollection());
          }
          // crea nuova annotazione
          $age->creaAnnotazione($avviso);
          // ok: memorizza dati
          $em->flush();
          // log azione e notifica
          $notifica = (new Notifica())
            ->setOggettoNome('Avviso')
            ->setOggettoId($avviso->getId());
          $em->persist($notifica);
          if (!$id) {
            // nuovo
            $notifica->setAzione('A');
            $dblogger->write($this->getUser(), $request->getClientIp(), 'AGENDA', 'Crea verifica', __METHOD__, array(
              'Avviso' => $avviso->getId(),
              'Annotazioni' => implode(', ', array_map(function ($a) {
                  return $a->getId();
                }, $avviso->getAnnotazioni()->toArray())),
              ));
          } else {
            // modifica
            $notifica->setAzione('E');
            $dblogger->write($this->getUser(), $request->getClientIp(), 'AGENDA', 'Modifica verifica', __METHOD__, array(
              'Avviso' => $avviso->getId(),
              'Data' => $avviso_old->getData()->format('d/m/Y'),
              'Cattedra' => $avviso_old->getCattedra()->getId(),
              'Testo' => $avviso_old->getTesto(),
              'Destinatari individuali' => $avviso_old->getDestinatariIndividuali(),
              'Classi' => implode(', ', array_column($dest_filtro['classi'], 'classe')),
              'Utenti' => implode(', ', array_map(function ($a) {
                    return $a['genitore'].'->'.$a['alunno'];
                  }, $dest_filtro['utenti'])),
              'Docente' => $avviso_old->getDocente()->getId(),
              'Annotazioni cancellate' => implode(', ', $log_annotazioni['delete']),
              'Annotazioni create' => implode(', ', array_map(function ($a) {
                  return $a->getId();
                }, $avviso->getAnnotazioni()->toArray())),
              ));
          }
          // redirezione
          return $this->redirectToRoute('agenda_eventi');
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('agenda/verifica_edit.html.twig', array(
      'pagina_titolo' => 'page.agenda_verifica',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_verifica' : 'title.nuova_verifica'),
      'verifiche' => $verifiche,
      'lista_festivi' => $lista_festivi,
      'dati' => $dati,
    ));
  }

  /**
   * Restituisce gli alunni della classe collegata alla cattedra indicata
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id Identificativo della cattedra
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/agenda/cattedra/{id}", name="agenda_cattedra",
   *    requirements={"id": "\d+"},
   *    defaults={"id": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function cattedraAjaxAction(EntityManagerInterface $em, $id) {
    $alunni = $em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select("a.id,CONCAT(a.cognome,' ',a.nome) AS nome")
      ->join('App:Cattedra', 'c', 'WITH', 'c.classe=a.classe')
      ->where('a.abilitato=:abilitato AND c.id=:cattedra AND c.attiva=:attiva')
      ->setParameters(['abilitato' => 1, 'cattedra' => $id, 'attiva' => 1])
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return new JsonResponse($alunni);
  }

  /**
   * Restituisce le materie della cattedra della classe indicata
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id Identificativo della classe
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/agenda/classe/{id}", name="agenda_classe",
   *    requirements={"id": "\d+"},
   *    defaults={"id": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function classeAjaxAction(EntityManagerInterface $em, $id) {
    // solo cattedre attive e normali, no supplenza, no sostegno
    $cattedre = $em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id,m.nome')
      ->join('c.materia', 'm')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND c.supplenza=:supplenza AND m.tipo!=:sostegno')
      ->setParameters(['classe' => $id, 'attiva' => 1, 'tipo' => 'N', 'supplenza' => 0, 'sostegno' => 'S'])
      ->orderBy('m.nomeBreve', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return new JsonResponse($cattedre);
  }

  /**
   * Cancella verifica
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/verifica/delete/{id}", name="agenda_verifica_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function verificaDeleteAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger,
                                        RegistroUtil $reg, BachecaUtil $bac, AgendaUtil $age, $id) {
    // controllo avviso
    $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => 'V']);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$age->azioneEvento('delete', $avviso->getData(), $this->getUser(), $avviso)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if (count($avviso->getAnnotazioni()) > 0) {
      $a = $avviso->getAnnotazioni()[0];
      if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    // cancella annotazioni
    $log_annotazioni = array();
    foreach ($avviso->getAnnotazioni() as $a) {
      $log_annotazioni[] = $a->getId();
      $em->remove($a);
    }
    // cancella destinatari
    $log_destinatari = $bac->eliminaFiltriAvviso($avviso);
    // cancella avviso
    $avviso_id = $avviso->getId();
    $em->remove($avviso);
    // ok: memorizza dati
    $em->flush();
    // log azione e notifica
    $notifica = (new Notifica())
      ->setOggettoNome('Avviso')
      ->setOggettoId($avviso_id)
      ->setAzione('D');
    $em->persist($notifica);
    $dblogger->write($this->getUser(), $request->getClientIp(), 'AGENDA', 'Cancella verifica', __METHOD__, array(
      'Id' => $avviso_id,
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Testo' => $avviso->getTesto(),
      'Classi cancellate' => implode(', ', $log_destinatari['classi']),
      'Utenti cancellati' => implode(', ', array_map(function ($a) {
          return $a['genitore'].'->'.$a['alunno'];
        }, $log_destinatari['utenti'])),
      'Docente' => $avviso->getDocente()->getId(),
      'Annotazioni' => implode(', ', $log_annotazioni),
      ));
    // redirezione
    return $this->redirectToRoute('agenda_eventi');
  }

  /**
   * Aggiunge o modifica un compito per casa
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/compito/edit/{id}", name="agenda_compito_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function compitoEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     TranslatorInterface $trans, RegistroUtil $reg, AgendaUtil $age, LogHandler $dblogger, $id) {
    // inizializza
    $lista_festivi = null;
    $docente = $this->getUser();
    $dati = array();
    $scelta_materia = null;
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => 'P']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // conserva originale
      $avviso_old = clone $avviso;
      // legge destinatari di filtri
      $dest_filtro = $age->filtriVerificheCompiti($avviso);
      // controlla sostegno
      $sostegno = $em->getRepository('App:Cattedra')->findOneBy(['docente' => $docente,
        'classe' => $avviso->getCattedra()->getClasse(),
        'alunno' => (count($dest_filtro['utenti']) > 0 ? $dest_filtro['utenti'][0] : 0),
        'attiva' => 1]);
      if ($sostegno) {
        // cattedra di sostegno: modifica dati
        $scelta_materia = $avviso->getCattedra()->getId();
        $avviso->setCattedra($sostegno);
      }
    } else {
      // azione add
      $oggi = new \DateTime();
      $mese = $oggi;
      if ($session->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese_sessione = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/agenda_eventi/mese').'-01');
        if ($mese_sessione > $oggi) {
          // ultimo giorno di mese precedente
          $mese = $mese_sessione;
          $mese->modify('-1 day');
        }
      }
      $mese = $em->getRepository('App:Festivita')->giornoSuccessivo($mese);
      $avviso = (new Avviso())
        ->setTipo('P')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(false)
        ->setDestinatariAlunni(false)
        ->setDestinatariIndividuali(false)
        ->setData($mese)
        ->setOggetto('__TEMP__'); // valore temporaneo
      $em->persist($avviso);
      // destinatari di filtri
      $dest_filtro['classi'] = array();
      $dest_filtro['utenti'] = array();
    }
    // recupera festivi per calendario
    $lista_festivi = $age->festivi();
    // imposta autore dell'avviso
    $avviso->setDocente($docente);
    // opzione scelta filtro
    $scelta_filtro = 'T';
    $scelta_filtro_individuale = array();
    if ($avviso->getDestinatariIndividuali()) {
      $scelta_filtro = 'I';
      $scelta_filtro_individuale = array_column($dest_filtro['utenti'], 'alunno');
    }
    // form di inserimento
    $dati = $em->getRepository('App:Cattedra')->cattedreDocente($docente);
    $form = $this->container->get('form.factory')->createNamedBuilder('compito_edit', FormType::class, $avviso)
      ->add('data', DateType::class, array('label' => 'label.data_compito',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('cattedra', ChoiceType::class, array('label' => 'label.cattedra_compito',
        'choices' => $dati['choice'],
        'expanded' => false,
        'multiple' => false,
        'choice_translation_domain' => false,
        'mapped' => true,
        'required' => true))
      ->add('materia',  HiddenType::class, array('label' => false,
        'data' => $scelta_materia,
        'mapped' => false,
        'required' => false))
      ->add('testo', TextareaType::class, array(
        'label' => 'label.descrizione_compito',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('filtro', ChoiceType::class, array('label' => false,
        'data' => $scelta_filtro,
        'choices' => ['label.filtro_tutti' => 'T', 'label.filtro_alunno' => 'I'],
        'expanded' => false,
        'multiple' => false,
        'mapped' => false,
        'required' => true))
      ->add('filtroIndividuale',  HiddenType::class, array('label' => false,
        'data' => implode(',', $scelta_filtro_individuale),
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('agenda_eventi')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_filtro_id = array();
      if ($form->get('filtro')->getData() == 'T') {
        // classe
        $val_filtro = 'C';
        if ($avviso->getCattedra()) {
          $val_filtro_id = [$avviso->getCattedra()->getClasse()->getId()];
        }
      } else {
        // individuali
        $val_filtro = 'I';
        $val_filtro_id = array_filter(explode(',', $form->get('filtroIndividuale')->getData()), function($v){
          return ($v != ''); });
      }
      // controllo errori
      if ($val_filtro == 'I' && empty($val_filtro_id)) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.destinatari_filtro_mancanti')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($trans->trans('exception.data_festiva')));
      }
      // controllo cattedra
      if (!$avviso->getCattedra()) {
        // errore: cattedra non specificata
        $form->addError(new FormError($trans->trans('exception.cattedra_mancante')));
      }
      // controllo sostegno
      $materia = null;
      if ($avviso->getCattedra() && $avviso->getCattedra()->getMateria()->getTipo() == 'S') {
        // legge materia scelta
        $materia = $em->getRepository('App:Cattedra')->findOneBy(['id' => $form->get('materia')->getData(),
          'classe' => $avviso->getCattedra()->getClasse(), 'attiva' => 1]);
        if (!$materia || $avviso->getCattedra()->getAlunno()->getId() != $val_filtro_id[0]) {
          // errore: dati inconsistenti
          $form->addError(new FormError($trans->trans('exception.cattedra_non_valida')));
        }
      }
      // controllo permessi
      if (!$age->azioneEvento(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($trans->trans('exception.avviso_non_permesso')));
      }
      // modifica dati
      if ($form->isValid()) {
        if ($materia) {
          // sostegno: modifica dati cattedra
          $avviso->setCattedra($materia);
        }
        // oggetto
        $avviso->setOggetto($trans->trans('message.compito_oggetto',
            ['materia' => $avviso->getCattedra()->getMateria()->getNomeBreve()]));
        // destinatari
        $age->modificaFiltriVerificheCompiti($avviso, $dest_filtro, $val_filtro, $val_filtro_id);
        // ok: memorizza dati
        $em->flush();
        // log azione e notifica
        $notifica = (new Notifica())
          ->setOggettoNome('Avviso')
          ->setOggettoId($avviso->getId());
        $em->persist($notifica);
        if (!$id) {
          // nuovo
          $notifica->setAzione('A');
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AGENDA', 'Crea compito', __METHOD__, array(
            'Avviso' => $avviso->getId(),
            ));
        } else {
          // modifica
          $notifica->setAzione('E');
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AGENDA', 'Modifica compito', __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Cattedra' => $avviso_old->getCattedra()->getId(),
            'Testo' => $avviso_old->getTesto(),
            'Destinatari individuali' => $avviso_old->getDestinatariIndividuali(),
            'Classi' => implode(', ', array_column($dest_filtro['classi'], 'classe')),
            'Utenti' => implode(', ', array_map(function ($a) {
                  return $a['genitore'].'->'.$a['alunno'];
                }, $dest_filtro['utenti'])),
            'Docente' => $avviso_old->getDocente()->getId(),
            ));
        }
        // redirezione
        return $this->redirectToRoute('agenda_eventi');
      }
    }
    // mostra la pagina di risposta
    return $this->render('agenda/compito_edit.html.twig', array(
      'pagina_titolo' => 'page.agenda_compito',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_compito' : 'title.nuovo_compito'),
      'lista_festivi' => $lista_festivi,
      'dati' => $dati,
    ));
  }

  /**
   * Cancella compiti per casa
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/compito/delete/{id}", name="agenda_compito_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function compitoDeleteAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger,
                                       AgendaUtil $age, $id) {
    // controllo avviso
    $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => 'P']);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$age->azioneEvento('delete', $avviso->getData(), $this->getUser(), $avviso)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella destinatari
    $log_destinatari = $age->eliminaFiltriVerificheCompiti($avviso);
    // cancella avviso
    $avviso_id = $avviso->getId();
    $em->remove($avviso);
    // ok: memorizza dati
    $em->flush();
    // log azione e notifica
    $notifica = (new Notifica())
      ->setOggettoNome('Avviso')
      ->setOggettoId($avviso_id)
      ->setAzione('D');
    $em->persist($notifica);
    $dblogger->write($this->getUser(), $request->getClientIp(), 'AGENDA', 'Cancella compito', __METHOD__, array(
      'Avviso' => $avviso_id,
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Testo' => $avviso->getTesto(),
      'Destinatari individuali' => $avviso->getDestinatariIndividuali(),
      'Classi' => implode(', ', $log_destinatari['classi']),
      'Utenti' => implode(', ', array_map(function ($a) {
                  return $a['genitore'].'->'.$a['alunno'];
                }, $log_destinatari['utenti'])),
      'Docente' => $avviso->getDocente()->getId(),
      ));
    // redirezione
    return $this->redirectToRoute('agenda_eventi');
  }

}
