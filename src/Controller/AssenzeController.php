<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Assenza;
use App\Entity\Entrata;
use App\Entity\Presenza;
use App\Entity\Uscita;
use App\Form\AppelloType;
use App\Form\EntrataType;
use App\Form\PresenzaType;
use App\Form\UscitaType;
use App\Util\BachecaUtil;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * AssenzeController - gestione delle assenze
 *
 * @author Antonello Dessì
 */
class AssenzeController extends BaseController {

  /**
   * Mostra quadro delle assenze
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   * @param string $vista Tipo di vista del registro (giorno/settimana/mese)
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/quadro/{cattedra}/{classe}/{data}/{vista}/{posizione}", name="lezioni_assenze_quadro",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "vista": "G|S|M", "posizione": "\d+"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00", "vista": "G", "posizione": "0"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function quadroAction(Request $request, RegistroUtil $reg, BachecaUtil $bac,
                               int $cattedra, int $classe, string $data, string $vista, 
                               int $posizione): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $num_avvisi = 0;
    $lista_circolari = array();
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $data_succ = null;
    $data_prec = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data inizio e fine vista
    if ($vista == 'S') {
      // vista settimanale
      $data_inizio = clone $data_obj;
      $data_inizio->modify('this week');
      $data_fine = clone $data_inizio;
      $data_fine->modify('+5 days');
    } elseif ($vista == 'M') {
      // vista mensile
      $data_inizio = \DateTime::createFromFormat('Y-m-d', $data_obj->format('Y-m-01'));
      $data_fine = clone $data_inizio;
      $data_fine->modify('last day of this month');
    } else {
      // vista giornaliera
      $data_inizio = $data_obj;
      $data_fine = $data_obj;
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
      // informazioni necessarie
      $cattedra = null;
      $info['materia'] = $materia->getNomeBreve();
      $info['religione'] = false;
      $info['alunno'] = null;
    }
    if ($classe) {
      // data prec/succ
      $data_succ = (clone $data_fine);
      $data_succ = $this->em->getRepository('App\Entity\Festivita')->giornoSuccessivo($data_succ);
      $data_prec = (clone $data_inizio);
      $data_prec = $this->em->getRepository('App\Entity\Festivita')->giornoPrecedente($data_prec);
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if (!$errore) {
        // non festivo
        $dati = $reg->quadroAssenzeVista($data_inizio, $data_fine, $this->getUser(), $classe, $cattedra);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/assenze_quadro_'.$vista.'.html.twig', array(
      'pagina_titolo' => 'page.lezioni_assenze',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'data_inizio' => $data_inizio->format('d/m/Y'),
      'data_fine' => $data_fine->format('d/m/Y'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati,
      'avvisi' => $num_avvisi,
      'circolari' => $lista_circolari,
      'posizione' => $posizione,
    ));
  }

  /**
   * Inserisce o rimuove un'assenza
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $id Identificativo dell'assenza (se nullo crea nuova assenza)
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/assenza/{cattedra}/{classe}/{data}/{alunno}/{id}/{posizione}", name="lezioni_assenze_assenza",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "alunno": "\d+", "id": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": "0"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function assenzaAction(Request $request, RegistroUtil $reg, LogHandler $dblogger,
                                int $cattedra, int $classe, string $data, int $alunno, int $id, 
                                int $posizione): Response {
    // controlla cattedra
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla fc
    $presenza = $this->em->getRepository('App\Entity\Presenza')->findOneBy(['alunno' => $alunno,
      'data' => $data_obj]);
    if ($presenza) {
      // errore: esiste un fc
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla assenza
    if ($id > 0) {
      // assenza esistente
      $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['id' => $id,
        'alunno' => $alunno, 'data' => $data_obj]);
      if (!$assenza) {
        // assenza non esiste, niente da fare
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
      $this->em->remove($assenza);
    } else {
      // controlla se esiste assenza
      $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
      if ($assenza) {
        // assenza esiste già, niente da fare
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
      // inserisce nuova assenza
      $assenza = (new Assenza())
        ->setData($data_obj)
        ->setAlunno($alunno)
        ->setDocente($this->getUser());
      $this->em->persist($assenza);
      // controlla esistenza ritardo
      $entrata = $this->em->getRepository('App\Entity\Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
      if ($entrata) {
        // rimuove ritardo
        $id_entrata = $entrata->getId();
        $this->em->remove($entrata);
      }
      // controlla esistenza uscita
      $uscita = $this->em->getRepository('App\Entity\Uscita')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
      if ($uscita) {
        // rimuove uscita
        $id_uscita = $uscita->getId();
        $this->em->remove($uscita);
      }
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // ok: memorizza dati
    $this->em->flush();
    // ricalcola ore assenza
    $reg->ricalcolaOreAlunno($data_obj, $alunno);
    // log azione
    if ($id) {
      // log cancella assenza
      $dblogger->logAzione('ASSENZE', 'Cancella assenza', array(
        'Assenza' => $id,
        'Alunno' => $assenza->getAlunno()->getId(),
        'Data' => $assenza->getData()->format('Y-m-d'),
        'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
        'Docente' => $assenza->getDocente()->getId(),
        'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)
        ));
    } else {
      // log inserisce assenza
      $dblogger->logAzione('ASSENZE', 'Crea assenza', array(
        'Assenza' => $assenza->getId()
        ));
      if (isset($id_entrata)) {
        // log cancella ritardo
        $dblogger->logAzione('ASSENZE', 'Cancella entrata', array(
          'Entrata' => $id_entrata,
          'Alunno' => $entrata->getAlunno()->getId(),
          'Data' => $entrata->getData()->format('Y-m-d'),
          'Ora' => $entrata->getOra()->format('H:i'),
          'Note' => $entrata->getNote(),
          'Giustificato' => ($entrata->getGiustificato() ? $entrata->getGiustificato()->format('Y-m-d') : null),
          'Docente' => $entrata->getDocente()->getId(),
          'DocenteGiustifica' => ($entrata->getDocenteGiustifica() ? $entrata->getDocenteGiustifica()->getId() : null)
          ));
      }
      if (isset($id_uscita)) {
        // log cancella uscita
        $dblogger->logAzione('ASSENZE', 'Cancella uscita', array(
          'Uscita' => $id_uscita,
          'Alunno' => $uscita->getAlunno()->getId(),
          'Data' => $uscita->getData()->format('Y-m-d'),
          'Ora' => $uscita->getOra()->format('H:i'),
          'Note' => $uscita->getNote(),
          'Docente' => $uscita->getDocente()->getId()
          ));
       }
    }
    // redirezione
    return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
  }

  /**
   * Aggiunge, modifica o elimina un ritardo
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/entrata/{cattedra}/{classe}/{data}/{alunno}/{posizione}", name="lezioni_assenze_entrata",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function entrataAction(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                                LogHandler $dblogger, int $cattedra, int $classe, string $data, 
                                int $alunno, int $posizione): Response {
    // inizializza
    $label = array();
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data_obj, $classe->getSede());
    // controlla entrata
    $entrata = $this->em->getRepository('App\Entity\Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
    if ($entrata) {
      // edit
      $entrata_old['ora'] = $entrata->getOra();
      $entrata_old['note'] = $entrata->getNote();
      $entrata_old['valido'] = $entrata->getValido();
      $entrata_old['giustificato'] = $entrata->getGiustificato();
      $entrata_old['docente'] = $entrata->getDocente();
      $entrata_old['docenteGiustifica'] = $entrata->getDocenteGiustifica();
      // elimina giustificazione
      $entrata
        ->setDocente($this->getUser())
        ->setRitardoBreve(false)
        ->setGiustificato(null)
        ->setDocenteGiustifica(null);
    } else {
      // nuovo
      $entrata = (new Entrata())
        ->setData($data_obj)
        ->setAlunno($alunno)
        ->setValido(true)
        ->setDocente($this->getUser());
      // imposta ora
      $ora = new \DateTime();
      if ($data != date('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
          $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // data non odierna o ora attuale fuori da orario
        $ora = \DateTime::createFromFormat('H:i:s', $orario[0]['inizio']);
      }
      $entrata->setOra($ora);
      $this->em->persist($entrata);
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->createForm(EntrataType::class, $entrata, array('form_mode' => 'staff'));
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $presenza = $this->em->getRepository('App\Entity\Presenza')->findOneBy(['alunno' => $alunno,
        'data' => $data_obj]);
      if (!isset($entrata_old) && isset($request->request->get('entrata')['delete'])) {
        // ritardo non esiste, niente da fare
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      } elseif ($form->get('ora')->getData()->format('H:i:00') <= $orario[0]['inizio'] ||
                $form->get('ora')->getData()->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // ora fuori dai limiti
        $form->get('ora')->addError(new FormError($trans->trans('field.time', [], 'validators')));
      } elseif ($presenza && !$presenza->getOraInizio()) {
        // errore coerenza fc con entrata
        $form->addError(new FormError($trans->trans('exception.presenze_giorno_entrata_incoerente')));
      } elseif ($presenza && $presenza->getOraInizio() &&
                $presenza->getOraInizio() < $form->get('ora')->getData()) {
        // errore coerenza fc con orario entrata
        $form->addError(new FormError($trans->trans('exception.presenze_ritardo_incoerente')));
      } elseif ($form->isValid()) {
        if (isset($entrata_old) && isset($request->request->get('entrata')['delete'])) {
          // cancella ritardo esistente
          $id_entrata = $entrata->getId();
          $this->em->remove($entrata);
        } else {
          // controlla ritardo breve
          $inizio = \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 '.$orario[0]['inizio']);
          $inizio->modify('+' . $this->reqstack->getSession()->get('/CONFIG/SCUOLA/ritardo_breve', 0) . 'minutes');
          if ($form->get('ora')->getData() <= $inizio) {
            // ritardo breve: giustificazione automatica (non imposta docente)
            $entrata
              ->setRitardoBreve(true)
              ->setGiustificato($data_obj)
              ->setDocenteGiustifica(null)
              ->setValido(false);
          }
          // controlla se risulta assente
          $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
          if ($assenza) {
            // cancella assenza
            $id_assenza = $assenza->getId();
            $this->em->remove($assenza);
          }
        }
        // ok: memorizza dati
        $this->em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data_obj, $alunno);
        // log azione
        if (isset($entrata_old) && isset($request->request->get('entrata')['delete'])) {
          // log cancella
          $dblogger->logAzione('ASSENZE', 'Cancella entrata', array(
            'Entrata' => $id_entrata,
            'Alunno' => $entrata->getAlunno()->getId(),
            'Data' => $entrata->getData()->format('Y-m-d'),
            'Ora' => $entrata->getOra()->format('H:i'),
            'Note' => $entrata->getNote(),
            'Valido' => $entrata->getValido(),
            'Giustificato' => ($entrata->getGiustificato() ? $entrata->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $entrata->getDocente()->getId(),
            'DocenteGiustifica' => ($entrata->getDocenteGiustifica() ? $entrata->getDocenteGiustifica()->getId() : null)
            ));
        } elseif (isset($entrata_old)) {
          // log modifica
          $dblogger->logAzione('ASSENZE', 'Modifica entrata', array(
            'Entrata' => $entrata->getId(),
            'Ora' => $entrata_old['ora']->format('H:i'),
            'Note' => $entrata_old['note'],
            'Valido' => $entrata_old['valido'],
            'Giustificato' => ($entrata_old['giustificato'] ? $entrata_old['giustificato']->format('Y-m-d') : null),
            'Docente' => $entrata_old['docente']->getId(),
            'DocenteGiustifica' => ($entrata_old['docenteGiustifica'] ? $entrata_old['docenteGiustifica'] ->getId() : null)
            ));
        } else {
          // log nuovo
          $dblogger->logAzione('ASSENZE', 'Crea entrata', array(
            'Entrata' => $entrata->getId()
            ));
        }
        if (isset($id_assenza)) {
          // log cancella assenza
          $dblogger->logAzione('ASSENZE', 'Cancella assenza', array(
            'Assenza' => $id_assenza,
            'Alunno' => $assenza->getAlunno()->getId(),
            'Data' => $assenza->getData()->format('Y-m-d'),
            'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $assenza->getDocente()->getId(),
            'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)
            ));
        }
        // redirezione
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/entrata_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form->createView(),
      'form_title' => (isset($entrata_old) ? 'title.modifica_entrata' : 'title.nuova_entrata'),
      'label' => $label,
      'btn_delete' => isset($entrata_old),
      'posizione' => $posizione,
    ));
  }

  /**
   * Aggiunge, modifica o elimina un'usciata anticipata
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/uscita/{cattedra}/{classe}/{data}/{alunno}/{posizione}", name="lezioni_assenze_uscita",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function uscitaAction(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                               LogHandler $dblogger, int $cattedra, int $classe, string $data, 
                               int $alunno, int $posizione): Response {
    // inizializza
    $label = array();
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data_obj, $classe->getSede());
    // controlla uscita
    $uscita = $this->em->getRepository('App\Entity\Uscita')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
    if ($uscita) {
      // edit
      $uscita_old['ora'] = $uscita->getOra();
      $uscita_old['note'] = $uscita->getNote();
      $uscita_old['valido'] = $uscita->getValido();
      $uscita_old['giustificato'] = $uscita->getGiustificato();
      $uscita_old['docente'] = $uscita->getDocente();
      $uscita_old['docenteGiustifica'] = $uscita->getDocenteGiustifica();
      // elimina giustificazione
      $uscita
        ->setDocente($this->getUser())
        ->setGiustificato(null)
        ->setDocenteGiustifica(null);
    } else {
      // nuovo
      $uscita = (new Uscita())
        ->setData($data_obj)
        ->setAlunno($alunno)
        ->setValido(true)
        ->setDocente($this->getUser());
      // imposta ora
      $ora = new \DateTime();
      if ($data != date('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
          $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // data non odierna o ora attuale fuori da orario
        $ora = \DateTime::createFromFormat('H:i:s', $orario[count($orario) - 1]['fine']);
      }
      $uscita->setOra($ora);
      $this->em->persist($uscita);
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione().
      ($classe->getGruppo() ? ('-'.$classe->getGruppo()) : '');
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->createForm(UscitaType::class, $uscita);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $presenza = $this->em->getRepository('App\Entity\Presenza')->findOneBy(['alunno' => $alunno,
        'data' => $data_obj]);
      if (!isset($uscita_old) && isset($request->request->get('uscita')['delete'])) {
        // uscita non esiste, niente da fare
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      } elseif ($form->get('ora')->getData()->format('H:i:00') < $orario[0]['inizio'] ||
                $form->get('ora')->getData()->format('H:i:00') >= $orario[count($orario) - 1]['fine']) {
        // ora fuori dai limiti
        $form->get('ora')->addError(new FormError($trans->trans('field.time', [], 'validators')));
      } elseif ($presenza && !$presenza->getOraFine()) {
        // errore coerenza fc con uscita
        $form->addError(new FormError($trans->trans('exception.presenze_giorno_uscita_incoerente')));
      } elseif ($presenza && $presenza->getOraFine() &&
                $presenza->getOraFine() > $form->get('ora')->getData()) {
        // errore coerenza fc con orario uscita
        $form->addError(new FormError($trans->trans('exception.presenze_uscita_incoerente')));
      } elseif ($form->isValid()) {
        if (isset($uscita_old) && isset($request->request->get('uscita')['delete'])) {
          // cancella ritardo esistente
          $id_uscita = $uscita->getId();
          $this->em->remove($uscita);
        } else {
          // controlla se risulta assente
          $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
          if ($assenza) {
            // cancella assenza
            $id_assenza = $assenza->getId();
            $this->em->remove($assenza);
          }
        }
        // ok: memorizza dati
        $this->em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data_obj, $alunno);
        // log azione
        if (isset($uscita_old) && isset($request->request->get('uscita')['delete'])) {
          // cancella
          $dblogger->logAzione('ASSENZE', 'Cancella uscita', array(
            'Uscita' => $id_uscita,
            'Alunno' => $uscita->getAlunno()->getId(),
            'Data' => $uscita->getData()->format('Y-m-d'),
            'Ora' => $uscita->getOra()->format('H:i'),
            'Note' => $uscita->getNote(),
            'Valido' => $uscita->getValido(),
            'Giustificato' => ($uscita->getGiustificato() ? $uscita->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $uscita->getDocente()->getId(),
            'DocenteGiustifica' => ($uscita->getDocenteGiustifica() ? $uscita->getDocenteGiustifica()->getId() : null)
            ));
        } elseif (isset($uscita_old)) {
          // modifica
          $dblogger->logAzione('ASSENZE', 'Modifica uscita', array(
            'Uscita' => $uscita->getId(),
            'Ora' => $uscita_old['ora']->format('H:i'),
            'Note' => $uscita_old['note'],
            'Valido' => $uscita_old['valido'],
            'Giustificato' => ($uscita_old['giustificato'] ? $uscita_old['giustificato']->format('Y-m-d') : null),
            'Docente' => $uscita_old['docente']->getId(),
            'DocenteGiustifica' => ($uscita_old['docenteGiustifica'] ? $uscita_old['docenteGiustifica'] ->getId() : null)
            ));
        } else {
          // nuovo
          $dblogger->logAzione('ASSENZE', 'Crea uscita', array(
            'Uscita' => $uscita->getId()
            ));
        }
        if (isset($id_assenza)) {
          // cancella assenza
          $dblogger->logAzione('ASSENZE', 'Cancella assenza', array(
            'Assenza' => $id_assenza,
            'Alunno' => $assenza->getAlunno()->getId(),
            'Data' => $assenza->getData()->format('Y-m-d'),
            'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $assenza->getDocente()->getId(),
            'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)
            ));
        }
        // redirezione
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/uscita_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form->createView(),
      'form_title' => (isset($uscita_old) ? 'title.modifica_uscita' : 'title.nuova_uscita'),
      'label' => $label,
      'btn_delete' => isset($uscita_old),
      'posizione' => $posizione,
    ));
  }

  /**
   * Giustifica assenze e ritardi di un alunno
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/giustifica/{cattedra}/{classe}/{data}/{alunno}/{posizione}", name="lezioni_assenze_giustifica",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function giustificaAction(Request $request, RegistroUtil $reg, LogHandler $dblogger,
                                   int $cattedra, int $classe, string $data, int $alunno, 
                                   int $posizione): Response {
    // inizializza
    $label = array();
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->find($alunno);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // assenze da giustificare
    if ($this->reqstack->getSession()->get('/CONFIG/SCUOLA/assenze_ore')) {
      // modalità assenze orarie
      $giustifica = $reg->assenzeOreDaGiustificare($data_obj, $alunno, $classe);
      $obj = $this->em->getRepository('App\Entity\AssenzaLezione');
      $func_convalida = function($value, $key, $index) use($obj, $alunno) {
        $ore = $obj->alunnoOreAssenze($alunno, $value->data_obj);
        $ore_str = implode('ª, ', $ore).'ª';
        return '<strong>'.$value->data.(count($ore) > 0 ? (' - Ore: '.$ore_str) : '').'</strong>'.
          '<br>Motivazione: <em>'.$value->motivazione.'</em>'; };
      $func_assenze = function($value, $key, $index) use($obj, $alunno) {
        $ore = $obj->alunnoOreAssenze($alunno, $value->data_obj);
        $ore_str = implode('ª, ', $ore).'ª';
        return '<strong>'.$value->data.(count($ore) > 0 ? (' - Ore: '.$ore_str) : '').'</strong>'; };
    } else {
      // modalità assenze giornaliere
      $giustifica = $reg->assenzeRitardiDaGiustificare($data_obj, $alunno, $classe);
      $func_convalida = function ($value, $key, $index) {
        return $value->data.($value->giorni > 1 ? (' - '.$value->data_fine.' ('.$value->giorni.' giorni)') : '').
          '<br>Motivazione: <em>'.$value->motivazione.'</em>'; };
      $func_assenze = function ($value, $key, $index) {
        return $value->data.($value->giorni > 1 ? (' - '.$value->data_fine.' ('.$value->giorni.' giorni)') : ''); };
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('giustifica_edit', FormType::class)
      ->add('convalida_assenze', ChoiceType::class, array('label' => 'label.convalida_assenze',
        'choices' => $giustifica['convalida_assenze'],
        'choice_label' => $func_convalida,
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('convalida_ritardi', ChoiceType::class, array('label' => 'label.convalida_ritardi',
        'choices' => $giustifica['convalida_ritardi'],
        'choice_label' => function ($value, $key, $index) use ($settimana) {
            return '<strong>'.$settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y').
              ' ore '.$value->getOra()->format('H:i').'</strong><br>Motivazione: <em>'.$value->getMotivazione().'</em>';
          },
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('convalida_uscite', ChoiceType::class, array('label' => 'label.convalida_uscite',
        'choices' => $giustifica['convalida_uscite'],
        'choice_label' => function ($value, $key, $index) use ($settimana) {
            return '<strong>'.$settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y').
              ' ore '.$value->getOra()->format('H:i').'</strong><br>Motivazione: <em>'.$value->getMotivazione().'</em>';
          },
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('assenze', ChoiceType::class, array('label' => 'label.assenze',
        'choices' => $giustifica['assenze'],
        'choice_label' => $func_assenze,
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('ritardi', ChoiceType::class, array('label' => 'label.ritardi',
        'choices' => $giustifica['ritardi'],
        'choice_label' => function ($value, $key, $index) use ($settimana) {
            return $settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y').
              ' ore '.$value->getOra()->format('H:i');
          },
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('uscite', ChoiceType::class, array('label' => 'label.uscite_anticipate',
        'choices' => $giustifica['uscite'],
        'choice_label' => function ($value, $key, $index) use ($settimana) {
            return $settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y').
              ' ore '.$value->getOra()->format('H:i');
          },
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_assenze_quadro',
          ['posizione' => $posizione])."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // gruppi di assenze
      foreach ($form->get('assenze')->getData() as $ass) {
        $risultato = $this->em->getRepository('App\Entity\Assenza')->createQueryBuilder('ass')
          ->update()
          ->set('ass.modificato', ':modificato')
          ->set('ass.giustificato', ':giustificato')
          ->set('ass.docenteGiustifica', ':docenteGiustifica')
          ->where('ass.id in (:ids)')
          ->setParameters(['modificato' => new \DateTime(), 'giustificato' => $data_obj,
            'docenteGiustifica' => $this->getUser(), 'ids' => explode(',', $ass->ids)])
          ->getQuery()
          ->getResult();
      }
      foreach ($form->get('convalida_assenze')->getData() as $ass) {
        $risultato = $this->em->getRepository('App\Entity\Assenza')->createQueryBuilder('ass')
          ->update()
          ->set('ass.modificato', ':modificato')
          ->set('ass.docenteGiustifica', ':docenteGiustifica')
          ->where('ass.id in (:ids)')
          ->setParameters(['modificato' => new \DateTime(), 'docenteGiustifica' => $this->getUser(),
            'ids' => explode(',', $ass->ids)])
          ->getQuery()
          ->getResult();
      }
      // ritardi
      foreach ($form->get('ritardi')->getData() as $rit) {
        $rit
          ->setGiustificato($data_obj)
          ->setDocenteGiustifica($this->getUser());
      }
      foreach ($form->get('convalida_ritardi')->getData() as $rit) {
        $rit
          ->setDocenteGiustifica($this->getUser());
      }
      // uscite
      foreach ($form->get('uscite')->getData() as $usc) {
        $usc
          ->setGiustificato($data_obj)
          ->setDocenteGiustifica($this->getUser());
      }
      foreach ($form->get('convalida_uscite')->getData() as $usc) {
        $usc
          ->setDocenteGiustifica($this->getUser());
      }
      // ok: memorizza dati
      $this->em->flush();
      // log azione
      if (count($form->get('assenze')->getData()) + count($form->get('ritardi')->getData()) + count($form->get('uscite')->getData()) > 0) {
        $dblogger->logAzione('ASSENZE', 'Giustifica', array(
          'Assenze' => implode(', ', array_map(function ($a) { return $a->ids; }, $form->get('assenze')->getData())),
          'Ritardi' => implode(', ', array_map(function ($r) { return $r->getId(); }, $form->get('ritardi')->getData())),
          'Uscite' => implode(', ', array_map(function ($u) { return $u->getId(); }, $form->get('uscite')->getData()))));
      }
      if (count($form->get('convalida_assenze')->getData()) + count($form->get('convalida_ritardi')->getData()) + count($form->get('convalida_uscite')->getData()) > 0) {
        $dblogger->logAzione('ASSENZE', 'Convalida', array(
          'Assenze' => implode(', ', array_map(function ($a) { return $a->ids; }, $form->get('convalida_assenze')->getData())),
          'Ritardi' => implode(', ', array_map(function ($r) { return $r->getId(); }, $form->get('convalida_ritardi')->getData())),
          'Uscite' => implode(', ', array_map(function ($u) { return $u->getId(); }, $form->get('convalida_uscite')->getData()))));
      }
      // redirezione
      return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/giustifica_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form->createView(),
      'form_title' => 'title.giustifica',
      'label' => $label,
      'giustificazioni' => $giustifica['tot_giustificazioni'],
      'convalide' => $giustifica['tot_convalide'],
      'alunno' => $alunno
    ));
  }

  /**
   * Gestione dell'appello
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/appello/{cattedra}/{classe}/{data}", name="lezioni_assenze_appello",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function appelloAction(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                                LogHandler $dblogger, int $cattedra, int $classe, 
                                string $data): Response {
    // inizializza
    $label = array();
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), null, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // elenco di alunni per l'appello
    $religione = ($cattedra && $cattedra->getMateria()->getTipo() == 'R' && $cattedra->getTipo() == 'A') ? 'A' :
      ($cattedra && $cattedra->getMateria()->getTipo() == 'R' ? 'S' : '');
    list($elenco, $listaFC, $noAppello) = $reg->elencoAppello($data_obj, $classe, $religione);
    // controlla funzione
    if ($noAppello) {
      // errore: funzione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('assenze_appello', FormType::class)
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $elenco,
        'entry_type' => AppelloType::class,
        'entry_options' => array('label' => false),
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary gs-mr-3']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_assenze_quadro')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta assenze/ritardi
      $log['assenza_create'] = array();
      $log['assenza_delete'] = array();
      $log['entrata_create'] = array();
      $log['entrata_edit'] = array();
      $log['entrata_delete'] = array();
      $log['uscita_delete'] = array();
      $orario = $reg->orarioInData($data_obj, $classe->getSede());
      $alunni_assenza = array();
      foreach ($form->get('lista')->getData() as $key=>$appello) {
        $alunno = $this->em->getRepository('App\Entity\Alunno')->find($appello->getId());
        if (!$alunno) {
          // alunno non esiste, salta
          continue;
        }
        $alunni_assenza[] = $alunno;
        switch ($appello->getPresenza()) {
          case 'A':   // assente
            // controlla se assenza esiste
            $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if (!$assenza) {
              // inserisce nuova assenza
              $assenza = (new Assenza())
                ->setData($data_obj)
                ->setAlunno($alunno)
                ->setDocente($this->getUser());
              $this->em->persist($assenza);
              $log['assenza_create'][] = $assenza;
            }
            // controlla esistenza ritardo
            $entrata = $this->em->getRepository('App\Entity\Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($entrata) {
              // rimuove ritardo
              $log['entrata_delete'][] = array($entrata->getId(), $entrata);
              $this->em->remove($entrata);
            }
            // controlla esistenza uscita
            $uscita = $this->em->getRepository('App\Entity\Uscita')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($uscita) {
              // rimuove uscita
              $log['uscita_delete'][] = array($uscita->getId(), $uscita);
              $this->em->remove($uscita);
            }
            // controlla fc
            $presenza = $this->em->getRepository('App\Entity\Presenza')->findOneBy(['alunno' => $alunno,
              'data' => $data_obj]);
            if ($presenza) {
              // errore: esiste un fc
              throw $this->createNotFoundException('exception.id_notfound');
            }
            break;
          case 'P':   // presente
            // controlla esistenza assenza
            $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($assenza) {
              // rimuove assenza
              $log['assenza_delete'][] = array($assenza->getId(), $assenza);
              $this->em->remove($assenza);
            }
            // controlla esistenza ritardo
            $entrata = $this->em->getRepository('App\Entity\Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($entrata) {
              // rimuove ritardo
              $log['entrata_delete'][] = array($entrata->getId(), $entrata);
              $this->em->remove($entrata);
            }
            // controlla esistenza uscita
            $uscita = $this->em->getRepository('App\Entity\Uscita')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($uscita) {
              // rimuove uscita
              $log['uscita_delete'][] = array($uscita->getId(), $uscita);
              $this->em->remove($uscita);
            }
            break;
          //-- case 'R':   // ritardo
            //-- // validazione orario
            //-- if ($appello->getOra()->format('H:i:00') <= $orario[0]['inizio'] ||
                //-- $appello->getOra()->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
              //-- // errore su orario
              //-- $form->get('lista')[$key]->get('ora')->addError(new FormError($trans->trans('field.time', [], 'validators')));
              //-- continue 2;
            //-- }
            //-- // controlla esistenza ritardo
            //-- $entrata = $this->em->getRepository('App\Entity\Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            //-- if ($entrata) {
              //-- if ($entrata->getOra()->format('H:i') != $appello->getOra()->format('H:i')) {
                //-- // modifica
                //-- $log['entrata_edit'][] = array($entrata->getId(), $entrata->getAlunno()->getId(),
                  //-- $entrata->getOra()->format('H:i'), $entrata->getNote(), $entrata->getGiustificato(),
                  //-- $entrata->getDocente()->getId(), $entrata->getDocenteGiustifica());
                //-- $entrata
                  //-- ->setOra($appello->getOra())
                  //-- ->setDocente($this->getUser())
                  //-- ->setRitardoBreve(false)
                  //-- ->setGiustificato(null)
                  //-- ->setDocenteGiustifica(null);
                //-- // controlla ritardo breve
                //-- $inizio = \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 '.$orario[0]['inizio']);
                //-- $inizio->modify('+' . $this->reqstack->getSession()->get('/CONFIG/SCUOLA/ritardo_breve', 0) . 'minutes');
                //-- if ($appello->getOra() <= $inizio) {
                  //-- // ritardo breve: giustificazione automatica (non imposta docente)
                  //-- $entrata
                    //-- ->setRitardoBreve(true)
                    //-- ->setGiustificato($data_obj)
                    //-- ->setDocenteGiustifica(null)
                    //-- ->setValido(false);
                //-- }
              //-- }
            //-- } else {
              //-- // inserisce ritardo
              //-- $entrata = (new Entrata())
                //-- ->setData($data_obj)
                //-- ->setAlunno($alunno)
                //-- ->setDocente($this->getUser())
                //-- ->setOra($appello->getOra())
                //-- ->setValido(false);
              //-- // controlla ritardo breve
              //-- $inizio = \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 '.$orario[0]['inizio']);
              //-- $inizio->modify('+' . $this->reqstack->getSession()->get('/CONFIG/SCUOLA/ritardo_breve', 0) . 'minutes');
              //-- if ($appello->getOra() <= $inizio) {
                //-- // ritardo breve: giustificazione automatica (non imposta docente)
                //-- $entrata
                  //-- ->setRitardoBreve(true)
                  //-- ->setGiustificato($data_obj)
                  //-- ->setDocenteGiustifica(null);
              //-- }
              //-- $this->em->persist($entrata);
              //-- $log['entrata_create'][] = $entrata;
            //-- }
            //-- // controlla esistenza assenza
            //-- $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            //-- if ($assenza) {
              //-- // rimuove assenza
              //-- $log['assenza_delete'][] = array($assenza->getId(), $assenza);
              //-- $this->em->remove($assenza);
            //-- }
            //-- break;
        }
      }
      if ($form->isValid()) {
        // ok: memorizza dati
        $this->em->flush();
        // ricalcola ore assenze
        foreach ($alunni_assenza as $alu) {
          $reg->ricalcolaOreAlunno($data_obj, $alu);
        }
        // log azione
        $dblogger->logAzione('ASSENZE', 'Appello', array(
          'Data' => $data,
          'Assenze create' => implode(', ', array_map(function ($e) {
              return $e->getId();
            }, $log['assenza_create'])),
          'Assenze cancellate' => implode(', ', array_map(function ($e) {
              return '[Assenza: '.$e[0].', Alunno: '.$e[1]->getAlunno()->getId().
                ', Giustificato: '.($e[1]->getGiustificato() ? $e[1]->getGiustificato()->format('Y-m-d') : '').
                ', Docente: '.$e[1]->getDocente()->getId().
                ', DocenteGiustifica: '.($e[1]->getDocenteGiustifica() ? $e[1]->getDocenteGiustifica()->getId() : '').']';
            }, $log['assenza_delete'])),
          'Entrate create' => implode(', ', array_map(function ($e) {
              return $e->getId();
            }, $log['entrata_create'])),
          'Entrate modificate' => implode(', ', array_map(function ($e) {
              return '[Entrata: '.$e[0].', Alunno: '.$e[1].', Ora: '.$e[2].
                ', Note: "'.$e[3].'"'.
                ', Giustificato: '.($e[4] ? $e[4]->format('Y-m-d') : '').
                ', Docente: '.$e[5].
                ', DocenteGiustifica: '.($e[6] ? $e[6]->getId() : '').']';
            }, $log['entrata_edit'])),
          'Entrate cancellate' => implode(', ', array_map(function ($e) {
              return '[Entrata: '.$e[0].', Alunno: '.$e[1]->getAlunno()->getId().
                ', Ora: '.$e[1]->getOra()->format('H:i').
                ', Note: "'.$e[1]->getNote().'"'.
                ', Giustificato: '.($e[1]->getGiustificato() ? $e[1]->getGiustificato()->format('Y-m-d') : '').
                ', Docente: '.$e[1]->getDocente()->getId().
                ', DocenteGiustifica: '.($e[1]->getDocenteGiustifica() ? $e[1]->getDocenteGiustifica()->getId() : '').']';
            }, $log['entrata_delete'])),
          'Uscite cancellate' => implode(', ', array_map(function ($e) {
              return '[Uscita: '.$e[0].', Alunno: '.$e[1]->getAlunno()->getId().
                ', Ora: '.$e[1]->getOra()->format('H:i').
                ', Note: "'.$e[1]->getNote().'"'.
                ', Docente: '.$e[1]->getDocente()->getId();
            }, $log['uscita_delete']))
          ));
        // redirezione
        return $this->redirectToRoute('lezioni_assenze_quadro');
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/appello.html.twig', array(
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form->createView(),
      'form_title' => 'title.appello',
      'label' => $label,
      'dati' => $listaFC,
    ));
  }

  /**
   * Inserice, modifica o cancella una presenza fuori classe
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $id Identificativo della presenza fuori classe
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/fuoriclasse/{classe}/{data}/{alunno}/{id}/{posizione}", name="lezioni_assenze_fuoriclasse",
   *    requirements={"classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "alunno": "\d+", "id": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function fuoriclasseAction(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                                    LogHandler $dblogger, int $classe, string $data, int $alunno,
                                    int $id, int $posizione): Response {
    // init
    $dati = [];
    $info = [];
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno,
      'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla presenza
    if ($id > 0) {
      // presenza esistente
      $presenza = $this->em->getRepository('App\Entity\Presenza')->findOneBy(['id' => $id,
        'alunno' => $alunno, 'data' => $data_obj]);
      if (!$presenza) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $vecchiaPresenza = clone $presenza;
    } else {
      // inserisce nuova presenza
      $presenza = (new Presenza())
        ->setData($data_obj)
        ->setAlunno($alunno);
      $this->em->persist($presenza);
    }
    // imposta informazioni
    $info['classe'] = $classe;
    $info['data'] = $data_obj;
    $info['alunno'] = $alunno;
    $info['delete'] = ($id > 0);
    $info['posizione'] = $posizione;
    // form
    $form = $this->createForm(PresenzaType::class, $presenza, [
      'return_url' => $this->generateUrl('lezioni_assenze_quadro', ['posizione' => $posizione]),
      'form_mode' => 'registro']);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      if (isset($vecchiaPresenza) && isset($request->request->get('presenza')['delete'])) {
        // cancella presenza esistente
        $this->em->remove($presenza);
      } else {
        // controlla dati
        if (($form->get('oraTipo')->getData() == 'G' && (!empty($form->get('oraInizio')->getData()) ||
            !empty($form->get('oraFine')->getData()))) ||
            ($form->get('oraTipo')->getData() == 'F' && !empty($form->get('oraFine')->getData()))) {
          // errore tipo con dati errati
          $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_errato')));
        } elseif (($form->get('oraTipo')->getData() == 'F' && empty($form->get('oraInizio')->getData())) ||
            ($form->get('oraTipo')->getData() == 'I' && empty($form->get('oraInizio')->getData())) ||
            ($form->get('oraTipo')->getData() == 'I' && empty($form->get('oraFine')->getData()))) {
          // errore tipo con dati mancanti
          $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_mancante')));
        } elseif ($form->get('oraTipo')->getData() == 'I' &&
            $form->get('oraInizio')->getData() > $form->get('oraFine')->getData()) {
          // errore tipo con dati mancanti
          $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_errato')));
        }
        // controlla permessi
        if (!$reg->azionePresenze($data_obj, $this->getUser(), $alunno, $classe)) {
          // errore: azione non permessa
          $form->addError(new FormError($trans->trans('exception.presenze_azione_non_permessa')));
        }
        // controllo coerenza
        $assenze = $this->em->getRepository('App\Entity\Alunno')->assenzeInData($alunno, $data_obj);
        if ($assenze['id_assenza'] > 0) {
          // errore: assenza con fuori classe
          $form->addError(new FormError($trans->trans('exception.presenze_assenza_incoerente')));
        }
        if ($assenze['id_entrata'] > 0 && $presenza->getOraInizio() &&
            $assenze['ora_entrata'] > $presenza->getOraInizio()) {
          // errore: entrata successiva a inizio
          $form->addError(new FormError($trans->trans('exception.presenze_ritardo_incoerente')));
        }
        if ($assenze['id_uscita'] > 0 && $presenza->getOraFine() &&
            $assenze['ora_uscita'] < $presenza->getOraFine()) {
          // errore: uscita precedente a fine
          $form->addError(new FormError($trans->trans('exception.presenze_uscita_incoerente')));
        }
        if (($form->get('oraTipo')->getData() == 'G' &&
            ($assenze['id_entrata'] > 0 || $assenze['id_uscita'])) ||
            ($form->get('oraTipo')->getData() == 'F' && $assenze['id_uscita'])) {
          // errore: entrata o uscita con tipo fuori classe incoerente
          $form->addError(new FormError($trans->trans('exception.presenze_tipo_incoerente')));
        }
      }
      if ($form->isValid()) {
        // ok: memorizzazione e log
        if (isset($vecchiaPresenza) && isset($request->request->get('presenza')['delete'])) {
          // log cancella
          $dblogger->logRimozione('PRESENZE', 'Cancella presenza', $vecchiaPresenza);
        } elseif (isset($vecchiaPresenza)) {
          // log modifica
          $dblogger->logModifica('PRESENZE', 'Modifica presenza', $vecchiaPresenza, $presenza);
        } else {
          // log inserimento
          $dblogger->logCreazione('PRESENZE', 'Aggiunge presenza', $presenza);
        }
        // redirect
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('lezioni', 'assenze_fuoriclasse', $dati, $info, [$form->createView(),
      'message.required_fields']);
  }

}
