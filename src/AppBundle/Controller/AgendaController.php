<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
use AppBundle\Entity\Avviso;
use AppBundle\Util\RegistroUtil;
use AppBundle\Util\BachecaUtil;
use AppBundle\Util\AgendaUtil;
use AppBundle\Util\LogHandler;


/**
 * AgendaController - gestione dell'agenda
 */
class AgendaController extends Controller {

  /**
   * Visualizza gli eventi destinati ai docenti
   *
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param string $mese Anno e mese della pagina da visualizzare dell'agenda
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/eventi/{mese}", name="agenda_eventi",
   *    requirements={"mese": "\d\d\d\d-\d\d"},
   *    defaults={"mese": "0000-00"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function eventiAction(SessionInterface $session, AgendaUtil $age, $mese) {
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
    $m = clone $mese;
    $mese_succ = $m->modify('first day of next month');
    $info['mese_succ'] = ucfirst($formatter->format($mese_succ));
    $info['url_succ'] = $mese_succ->format('Y-m');
    $m = clone $mese;
    $mese_prec = $m->modify('first day of previous month');
    $info['mese_prec'] = ucfirst($formatter->format($mese_prec));
    $info['url_prec'] = $mese_prec->format('Y-m');
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
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "tipo": "C|A|V"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
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
   *    defaults={"id": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function verificaEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                      RegistroUtil $reg, BachecaUtil $bac, AgendaUtil $age, LogHandler $dblogger, $id) {
    // inizializza
    $lista_festivi = null;
    $verifiche = array();
    $docente = $this->getUser();
    if ($request->isMethod('GET')) {
      // inizializza sessione
      $session->set('/APP/ROUTE/agenda_verifica_edit/conferma', 0);
    }
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('AppBundle:Avviso')->findOneBy(['id' => $id, 'tipo' => 'V']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $oggi = new \DateTime();
      if ($session->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/agenda_eventi/mese').'-01');
        if ($mese < $oggi) {
          $mese = $oggi;
        }
      } else {
        $mese = $oggi;
      }
      while ($reg->controlloData($mese, null)) {
        // festivo: va al giorno successivo
        $mese->modify('+1 day');
      }
      $avviso = (new Avviso())
        ->setTipo('V')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(true)
        ->setDestinatariAlunni(false)
        ->setDestinatariIndividuali(false)
        ->setData($mese)
        ->setOggetto('__TEMP__'); // valore temporaneo
      $em->persist($avviso);
    }
    // recupera festivi per calendario
    $lista_festivi = $age->festivi();
    // legge destinatari di filtri
    $dest_filtro = $bac->filtriAvviso($avviso);
    // imposta autore dell'avviso
    $avviso->setDocente($docente);
    // imposta lettura non avvenuta (per avviso modificato)
    foreach ($dest_filtro['utenti'] as $k=>$v) {
      $dest_filtro['genitori'][$k]['letto'] = null;
    }
    // opzione scelta filtro
    $scelta_filtro = 'T';
    $scelta_filtro_individuale = array();
    if ($avviso->getDestinatariIndividuali()) {
      $scelta_filtro = 'I';
      foreach (array_column($dest_filtro['utenti'], 'alunno') as $a) {
        $alunno = $em->getRepository('AppBundle:Alunno')->find($a);
        if ($alunno) {
          $scelta_filtro_individuale[] = $alunno->getId();
        }
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('verifica_edit', FormType::class, $avviso)
      ->add('data', DateType::class, array('label' => 'label.data_verifica',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('cattedra', EntityType::class, array('label' => 'label.cattedra_verifica',
        'class' => 'AppBundle:Cattedra',
        'choice_label' => function ($obj) {
            return $obj->getClasse()->getAnno().'ª '.$obj->getClasse()->getSezione().' - '.$obj->getMateria()->getNome();
          },
        'query_builder' => function (EntityRepository $er) use ($docente) {
            return $er->createQueryBuilder('c')
              ->join('c.classe', 'cl')
              ->join('c.materia', 'm')
              ->where('c.docente=:docente AND c.attiva=:attiva AND m.tipo!=:tipo')
              ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
              ->setParameters(['docente' => $docente, 'attiva' => 1, 'tipo' => 'S']);
          },
        'expanded' => false,
        'multiple' => false,
        'mapped' => true,
        'required' => true))
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
      $val_staff_filtro = 'N';
      $val_staff_sedi = array();
      $val_destinatari = ['G'];
      $val_filtro = 'N';
      $val_filtro_id = array();
      if ($form->get('filtro')->getData() == 'T') {
        // genitori della classe
        $val_filtro = 'C';
        if ($avviso->getCattedra()) {
          $val_filtro_id = [$avviso->getCattedra()->getClasse()->getId()];
        }
      } else {
        // genitori individuali
        $val_destinatari[] = 'I';
        $val_filtro = 'I';
        foreach (explode(',', $form->get('filtroIndividuale')->getData()) as $fid) {
          if ($em->getRepository('AppBundle:Alunno')->findOneBy(['id' => $fid, 'abilitato' => 1])) {
            $val_filtro_id[] = $fid;
          }
        }
      }
      // controllo errori
      if ($val_filtro != 'T' && count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($this->get('translator')->trans('exception.destinatari_filtro_mancanti')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($this->get('translator')->trans('exception.data_festiva')));
      }
      // controllo sostegno
      if (!$avviso->getCattedra() ||$avviso->getCattedra()->getMateria()->getTipo() == 'S') {
        // errore: cattedra di sostegno
        $form->addError(new FormError($this->get('translator')->trans('exception.cattedra_sostegno')));
      }
      // controllo permessi
      if (!$age->azioneEvento(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($this->get('translator')->trans('exception.avviso_non_permesso')));
      }
      if (!$reg->azioneAnnotazione('add', $avviso->getData(), $this->getUser(), null, null)) {
        // errore: nuova annotazione non permessa
        $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_non_permessa')));
      }
      if (count($avviso->getAnnotazioni()) > 0) {
        $a = $avviso->getAnnotazioni()[0];
        if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
          // errore: cancellazione annotazione non permessa
          $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_non_permessa')));
        }
      }
      // modifica dati
      if ($form->isValid()) {
        // controllo verifiche esistenti
        $verifiche = $age->controlloVerifiche($avviso);
        $data_classe = $avviso->getData()->format('Y-m-d').'|'.$avviso->getCattedra()->getClasse()->getId();
        if (count($verifiche) > 0 && $session->get('/APP/ROUTE/agenda_verifica_edit/conferma', 0) != $data_classe) {
          // richiede conferma
          $session->set('/APP/ROUTE/agenda_verifica_edit/conferma', $data_classe);
        } else {
          // oggetto
          $avviso->setOggetto($this->get('translator')->trans('message.verifica_oggetto',
            ['%materia%' => $avviso->getCattedra()->getMateria()->getNomeBreve()]));
          // destinatari
          $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
            $val_destinatari, $val_filtro, $val_filtro_id);
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
          // log azione
          if (!$id) {
            // nuovo
            $dblogger->write($this->getUser(), $request->getClientIp(), 'EVENTI', 'Crea verifica', __METHOD__, array(
              'Avviso' => $avviso->getId(),
              'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
              'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                  return $a['genitore'].'->'.$a['alunno'];
                }, $log_destinatari['utenti']['add'])),
              'Annotazioni' => implode(', ', array_map(function ($a) {
                  return $a->getId();
                }, $avviso->getAnnotazioni()->toArray())),
              ));
          } else {
            // modifica
            $dblogger->write($this->getUser(), $request->getClientIp(), 'EVENTI', 'Modifica verifica', __METHOD__, array(
              'Id' => $avviso->getId(),
              'Data' => $avviso_old->getData()->format('d/m/Y'),
              'Testo' => $avviso_old->getTesto(),
              'Destinatari individuali' => $avviso_old->getDestinatariIndividuali(),
              'Classi cancellate' => implode(', ', $log_destinatari['classi']['delete']),
              'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
              'Utenti cancellati' => implode(', ', array_map(function ($a) {
                  return $a['genitore'].'->'.$a['alunno'];
                }, $log_destinatari['utenti']['delete'])),
              'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                  return $a['genitore'].'->'.$a['alunno'];
                }, $log_destinatari['utenti']['add'])),
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
   *    defaults={"id": 0})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function cattedraAjaxAction(EntityManagerInterface $em, $id) {
    $alunni = $em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select("a.id,CONCAT(a.cognome,' ',a.nome) AS nome")
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.classe=a.classe')
      ->where('a.abilitato=:abilitato AND c.id=:cattedra AND c.attiva=:attiva')
      ->setParameters(['abilitato' => 1, 'cattedra' => $id, 'attiva' => 1])
      ->orderBy('a.cognome,a.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return new JsonResponse($alunni);
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
   *    requirements={"id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function verificaDeleteAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger,
                                        RegistroUtil $reg, BachecaUtil $bac, AgendaUtil $age, $id) {
    // controllo avviso
    $avviso = $em->getRepository('AppBundle:Avviso')->findOneBy(['id' => $id, 'tipo' => 'V']);
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
    // log azione
    $dblogger->write($this->getUser(), $request->getClientIp(), 'EVENTI', 'Cancella verifica', __METHOD__, array(
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

}

