<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use DateTime;
use IntlDateFormatter;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Materia;
use App\Entity\Festivita;
use App\Entity\Alunno;
use App\Entity\Assenza;
use App\Entity\Entrata;
use App\Entity\Presenza;
use App\Entity\Uscita;
use App\Form\AppelloType;
use App\Form\EntrataType;
use App\Form\PresenzaType;
use App\Form\UscitaType;
// use App\Util\BachecaUtil;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
   * @param int $classe Identificativo della classe (sostituzione)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   * @param string $vista Tipo di vista del registro (giornaliera/mensile)
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/assenze/quadro/{cattedra}/{classe}/{data}/{vista}/{posizione}', name: 'lezioni_assenze_quadro', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d', 'vista' => 'G|M', 'posizione' => '\d+'], defaults: ['cattedra' => 0, 'classe' => 0, 'data' => '0000-00-00', 'vista' => 'G', 'posizione' => '0'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function quadro(Request $request, RegistroUtil $reg, BachecaUtil $bac,
                         int $cattedra, int $classe, string $data, string $vista,
                         int $posizione): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $info = [];
    $dati = [];
    $dati['filtro']['S'] = [];
    $dati['filtro']['A'] = [];
    $dati['filtro']['N'] = [];
    $num_avvisi = 0;
    $lista_circolari = [];
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
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data inizio e fine vista
    if ($vista == 'M') {
      // vista mensile
      $data_inizio = DateTime::createFromFormat('Y-m-d', $data_obj->format('Y-m-01'));
      $data_fine = clone $data_inizio;
      $data_fine->modify('last day of this month');
    } else {
      // vista giornaliera
      $data_inizio = $data_obj;
      $data_fine = $data_obj;
    }
    // controllo cattedra/sostituzione
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
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
      // sostituzione
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $this->em->getRepository(Materia::class)->findOneByTipo('U');
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
      $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_succ);
      $data_prec = (clone $data_inizio);
      $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_prec);
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
    return $this->render('lezioni/assenze_quadro_'.$vista.'.html.twig', [
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
      'posizione' => $posizione]);
  }

  /**
   * Inserisce o rimuove un'assenza
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se sostituzione)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $id Identificativo dell'assenza (se nullo crea nuova assenza)
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/assenze/assenza/{cattedra}/{classe}/{data}/{alunno}/{id}/{posizione}', name: 'lezioni_assenze_assenza', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d', 'alunno' => '\d+', 'id' => '\d+', 'posizione' => '\d+'], defaults: ['posizione' => '0'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function assenza(RegistroUtil $reg, LogHandler $dblogger,
                          int $cattedra, int $classe, string $data, int $alunno, int $id,
                          int $posizione): Response {
    // controlla cattedra
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository(Cattedra::class)->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // sostituzione
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla fc
    $presenza = $this->em->getRepository(Presenza::class)->findOneBy(['alunno' => $alunno,
      'data' => $data_obj]);
    if ($presenza) {
      // errore: esiste un fc
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla assenza
    if ($id > 0) {
      // assenza esistente
      $assenza = $this->em->getRepository(Assenza::class)->findOneBy(['id' => $id,
        'alunno' => $alunno, 'data' => $data_obj]);
      if (!$assenza) {
        // assenza non esiste, niente da fare
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
      $this->em->remove($assenza);
    } else {
      // controlla se esiste assenza
      $assenza = $this->em->getRepository(Assenza::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
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
      // legge assenze precedenti e annulla giustificazione se consecutive
      $assenzePrec = $this->em->getRepository(Assenza::class)->createQueryBuilder('ass')
        ->where('ass.alunno=:alunno AND ass.data<:data')
        ->orderBy('ass.data', 'DESC')
        ->setParameter('alunno', $alunno)
        ->setParameter('data', $data)
        ->getQuery()
        ->getResult();
      $dataConsecutiva = clone $data_obj;
      foreach ($assenzePrec as $assenza) {
        $dataConsecutiva = $this->em->getRepository(Festivita::class)->giornoPrecedente($dataConsecutiva);
        if ($assenza->getData()->format('Y-m-d') === $dataConsecutiva->format('Y-m-d')) {
          // assenza consecutiva: annulla giustificazione
          $assenza->setGiustificato(null);
          $assenza->setUtenteGiustifica(null);
          $assenza->setDocenteGiustifica(null);
        } else {
          // fine giorni consecutivi di assenza
          break;
        }
      }
      // legge assenze successive e annulla giustificazione se consecutive
      $assenzeSucc = $this->em->getRepository(Assenza::class)->createQueryBuilder('ass')
        ->where('ass.alunno=:alunno AND ass.data>:data')
        ->orderBy('ass.data', 'ASC')
        ->setParameter('alunno', $alunno)
        ->setParameter('data', $data)
        ->getQuery()
        ->getResult();
      $dataConsecutiva = clone $data_obj;
      foreach ($assenzeSucc as $assenza) {
        $dataConsecutiva = $this->em->getRepository(Festivita::class)->giornoSuccessivo($dataConsecutiva);
        if ($assenza->getData()->format('Y-m-d') === $dataConsecutiva->format('Y-m-d')) {
          // assenza consecutiva: annulla giustificazione
          $assenza->setGiustificato(null);
          $assenza->setUtenteGiustifica(null);
          $assenza->setDocenteGiustifica(null);
        } else {
          // fine giorni consecutivi di assenza
          break;
        }
      }
      // controlla esistenza ritardo
      $entrata = $this->em->getRepository(Entrata::class)->findOneBy(['alunno' => $alunno,
	      'data' => $data_obj]);
      if ($entrata) {
        // rimuove ritardo
        $id_entrata = $entrata->getId();
        $this->em->remove($entrata);
      }
      // controlla esistenza uscita
      $uscita = $this->em->getRepository(Uscita::class)->findOneBy(['alunno' => $alunno,
	      'data' => $data_obj]);
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
      $dblogger->logAzione('ASSENZE', 'Cancella assenza', [
        'Assenza' => $id,
        'Alunno' => $assenza->getAlunno()->getId(),
        'Data' => $assenza->getData()->format('Y-m-d'),
        'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
        'Docente' => $assenza->getDocente()->getId(),
        'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)]);
    } else {
      // log inserisce assenza
      $dblogger->logAzione('ASSENZE', 'Crea assenza', [
        'Assenza' => $assenza->getId()]);
      if (isset($id_entrata)) {
        // log cancella ritardo
        $dblogger->logAzione('ASSENZE', 'Cancella entrata', [
          'Entrata' => $id_entrata,
          'Alunno' => $entrata->getAlunno()->getId(),
          'Data' => $entrata->getData()->format('Y-m-d'),
          'Ora' => $entrata->getOra()->format('H:i'),
          'Note' => $entrata->getNote(),
          'Giustificato' => ($entrata->getGiustificato() ? $entrata->getGiustificato()->format('Y-m-d') : null),
          'Docente' => $entrata->getDocente()->getId(),
          'DocenteGiustifica' => ($entrata->getDocenteGiustifica() ? $entrata->getDocenteGiustifica()->getId() : null)]);
      }
      if (isset($id_uscita)) {
        // log cancella uscita
        $dblogger->logAzione('ASSENZE', 'Cancella uscita', [
          'Uscita' => $id_uscita,
          'Alunno' => $uscita->getAlunno()->getId(),
          'Data' => $uscita->getData()->format('Y-m-d'),
          'Ora' => $uscita->getOra()->format('H:i'),
          'Note' => $uscita->getNote(),
          'Docente' => $uscita->getDocente()->getId()]);
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
   * @param int $cattedra Identificativo della cattedra (nullo se sostituzione)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/assenze/entrata/{cattedra}/{classe}/{data}/{alunno}/{posizione}', name: 'lezioni_assenze_entrata', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d', 'alunno' => '\d+', 'posizione' => '\d+'], defaults: ['posizione' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function entrata(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                          LogHandler $dblogger, int $cattedra, int $classe, string $data,
                          int $alunno, int $posizione): Response {
    // inizializza
    $label = [];
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository(Cattedra::class)->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // sostituzione
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data_obj, $classe->getSede());
    // controlla entrata
    $entrata = $this->em->getRepository(Entrata::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
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
        ->setValido(false)
        ->setDocente($this->getUser());
      // imposta ora
      $ora = new DateTime();
      if ($data != date('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
          $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // data non odierna o ora attuale fuori da orario
        $ora = DateTime::createFromFormat('H:i:s', $orario[0]['inizio']);
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
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$classe;
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->createForm(EntrataType::class, $entrata, ['form_mode' => 'staff']);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $presenza = $this->em->getRepository(Presenza::class)->findOneBy(['alunno' => $alunno,
        'data' => $data_obj]);
      $mode = isset($request->request->all()['entrata']['delete']) ? 'DELETE' : 'EDIT';
      if (!isset($entrata_old) && $mode == 'DELETE') {
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
        if (isset($entrata_old) && $mode == 'DELETE') {
          // cancella ritardo esistente
          $id_entrata = $entrata->getId();
          $this->em->remove($entrata);
        } else {
          // controlla ritardo breve
          $inizio = DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 '.$orario[0]['inizio']);
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
          $assenza = $this->em->getRepository(Assenza::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
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
        if (isset($entrata_old) && $mode == 'DELETE') {
          // log cancella
          $dblogger->logAzione('ASSENZE', 'Cancella entrata', [
            'Entrata' => $id_entrata,
            'Alunno' => $entrata->getAlunno()->getId(),
            'Data' => $entrata->getData()->format('Y-m-d'),
            'Ora' => $entrata->getOra()->format('H:i'),
            'Note' => $entrata->getNote(),
            'Valido' => $entrata->getValido(),
            'Giustificato' => ($entrata->getGiustificato() ? $entrata->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $entrata->getDocente()->getId(),
            'DocenteGiustifica' => ($entrata->getDocenteGiustifica() ? $entrata->getDocenteGiustifica()->getId() : null)]);
        } elseif (isset($entrata_old)) {
          // log modifica
          $dblogger->logAzione('ASSENZE', 'Modifica entrata', [
            'Entrata' => $entrata->getId(),
            'Ora' => $entrata_old['ora']->format('H:i'),
            'Note' => $entrata_old['note'],
            'Valido' => $entrata_old['valido'],
            'Giustificato' => ($entrata_old['giustificato'] ? $entrata_old['giustificato']->format('Y-m-d') : null),
            'Docente' => $entrata_old['docente']->getId(),
            'DocenteGiustifica' => ($entrata_old['docenteGiustifica'] ? $entrata_old['docenteGiustifica'] ->getId() : null)]);
        } else {
          // log nuovo
          $dblogger->logAzione('ASSENZE', 'Crea entrata', [
            'Entrata' => $entrata->getId()]);
        }
        if (isset($id_assenza)) {
          // log cancella assenza
          $dblogger->logAzione('ASSENZE', 'Cancella assenza', [
            'Assenza' => $id_assenza,
            'Alunno' => $assenza->getAlunno()->getId(),
            'Data' => $assenza->getData()->format('Y-m-d'),
            'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $assenza->getDocente()->getId(),
            'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)]);
        }
        // redirezione
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/entrata_edit.html.twig', [
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form,
      'form_title' => (isset($entrata_old) ? 'title.modifica_entrata' : 'title.nuova_entrata'),
      'label' => $label,
      'btn_delete' => isset($entrata_old),
      'posizione' => $posizione]);
  }

  /**
   * Aggiunge, modifica o elimina un'usciata anticipata
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se sostituzione)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/assenze/uscita/{cattedra}/{classe}/{data}/{alunno}/{posizione}', name: 'lezioni_assenze_uscita', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d', 'alunno' => '\d+', 'posizione' => '\d+'], defaults: ['posizione' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function uscita(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                         LogHandler $dblogger, int $cattedra, int $classe, string $data,
                         int $alunno, int $posizione): Response {
    // inizializza
    $label = [];
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository(Cattedra::class)->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // sostituzione
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data_obj, $classe->getSede());
    // controlla uscita
    $uscita = $this->em->getRepository(Uscita::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
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
        ->setValido(false)
        ->setDocente($this->getUser());
      // imposta ora
      $ora = new DateTime();
      if ($data != date('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
          $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // data non odierna o ora attuale fuori da orario
        $ora = DateTime::createFromFormat('H:i:s', $orario[count($orario) - 1]['fine']);
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
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$classe;
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->createForm(UscitaType::class, $uscita);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $presenza = $this->em->getRepository(Presenza::class)->findOneBy(['alunno' => $alunno,
        'data' => $data_obj]);
      $mode = isset($request->request->all()['uscita']['delete']) ? 'DELETE' : 'EDIT';
      if (!isset($uscita_old) && $mode == 'DELETE') {
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
        if (isset($uscita_old) && $mode == 'DELETE') {
          // cancella ritardo esistente
          $id_uscita = $uscita->getId();
          $this->em->remove($uscita);
        } else {
          // controlla se risulta assente
          $assenza = $this->em->getRepository(Assenza::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
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
        if (isset($uscita_old) && $mode == 'DELETE') {
          // cancella
          $dblogger->logAzione('ASSENZE', 'Cancella uscita', [
            'Uscita' => $id_uscita,
            'Alunno' => $uscita->getAlunno()->getId(),
            'Data' => $uscita->getData()->format('Y-m-d'),
            'Ora' => $uscita->getOra()->format('H:i'),
            'Note' => $uscita->getNote(),
            'Valido' => $uscita->getValido(),
            'Giustificato' => ($uscita->getGiustificato() ? $uscita->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $uscita->getDocente()->getId(),
            'DocenteGiustifica' => ($uscita->getDocenteGiustifica() ? $uscita->getDocenteGiustifica()->getId() : null)]);
        } elseif (isset($uscita_old)) {
          // modifica
          $dblogger->logAzione('ASSENZE', 'Modifica uscita', [
            'Uscita' => $uscita->getId(),
            'Ora' => $uscita_old['ora']->format('H:i'),
            'Note' => $uscita_old['note'],
            'Valido' => $uscita_old['valido'],
            'Giustificato' => ($uscita_old['giustificato'] ? $uscita_old['giustificato']->format('Y-m-d') : null),
            'Docente' => $uscita_old['docente']->getId(),
            'DocenteGiustifica' => ($uscita_old['docenteGiustifica'] ? $uscita_old['docenteGiustifica'] ->getId() : null)]);
        } else {
          // nuovo
          $dblogger->logAzione('ASSENZE', 'Crea uscita', [
            'Uscita' => $uscita->getId()]);
        }
        if (isset($id_assenza)) {
          // cancella assenza
          $dblogger->logAzione('ASSENZE', 'Cancella assenza', [
            'Assenza' => $id_assenza,
            'Alunno' => $assenza->getAlunno()->getId(),
            'Data' => $assenza->getData()->format('Y-m-d'),
            'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $assenza->getDocente()->getId(),
            'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)]);
        }
        // redirezione
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/uscita_edit.html.twig', [
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form,
      'form_title' => (isset($uscita_old) ? 'title.modifica_uscita' : 'title.nuova_uscita'),
      'label' => $label,
      'btn_delete' => isset($uscita_old),
      'posizione' => $posizione]);
  }

  /**
   * Giustifica assenze e ritardi di un alunno
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se sostituzione)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/assenze/giustifica/{cattedra}/{classe}/{data}/{alunno}/{posizione}', name: 'lezioni_assenze_giustifica', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d', 'alunno' => '\d+', 'posizione' => '\d+'], defaults: ['posizione' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function giustifica(Request $request, RegistroUtil $reg, LogHandler $dblogger,
                             int $cattedra, int $classe, string $data, int $alunno,
                             int $posizione): Response {
    // inizializza
    $label = [];
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository(Cattedra::class)->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // sostituzione
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $this->em->getRepository(Alunno::class)->find($alunno);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // modalità assenze giornaliere
    $giustifica = $reg->assenzeRitardiDaGiustificare($data_obj, $alunno, $classe);
    // dati in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$classe;
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('giustifica_edit', FormType::class)
      ->add('convalida_assenze', ChoiceType::class, ['label' => 'label.convalida_assenze',
        'choices' => $giustifica['convalida_assenze'],
        'choice_label' => fn($value, $key, $index) => $value->data.($value->giorni > 1 ? (' - '.$value->fine.' ('.$value->giorni.' giorni)') : '').
          '<br>Motivazione: <em>'.$value->motivazione.'</em>',
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false])
      ->add('convalida_ritardi', ChoiceType::class, ['label' => 'label.convalida_ritardi',
        'choices' => $giustifica['convalida_ritardi'],
        'choice_label' => fn($value, $key, $index) => '<strong>'.$settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y').
          ' ore '.$value->getOra()->format('H:i').'</strong><br>Motivazione: <em>'.$value->getMotivazione().'</em>',
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false])
      ->add('convalida_uscite', ChoiceType::class, ['label' => 'label.convalida_uscite',
        'choices' => $giustifica['convalida_uscite'],
        'choice_label' => fn($value, $key, $index) => '<strong>'.$settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y').
          ' ore '.$value->getOra()->format('H:i').'</strong><br>Motivazione: <em>'.$value->getMotivazione().'</em>',
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false])
      ->add('assenze', ChoiceType::class, ['label' => 'label.assenze',
        'choices' => $giustifica['assenze'],
        'choice_label' => fn($value, $key, $index) => $value->data.($value->giorni > 1 ? (' - '.$value->fine.' ('.$value->giorni.' giorni)') : ''),
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false])
      ->add('ritardi', ChoiceType::class, ['label' => 'label.ritardi',
        'choices' => $giustifica['ritardi'],
        'choice_label' => fn($value, $key, $index) => $settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y').
          ' ore '.$value->getOra()->format('H:i'),
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false])
      ->add('uscite', ChoiceType::class, ['label' => 'label.uscite_anticipate',
        'choices' => $giustifica['uscite'],
        'choice_label' => fn($value, $key, $index) => $settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y').
          ' ore '.$value->getOra()->format('H:i'),
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
	      'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
	      'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$this->generateUrl('lezioni_assenze_quadro',
            ['posizione' => $posizione])."'"]])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // gruppi di assenze
      foreach ($form->get('assenze')->getData() as $ass) {
        $risultato = $this->em->getRepository(Assenza::class)->createQueryBuilder('ass')
          ->update()
          ->set('ass.modificato', ':modificato')
          ->set('ass.giustificato', ':giustificato')
          ->set('ass.docenteGiustifica', ':docenteGiustifica')
          ->where('ass.id in (:ids)')
          ->setParameter('modificato', new DateTime())
          ->setParameter('giustificato', $data_obj)
          ->setParameter('docenteGiustifica', $this->getUser())
          ->setParameter('ids', explode(',', $ass->ids))
          ->getQuery()
          ->getResult();
      }
      foreach ($form->get('convalida_assenze')->getData() as $ass) {
        $risultato = $this->em->getRepository(Assenza::class)->createQueryBuilder('ass')
          ->update()
          ->set('ass.modificato', ':modificato')
          ->set('ass.docenteGiustifica', ':docenteGiustifica')
          ->where('ass.id in (:ids)')
          ->setParameter('modificato', new DateTime())
          ->setParameter('docenteGiustifica', $this->getUser())
          ->setParameter('ids', explode(',', $ass->ids))
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
        $dblogger->logAzione('ASSENZE', 'Giustifica', [
          'Assenze' => implode(', ', array_map(fn($a) => $a->ids, $form->get('assenze')->getData())),
          'Ritardi' => implode(', ', array_map(fn($r) => $r->getId(), $form->get('ritardi')->getData())),
          'Uscite' => implode(', ', array_map(fn($u) => $u->getId(), $form->get('uscite')->getData()))]);
      }
      if (count($form->get('convalida_assenze')->getData()) + count($form->get('convalida_ritardi')->getData()) + count($form->get('convalida_uscite')->getData()) > 0) {
        $dblogger->logAzione('ASSENZE', 'Convalida', [
          'Assenze' => implode(', ', array_map(fn($a) => $a->ids, $form->get('convalida_assenze')->getData())),
          'Ritardi' => implode(', ', array_map(fn($r) => $r->getId(), $form->get('convalida_ritardi')->getData())),
          'Uscite' => implode(', ', array_map(fn($u) => $u->getId(), $form->get('convalida_uscite')->getData()))]);
      }
      // redirezione
      return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/giustifica_edit.html.twig', [
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form->createView(),
      'form_title' => 'title.giustifica',
      'label' => $label,
      'giustificazioni' => $giustifica['tot_giustificazioni'],
      'convalide' => $giustifica['tot_convalide'],
      'alunno' => $alunno]);
  }

  /**
   * Gestione dell'appello
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se sostituzione)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/assenze/appello/{cattedra}/{classe}/{data}', name: 'lezioni_assenze_appello', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function appello(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                          LogHandler $dblogger, int $cattedra, int $classe,
                          string $data): Response {
    // inizializza
    $label = [];
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $this->em->getRepository(Cattedra::class)->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // sostituzione
      $cattedra = null;
    }
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
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
    [$elenco, $listaFC, $noAppello] = $reg->elencoAppello($data_obj, $classe, $religione);
    // controlla funzione
    if ($noAppello) {
      // errore: funzione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$classe;
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('assenze_appello', FormType::class)
      ->add('lista', CollectionType::class, ['label' => false,
        'data' => $elenco,
        'entry_type' => AppelloType::class,
        'entry_options' => ['label' => false]])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary gs-mr-3']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$this->generateUrl('lezioni_assenze_quadro')."'"]])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta assenze/ritardi
      $log['assenza_create'] = [];
      $log['assenza_delete'] = [];
      $log['entrata_create'] = [];
      $log['entrata_edit'] = [];
      $log['entrata_delete'] = [];
      $log['uscita_delete'] = [];
      $orario = $reg->orarioInData($data_obj, $classe->getSede());
      $alunni_assenza = [];
      foreach ($form->get('lista')->getData() as $key=>$appello) {
        $alunno = $this->em->getRepository(Alunno::class)->find($appello->getId());
        if (!$alunno) {
          // alunno non esiste, salta
          continue;
        }
        $alunni_assenza[] = $alunno;
        switch ($appello->getPresenza()) {
          case 'A':   // assente
            // controlla se assenza esiste
            $assenza = $this->em->getRepository(Assenza::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
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
            $entrata = $this->em->getRepository(Entrata::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($entrata) {
              // rimuove ritardo
              $log['entrata_delete'][] = [$entrata->getId(), $entrata];
              $this->em->remove($entrata);
            }
            // controlla esistenza uscita
            $uscita = $this->em->getRepository(Uscita::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($uscita) {
              // rimuove uscita
              $log['uscita_delete'][] = [$uscita->getId(), $uscita];
              $this->em->remove($uscita);
            }
            // controlla fc
            $presenza = $this->em->getRepository(Presenza::class)->findOneBy(['alunno' => $alunno,
              'data' => $data_obj]);
            if ($presenza) {
              // errore: esiste un fc
              throw $this->createNotFoundException('exception.id_notfound');
            }
            break;
          case 'P':   // presente
            // controlla esistenza assenza
            $assenza = $this->em->getRepository(Assenza::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($assenza) {
              // rimuove assenza
              $log['assenza_delete'][] = [$assenza->getId(), $assenza];
              $this->em->remove($assenza);
            }
            // controlla esistenza ritardo
            $entrata = $this->em->getRepository(Entrata::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($entrata) {
              // rimuove ritardo
              $log['entrata_delete'][] = [$entrata->getId(), $entrata];
              $this->em->remove($entrata);
            }
            // controlla esistenza uscita
            $uscita = $this->em->getRepository(Uscita::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($uscita) {
              // rimuove uscita
              $log['uscita_delete'][] = [$uscita->getId(), $uscita];
              $this->em->remove($uscita);
            }
            break;
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
        $dblogger->logAzione('ASSENZE', 'Appello', [
          'Data' => $data,
          'Assenze create' => implode(', ', array_map(fn($e) => $e->getId(), $log['assenza_create'])),
          'Assenze cancellate' => implode(', ', array_map(fn($e) => '[Assenza: '.$e[0].', Alunno: '.$e[1]->getAlunno()->getId().
            ', Giustificato: '.($e[1]->getGiustificato() ? $e[1]->getGiustificato()->format('Y-m-d') : '').
            ', Docente: '.$e[1]->getDocente()->getId().
            ', DocenteGiustifica: '.($e[1]->getDocenteGiustifica() ? $e[1]->getDocenteGiustifica()->getId() : '').']', $log['assenza_delete'])),
            'Entrate create' => implode(', ', array_map(fn($e) => $e->getId(), $log['entrata_create'])),
            'Entrate modificate' => implode(', ', array_map(fn($e) => '[Entrata: '.$e[0].', Alunno: '.$e[1].', Ora: '.$e[2].
              ', Note: "'.$e[3].'"'.
              ', Giustificato: '.($e[4] ? $e[4]->format('Y-m-d') : '').
              ', Docente: '.$e[5].
              ', DocenteGiustifica: '.($e[6] ? $e[6]->getId() : '').']', $log['entrata_edit'])),
            'Entrate cancellate' => implode(', ', array_map(fn($e) => '[Entrata: '.$e[0].', Alunno: '.$e[1]->getAlunno()->getId().
              ', Ora: '.$e[1]->getOra()->format('H:i').
              ', Note: "'.$e[1]->getNote().'"'.
              ', Giustificato: '.($e[1]->getGiustificato() ? $e[1]->getGiustificato()->format('Y-m-d') : '').
              ', Docente: '.$e[1]->getDocente()->getId().
              ', DocenteGiustifica: '.($e[1]->getDocenteGiustifica() ? $e[1]->getDocenteGiustifica()->getId() : '').']', $log['entrata_delete'])),
            'Uscite cancellate' => implode(', ', array_map(fn($e) => '[Uscita: '.$e[0].', Alunno: '.$e[1]->getAlunno()->getId().
              ', Ora: '.$e[1]->getOra()->format('H:i').
              ', Note: "'.$e[1]->getNote().'"'.
              ', Docente: '.$e[1]->getDocente()->getId(), $log['uscita_delete']))]);
        // redirezione
        return $this->redirectToRoute('lezioni_assenze_quadro');
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/appello.html.twig', [
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form->createView(),
      'form_title' => 'title.appello',
      'label' => $label,
      'dati' => $listaFC]);
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
   */
  #[Route(path: '/lezioni/assenze/fuoriclasse/{classe}/{data}/{alunno}/{id}/{posizione}', name: 'lezioni_assenze_fuoriclasse', requirements: ['classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d', 'alunno' => '\d+', 'id' => '\d+', 'posizione' => '\d+'], defaults: ['posizione' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function fuoriclasse(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                              LogHandler $dblogger, int $classe, string $data, int $alunno,
                              int $id, int $posizione): Response {
    // init
    $dati = [];
    $info = [];
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['id' => $alunno,
      'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla presenza e azione
    $edit = false;
    $delete = false;
    if ($id > 0) {
      // presenza esistente
      $presenza = $this->em->getRepository(Presenza::class)->findOneBy(['id' => $id,
        'alunno' => $alunno, 'data' => $data_obj]);
      if (!$presenza) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $edit = true;
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
    $info['delete'] = $edit;
    $info['posizione'] = $posizione;
    // form
    $form = $this->createForm(PresenzaType::class, $presenza, [
      'return_url' => $this->generateUrl('lezioni_assenze_quadro', ['posizione' => $posizione]),
      'form_mode' => 'registro']);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      if ($edit && isset($request->request->all()['presenza']['delete'])) {
        // cancella presenza esistente
        $delete = true;
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
        $assenze = $this->em->getRepository(Alunno::class)->assenzeInData($alunno, $data_obj);
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
        $dblogger->logAzione('PRESENZE', $delete ? 'Cancella presenza' :
          ($edit ? 'Modifica presenza' : 'Aggiunge presenza'));
        // redirect
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('lezioni', 'assenze_fuoriclasse', $dati, $info, [$form->createView(),
      'message.required_fields']);
  }

}
