<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\Staff;
use App\Entity\Sede;
use App\Entity\Materia;
use App\Entity\Utente;
use App\Entity\ScansioneOraria;
use DateTime;
use IntlDateFormatter;
use App\Entity\Festivita;
use App\Entity\Genitore;
use App\Entity\Richiesta;
use App\Entity\CambioClasse;
use Exception;
use App\Entity\Nota;
use App\Entity\Cattedra;
use App\Entity\Alunno;
use App\Entity\Assenza;
use App\Entity\Avviso;
use App\Entity\ComunicazioneClasse;
use App\Entity\ComunicazioneUtente;
use App\Entity\Entrata;
use App\Entity\Provisioning;
use App\Entity\Uscita;
use App\Form\AvvisoType;
use App\Form\EntrataType;
use App\Form\MessageType;
use App\Form\ModuloType;
use App\Form\UscitaType;
use App\Message\AvvisoMessage;
use App\MessageHandler\NotificaMessageHandler;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\RegistroUtil;
use App\Util\StaffUtil;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * StaffController - funzioni per lo staff
 *
 * @author Antonello Dessì
 */
class StaffController extends BaseController {


  /**
   * Restituisce gli alunni della classe indicata
   *
   * @param int $id Identificativo della classe
   *
   * @return JsonResponse Informazioni di risposta
   *
   */
  #[Route(path: '/staff/classe/{id}', name: 'staff_classe', requirements: ['id' => '\d+'], defaults: ['id' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function classeAjax(int $id): JsonResponse {
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select("a.id,CONCAT(a.cognome,' ',a.nome) AS nome")
      ->where('a.classe=:classe AND a.abilitato=:abilitato')
      ->setParameter('classe', $id)
      ->setParameter('abilitato', 1)
      ->orderBy('a.cognome,a.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return new JsonResponse($alunni);
  }

  /**
   * Gestione dei ritardi e delle uscite anticipate
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param string $data Data per la gestione dei ritardi e delle uscita (AAAA-MM-GG)
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/studenti/autorizza/{data}/{pagina}', name: 'staff_studenti_autorizza', requirements: ['data' => '\d\d\d\d-\d\d-\d\d', 'pagina' => '\d+'], defaults: ['data' => '0000-00-00', 'pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function studentiAutorizza(Request $request, RegistroUtil $reg, StaffUtil $staff,
                                    string $data, int $pagina): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $info = null;
    $max_pagine = 1;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $data_succ = null;
    $data_prec = null;
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/data')) {
        // recupera data da sessione
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/data'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/data', $data);
    }
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data prec/succ
    $data_succ = (clone $data_obj);
    $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_succ);
    $data_prec = (clone $data_obj);
    $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_prec);
    // recupera criteri dalla sessione
    $search = [];
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_autorizza', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_autorizza', ['data' => $data]))
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('cognome', TextType::class, ['label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim((string) $form->get('cognome')->getData());
      $search['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/pagina', $pagina);
    }
    // recupera periodo
    $info['periodo'] = $reg->periodo($data_obj);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    if (!$errore) {
      // non festivo: recupera dati
      $lista = $this->em->getRepository(Alunno::class)->findClassEnabled($sede, $search, $pagina, $limite);
      $max_pagine = ceil($lista->count() / $limite);
      $dati['genitori'] = $this->em->getRepository(Genitore::class)->datiGenitoriPaginator($lista);
      $dati['lista'] = $staff->entrateUscite($info['periodo']['inizio'], $info['periodo']['fine'], $lista);
      $dati['azioni'] = $reg->azioneAssenze($data_obj, $this->getUser(), null, null, null);
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_autorizza.html.twig', [
      'pagina_titolo' => 'page.staff_autorizza',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => $max_pagine,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Aggiunge, modifica o elimina un ritardo
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $classe Identificativo della classe
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/studenti/autorizza/entrata/{data}/{classe}/{alunno}', name: 'staff_studenti_autorizza_entrata', requirements: ['data' => '\d\d\d\d-\d\d-\d\d', 'classe' => '\d+', 'alunno' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function studentiAutorizzaEntrata(Request $request, RegistroUtil $reg,
                                           TranslatorInterface $trans, LogHandler $dblogger,
                                           string $data, int $classe, int $alunno): Response {
    // inizializza
    $label = [];
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['id' => $alunno, 'abilitato' => 1, 'classe' => $classe]);
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
      $entrata_old = clone $entrata;
      // elimina giustificazione
      $entrata
        ->setDocente($this->getUser())
        ->setRitardoBreve(false)
        ->setGiustificato(null)
        ->setDocenteGiustifica(null);
    } else {
      // nuovo
      $ora = DateTime::createFromFormat('H:i:s', $orario[0]['fine']);
      $nota = $trans->trans('message.autorizza_ritardo', [
        'sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
        'alunno' => $alunno->getCognome().' '.$alunno->getNome()]);
      $entrata = (new Entrata())
        ->setData($data_obj)
        ->setOra($ora)
        ->setNote($nota)
        ->setAlunno($alunno)
        ->setValido(true)
        ->setDocente($this->getUser());
      $this->em->persist($entrata);
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, null)) {
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
      $mode = isset($request->request->all()['entrata']['delete']) ? 'DELETE' : 'EDIT';
      if (!isset($entrata_old) && $mode == 'DELETE') {
        // ritardo non esiste, niente da fare
        return $this->redirectToRoute('staff_studenti_autorizza');
      } elseif ($form->get('ora')->getData()->format('H:i:00') <= $orario[0]['inizio'] ||
                $form->get('ora')->getData()->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // ora fuori dai limiti
        $form->get('ora')->addError(new FormError($trans->trans('field.time', [], 'validators')));
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
          $dblogger->logAzione('ASSENZE', 'Cancella entrata');
        } elseif (isset($entrata_old)) {
          // log modifica
          $dblogger->logAzione('ASSENZE', 'Modifica entrata');
        } else {
          // log nuovo
          $dblogger->logAzione('ASSENZE', 'Crea entrata');
        }
        if (isset($id_assenza)) {
          // log cancella assenza
          $dblogger->logAzione('ASSENZE', 'Cancella assenza');
        }
        // redirezione
        return $this->redirectToRoute('staff_studenti_autorizza');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_autorizza_entrata.html.twig', [
      'pagina_titolo' => 'page.staff_autorizza',
      'form' => $form,
      'form_title' => (isset($entrata_old) ? 'title.modifica_entrata' : 'title.nuova_entrata'),
      'label' => $label,
      'btn_delete' => isset($entrata_old)]);
  }

  /**
   * Aggiunge, modifica o elimina un'uscita anticipata
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $classe Identificativo della classe
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/studenti/autorizza/uscita/{data}/{classe}/{alunno}', name: 'staff_studenti_autorizza_uscita', requirements: ['data' => '\d\d\d\d-\d\d-\d\d', 'classe' => '\d+', 'alunno' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function studentiAutorizzaUscita(Request $request, RegistroUtil $reg,
                                          TranslatorInterface $trans, LogHandler $dblogger,
                                          string $data, int $classe, int $alunno): Response {
    // inizializza
    $label = [];
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['id' => $alunno, 'abilitato' => 1, 'classe' => $classe]);
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
    // controlla richiesta
    if ($this->reqstack->getSession()->get('/CONFIG/SCUOLA/gestione_uscite') == 'A') {
      // gestione uscita con autorizzazione
      $richiesta = $this->em->getRepository(Richiesta::class)
        ->richiestaAlunno('U', $alunno->getId(), $data_obj);
      if ($richiesta && (!in_array($richiesta->getStato(), ['I', 'G'], true) ||
          $richiesta->getDefinizioneRichiesta()->getUnica() ||
          !$richiesta->getDefinizioneRichiesta()->getAbilitata())) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // gestione uscita con giustificazione
      $richiesta = null;
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data_obj, $classe->getSede());
    // controlla uscita
    $uscita = $this->em->getRepository(Uscita::class)->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
    if ($uscita) {
      // edit
      $uscita_old = clone $uscita;
      $uscita
        ->setDocente($this->getUser())
        ->setGiustificato(null)
        ->setDocenteGiustifica(null);
      $chiediGiustificazione = !$uscita_old->getDocenteGiustifica();
    } else {
      // nuovo
      if ($richiesta) {
        $ora = $richiesta->getValori()['ora'];
      } else {
        $ora = new DateTime();
        if ($data != $ora->format('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
            $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
          // data non odierna o ora attuale fuori da orario
          $ora = DateTime::createFromFormat('H:i:s', $orario[count($orario) - 1]['inizio']);
        }
      }
      $msg = $richiesta ? 'message.autorizza_uscita_richiesta' :
        ($alunno->controllaRuoloFunzione('AM') ? 'message.autorizza_uscita_maggiorenne' : 'message.autorizza_uscita');
      $nota = $trans->trans($msg, ['sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
        'alunno' => $alunno->getCognome().' '.$alunno->getNome()]);
      $uscita = (new Uscita())
        ->setData($data_obj)
        ->setOra($ora)
        ->setAlunno($alunno)
        ->setNote($nota)
        ->setValido(true)
        ->setDocente($this->getUser());
      $this->em->persist($uscita);
      $chiediGiustificazione = false;
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, null)) {
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
    $label['richiesta'] = $richiesta;
    // form di inserimento
    $form = $this->createForm(UscitaType::class, $uscita, [
      'form_mode' => $richiesta ? 'richiesta' : 'staff',
      'values' => [$chiediGiustificazione]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $mode = isset($request->request->all()['uscita']['delete']) ? 'DELETE' : 'EDIT';
      if (!isset($uscita_old) && $mode == 'DELETE') {
        // ritardo non esiste, niente da fare
        return $this->redirectToRoute('staff_studenti_autorizza');
      } elseif ($form->get('ora')->getData()->format('H:i:00') < $orario[0]['inizio'] ||
                $form->get('ora')->getData()->format('H:i:00') >= $orario[count($orario) - 1]['fine']) {
        // ora fuori dai limiti
        $form->get('ora')->addError(new FormError($trans->trans('field.time', [], 'validators')));
      } elseif ($form->isValid()) {
        if (isset($uscita_old) && $mode == 'DELETE') {
          // cancella uscita esistente
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
        if ($richiesta) {
          // gestione richiesta
          $richiesta->setStato(isset($id_uscita) ? 'I' : 'G');
        }
        if ($richiesta || $form->get('giustificazione')->getData() === false) {
          // gestione autorizzazione
          $uscita
            ->setGiustificato(new DateTime('today'))
            ->setDocenteGiustifica($this->getUser());
        }
        // ok: memorizza dati
        $this->em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data_obj, $alunno);
        // log azione
        if (isset($uscita_old) && $mode == 'DELETE') {
          // cancella
          $dblogger->logAzione('ASSENZE', 'Cancella uscita');
        } elseif (isset($uscita_old)) {
          // modifica
          $dblogger->logAzione('ASSENZE', 'Modifica uscita');
        } else {
          // nuovo
          $dblogger->logAzione('ASSENZE', 'Crea uscita');
        }
        if (isset($id_assenza)) {
          // cancella assenza
          $dblogger->logAzione('ASSENZE', 'Cancella assenza');
        }
        // redirezione
        return $this->redirectToRoute('staff_studenti_autorizza');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_autorizza_uscita.html.twig', [
      'pagina_titolo' => 'page.staff_autorizza',
      'form' => $form,
      'form_title' => (isset($uscita_old) ? 'title.modifica_uscita' : 'title.nuova_uscita'),
      'label' => $label,
      'btn_delete' => isset($uscita_old)]);
  }

  /**
   * Gestisce l'inserimento di deroghe e annotazioni sugli alunni
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/studenti/deroghe/{pagina}', name: 'staff_studenti_deroghe', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function studentiDeroghe(Request $request, int $pagina): Response {
    $dati = [];
    // recupera criteri dalla sessione
    $search = [];
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_deroghe/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_deroghe/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_deroghe/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_deroghe/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_deroghe', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_deroghe'))
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('cognome', TextType::class, ['label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim((string) $form->get('cognome')->getData());
      $search['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/pagina', $pagina);
    }
    // lista alunni
    $lista['lista'] = $this->em->getRepository(Alunno::class)->findClassEnabled($sede, $search, $pagina, $limite);
    $lista['genitori'] = $this->em->getRepository(Genitore::class)->datiGenitoriPaginator($lista['lista']);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_deroghe.html.twig', [
      'pagina_titolo' => 'page.staff_deroghe',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'dati' => $dati,
      'page' => $pagina,
      'maxPages' => ceil($lista['lista']->count() / $limite)]);
  }

  /**
   * Modifica delle deroghe e annotazioni di un alunno
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $alunno ID dell'alunno
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/studenti/deroghe/edit/{alunno}', name: 'staff_studenti_deroghe_edit', requirements: ['alunno' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function studentiDerogheEdit(Request $request, LogHandler $dblogger, int $alunno): Response {
    // inizializza
    $label = null;
    // controlla alunno
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // edit
    $alunno_old['autorizzaEntrata'] = $alunno->getAutorizzaEntrata();
    $alunno_old['autorizzaUscita'] = $alunno->getAutorizzaUscita();
    $alunno_old['note'] = $alunno->getNote();
    // dati in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format(new DateTime());
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$alunno->getClasse();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('deroga_edit', FormType::class, $alunno)
      ->add('autorizzaEntrata', MessageType::class, ['label' => 'label.autorizza_entrata',
	      'required' => false])
      ->add('autorizzaUscita', MessageType::class, ['label' => 'label.autorizza_uscita',
        'required' => false])
      ->add('note', MessageType::class, ['label' => 'label.note',
	      'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
	      'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
	      'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$this->generateUrl('staff_studenti_deroghe')."'"]])
      ->getForm();
    $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // ok: memorizza dati
        $this->em->flush();
        // log azione
        $dblogger->logAzione('ALUNNO', 'Modifica deroghe');
      // redirezione
      return $this->redirectToRoute('staff_studenti_deroghe');
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_deroghe_edit.html.twig', [
      'pagina_titolo' => 'title.deroghe',
      'form' => $form->createView(),
      'form_title' => 'title.deroghe',
      'label' => $label]);
  }

  /**
   * Mostra la situazione degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/studenti/situazione/{pagina}', name: 'staff_studenti_situazione', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function studentiSituazione(Request $request, int $pagina): Response {
    $dati = [];
    // recupera criteri dalla sessione
    $search = [];
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_situazione/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_situazione/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_situazione/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_situazione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_situazione', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_situazione'))
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('cognome', TextType::class, ['label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim((string) $form->get('cognome')->getData());
      $search['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/pagina', $pagina);
    }
    // lista alunni
    $lista['lista'] = $this->em->getRepository(Alunno::class)->cercaClasse($search, $pagina, $limite);
    $lista['genitori'] = $this->em->getRepository(Genitore::class)->datiGenitoriPaginator($lista['lista']);
    // aggiunge dati cambio classe
    $lista['cambio'] = [];
    foreach ($lista['lista'] as $alunno) {
      $cambio = $this->em->getRepository(CambioClasse::class)->findOneBy(['alunno' => $alunno]);
      if ($cambio) {
        $lista['cambio'][$alunno->getId()] = $cambio;
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_situazione.html.twig', [
      'pagina_titolo' => 'page.staff_situazione',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'dati' => $dati,
      'page' => $pagina,
      'maxPages' => ceil($lista['lista']->count() / $limite)]);
  }

  /**
   * Mostra le statistiche sulle ore di lezione svolte dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/docenti/statistiche/{pagina}', name: 'staff_docenti_statistiche', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function docentiStatistiche(Request $request, TranslatorInterface $trans,
                                     StaffUtil $staff, PdfManager $pdf, int $pagina): Response {
    // recupera criteri dalla sessione
    $creaPdf = false;
    $search = [];
    $search['docente'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_docenti_statistiche/docente', null);
    $search['inizio'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_docenti_statistiche/inizio', null);
    $search['fine'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_docenti_statistiche/fine', null);
    $docente = ($search['docente'] > 0 ? $this->em->getRepository(Docente::class)->find($search['docente']) :
      ($search['docente'] < 0 ? -1 : null));
    $inizio = ($search['inizio'] ? DateTime::createFromFormat('Y-m-d', $search['inizio']) : new DateTime());
    $fine = ($search['fine'] ? DateTime::createFromFormat('Y-m-d', $search['fine']) : new DateTime());
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_docenti_statistiche/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/pagina', $pagina);
    }
    // form di ricerca
    $limite = 20;
    $opzioniDocenti = $this->em->getRepository(Docente::class)->opzioni();
    $opzioniDocenti[$trans->trans('label.tutti_docenti')] = -1;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_docenti_statistiche', FormType::class)
      ->setAction($this->generateUrl('staff_docenti_statistiche'))
      ->add('docente', ChoiceType::class, ['label' => 'label.docente',
        'data' => $docente,
        'choices' => $opzioniDocenti,
        'choice_value' => 'id',
        'placeholder' => 'label.scegli_docente',
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'choice_translation_domain' => false,
        'required' => false])
      ->add('inizio', DateType::class, ['label' => 'label.data_inizio',
        'data' => $inizio,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => false])
      ->add('fine', DateType::class, ['label' => 'label.data_fine',
        'data' => $fine,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->add('print', SubmitType::class, ['label' => 'label.stampa',
	      'attr' => ['class' => 'btn-success']])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() :
        ($form->get('docente')->getData() < 0 ? -1 : null));
      $search['inizio'] = ($form->get('inizio')->getData() ? $form->get('inizio')->getData()->format('Y-m-d') : 0);
      $search['fine'] = ($form->get('fine')->getData() ? $form->get('fine')->getData()->format('Y-m-d') : 0);
      $docente = ($search['docente'] > 0 ? $this->em->getRepository(Docente::class)->find($search['docente']) :
        ($search['docente'] < 0 ? -1 : null));
      $inizio = ($form->get('inizio')->getData() ?: new DateTime());
      $fine = ($form->get('fine')->getData() ?: new DateTime());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/docente', $search['docente']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/inizio', $search['inizio']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/fine', $search['fine']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/pagina', $pagina);
      $creaPdf = ($form->get('print')->isClicked());
    }
    // statistiche
    if ($creaPdf) {
      // crea PDF
      $lista = $staff->statisticheStampa($docente, $inizio, $fine);
      $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
        'Statistiche sulle ore di lezione dei docenti');
      $pdf->getHandler()->SetAutoPageBreak(true, 15);
      $pdf->getHandler()->SetFooterMargin(15);
      $pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
      $pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
      $pdf->getHandler()->setPrintFooter(true);
      $html = $this->renderView('pdf/statistiche_docenti.html.twig', [
        'lista' => $lista,
	      'search' => $search]);
      $pdf->createFromHtml($html);
      // invia il documento
      $nomefile = 'statistiche-docenti.pdf';
      return $pdf->send($nomefile);
    } else {
      // mostra la pagina di risposta
      $lista = $staff->statistiche($docente, $inizio, $fine, $pagina, $limite);
      return $this->render('ruolo_staff/docenti_statistiche.html.twig', [
        'pagina_titolo' => 'page.staff_statistiche',
        'form' => $form->createView(),
        'lista' => $lista,
        'page' => $pagina,
        'maxPages' => ceil($lista->count() / $limite)]);
    }
  }

  /**
   * Gestisce la generazione della password per gli alunni
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/password/{pagina}', name: 'staff_password', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function password(Request $request, int $pagina): Response {
    // recupera criteri dalla sessione
    $search = [];
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_password/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_password/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_password/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_password/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_password', FormType::class)
      ->setAction($this->generateUrl('staff_password'))
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('cognome', TextType::class, ['label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim((string) $form->get('cognome')->getData());
      $search['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/pagina', $pagina);
    }
    // lista alunni
    $lista['lista'] = $this->em->getRepository(Alunno::class)->findClassEnabled($sede, $search, $pagina, $limite);
    $lista['genitori'] = $this->em->getRepository(Genitore::class)->datiGenitoriPaginator($lista['lista']);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/password.html.twig', [
      'pagina_titolo' => 'page.staff_password',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista['lista']->count() / $limite)]);
  }

  /**
   * Generazione della password degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param LogHandler $dblogger Gestore dei log su database
   * @param LoggerInterface $logger Gestore dei log su file
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param MailerInterface $mailer Gestore della spedizione delle email
   * @param string $tipo Indica se inviare l'email (E) o scaricare il PDF (P)
   * @param string $username Identificativo dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/password/create/{tipo}/{username}', name: 'staff_password_create', requirements: ['tipo' => 'E|P'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function passwordCreate(Request $request, UserPasswordHasherInterface $hasher,
                                 StaffUtil $staff, LogHandler $dblogger, LoggerInterface $logger ,
                                 PdfManager $pdf, MailerInterface $mailer, string $tipo,
                                 string $username = null): Response {
     // controlla alunno
     $utente = $this->em->getRepository(Alunno::class)->findOneByUsername($username);
     if (!$utente) {
       // controlla genitore
       $utente = $this->em->getRepository(Genitore::class)->findOneByUsername($username);
       if (!$utente) {
         // errore
         throw $this->createNotFoundException('exception.id_notfound');
       }
     }
    // crea password
    $password = $staff->creaPassword(8);
    $utente->setPasswordNonCifrata($password);
    $pswd = $hasher->hashPassword($utente, $utente->getPasswordNonCifrata());
    $utente->setPassword($pswd);
    // provisioning
    if ($utente instanceOf Alunno) {
      $provisioning = (new Provisioning())
        ->setUtente($utente)
        ->setFunzione('passwordUtente')
        ->setDati(['password' => $utente->getPasswordNonCifrata()]);
      $this->em->persist($provisioning);
    }
    // memorizza su db
    $this->em->flush();
    // log azione
    $dblogger->logAzione('SICUREZZA', 'Generazione Password');
    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      'Credenziali di accesso al Registro Elettronico');
    // contenuto in formato HTML
    if ($utente instanceOf Alunno) {
      $html = $this->renderView('pdf/credenziali_profilo_alunni.html.twig', [
        'alunno' => $utente,
        'sesso' => ($utente->getSesso() == 'M' ? 'o' : 'a'),
        'username' => $utente->getUsername(),
        'password' => $password]);
    } else {
      $html = $this->renderView('pdf/credenziali_profilo_genitori.html.twig', [
        'alunno' => $utente->getAlunno(),
        'genitore' => $utente,
        'sesso' => ($utente->getAlunno()->getSesso() == 'M' ? 'o' : 'a'),
        'username' => $utente->getUsername(),
        'password' => $password]);
    }
    $pdf->createFromHtml($html);
    if ($tipo == 'E') {
      // invia password per email
      $doc = $pdf->getHandler()->Output('', 'S');
      $message = (new Email())
        ->from(new Address($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/email_notifiche'), $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')))
        ->to($utente->getEmail())
        ->subject($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')." - Credenziali di accesso al Registro Elettronico")
        ->text($this->renderView('email/credenziali.txt.twig'))
        ->html($this->renderView('email/credenziali.html.twig'))
        ->attach($doc, 'credenziali_registro.pdf', 'application/pdf');
      try {
        // invia email
        $mailer->send($message);
        $this->addFlash('success', 'message.credenziali_inviate');
      } catch (Exception $err) {
        // errore di spedizione
        $logger->error('Errore di spedizione email delle credenziali alunno/genitore.', [
          'username' => $utente->getUsername(),
          'email' => $utente->getEmail(),
          'ip' => $request->getClientIp(),
          'errore' => $err->getMessage()]);
        $this->addFlash('danger', 'exception.errore_invio_credenziali');
      }
      // redirezione
      return $this->redirectToRoute('staff_password');
    } else {
      // crea pdf e lo scarica
      $nomefile = 'credenziali-registro.pdf';
      return $pdf->send($nomefile);
    }
  }

  /**
   * Gestione delle assenze di classe
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $data Data per la gestione delle assenze (AAAA-MM-GG)
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/studenti/assenze/{data}/{classe}', name: 'staff_studenti_assenze', requirements: ['data' => '\d\d\d\d-\d\d-\d\d', 'classe' => '\d+'], defaults: ['data' => '0000-00-00', 'classe' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function studentiAssenze(Request $request, RegistroUtil $reg, LogHandler $dblogger,
                                  string $data, int $classe): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $info = null;
    $data_succ = null;
    $data_prec = null;
    $form_assenze = null;
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_assenze/data')) {
        // recupera data da sessione
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_assenze/data'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_assenze/data', $data);
    }
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data prec/succ
    $data_succ = (clone $data_obj);
    $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_succ);
    $data_prec = (clone $data_obj);
    $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_prec);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    // parametro classe (può essere null)
    if ($classe == 0) {
      // classe non specificata
      $classe = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_assenze/classe', 0);
    }
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_assenze', FormType::class)
      ->setMethod('GET')
      ->setAction($this->generateUrl('staff_studenti_assenze', ['data' => $data]))
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.visualizza'])
      ->getForm();
    $form->handleRequest($request);
    if ($classe) {
      // memorizza classe
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_assenze/classe', $classe->getId());
      if (!$errore && $reg->azioneAssenze($data_obj, $this->getUser(), null, $classe, null)) {
        // elenco alunni
        $elenco = $reg->alunniInData($data_obj, $classe);
        // elenco assenze
        $assenti = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
          ->join(Assenza::class, 'ass', 'WITH', 'a.id=ass.alunno AND ass.data=:data')
          ->where('a.id IN (:elenco)')
          ->setParameter('elenco', $elenco)
          ->setParameter('data', $data_obj->format('Y-m-d'))
          ->getQuery()
          ->getResult();
        // form di inserimento
        $form_assenze = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_assenze_appello', FormType::class)
          ->add('alunni', EntityType::class, ['label' => 'label.alunni_assenti',
            'data' => $assenti,
            'class' => Alunno::class,
            'choice_label' => fn($obj) => $obj->getCognome().' '.$obj->getNome().' ('.
              $obj->getDataNascita()->format('d/m/Y').')',
            'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('a')
              ->where('a.id IN (:elenco)')
              ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
              ->setParameter('elenco', $elenco),
            'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
            'expanded' => true,
            'multiple' => true,
            'required' => false])
          ->add('submit', SubmitType::class, ['label' => 'label.submit',
	          'attr' => ['class' => 'btn-primary']])
          ->getForm();
        $form_assenze->handleRequest($request);
        if ($form_assenze->isSubmitted() && $form_assenze->isValid()) {
          $log['assenza_create'] = [];
          $log['assenza_delete'] = [];
          $log['entrata_delete'] = [];
          $log['uscita_delete'] = [];
          // aggiunge assenti
          $nuovi_assenti = array_diff($form_assenze->get('alunni')->getData(), $assenti);
          foreach ($nuovi_assenti as $alu) {
            // inserisce nuova assenza
            $assenza = (new Assenza())
              ->setData($data_obj)
              ->setAlunno($alu)
              ->setDocente($this->getUser());
            $this->em->persist($assenza);
            $log['assenza_create'][] = $assenza;
            // controlla esistenza ritardo
            $entrata = $this->em->getRepository(Entrata::class)->findOneBy(['alunno' => $alu, 'data' => $data_obj]);
            if ($entrata) {
              // rimuove ritardo
              $log['entrata_delete'][$entrata->getId()] = $entrata;
              $this->em->remove($entrata);
            }
            // controlla esistenza uscita
            $uscita = $this->em->getRepository(Uscita::class)->findOneBy(['alunno' => $alu, 'data' => $data_obj]);
            if ($uscita) {
              // rimuove uscita
              $log['uscita_delete'][$uscita->getId()] = $uscita;
              $this->em->remove($uscita);
            }
          }
          // cancella assenti
          $cancella_assenti = array_diff($assenti, $form_assenze->get('alunni')->getData());
          foreach ($cancella_assenti as $alu) {
            $assenza = $this->em->getRepository(Assenza::class)->findOneBy(['alunno' => $alu, 'data' => $data_obj]);
            if ($assenza) {
              // rimuove assenza
              $log['assenza_delete'][$assenza->getId()] = $assenza;
              $this->em->remove($assenza);
            }
          }
          // ok: memorizza dati
          $this->em->flush();
          // ricalcola ore assenze
          foreach (array_merge($nuovi_assenti, $cancella_assenti) as $alu) {
            $reg->ricalcolaOreAlunno($data_obj, $alu);
          }
          // log azione
          $dblogger->logAzione('ASSENZE', 'Gestione assenti');
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_assenze.html.twig');
  }

  /**
   * Visualizza statistiche sulle presenze in classe
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param string $data Data per la gestione delle assenze (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/studenti/statistiche/{data}', name: 'staff_studenti_statistiche', requirements: ['data' => '\d\d\d\d-\d\d-\d\d'], defaults: ['data' => '0000-00-00'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function studentiStatistiche(Request $request, RegistroUtil $reg, StaffUtil $staff,
                                      string $data): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $info = null;
    $data_succ = null;
    $data_prec = null;
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_statistiche/data')) {
        // recupera data da sessione
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_statistiche/data'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_statistiche/data', $data);
    }
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data prec/succ
    $data_succ = (clone $data_obj);
    $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_succ);
    $data_prec = (clone $data_obj);
    $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_prec);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    // recupera criteri dalla sessione
    $search = [];
    $search['sede'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_statistiche/sede');
    $sede = ($search['sede'] > 0 ? $this->em->getRepository(Sede::class)->find($search['sede']) : null);
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_statistiche/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($search['classe']) : null);
    // legge sede
    $sede_staff = $this->getUser()->getSede();
    // form di ricerca
    if ($this->getUser()->getSede()) {
      $opzioniSedi[$this->getUser()->getSede()->getNomeBreve()] = $this->getUser()->getSede();
    } else {
      $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    }
    foreach ($opzioniSedi as $s) {
      $info['sedi'][$s->getId()] = $s->getNomeBreve();
    }
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_statistiche', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_statistiche', ['data' => $data_obj->format('Y-m-d')]))
      ->add('sede', ChoiceType::class, ['label' => 'label.sede',
        'data' => $sede,
        'choices' => $opzioniSedi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_sede',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.visualizza'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid() && !$errore) {
      // imposta criteri di ricerca
      $search['sede'] = (is_object($form->get('sede')->getData()) ? $form->get('sede')->getData()->getId() : 0);
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_statistiche/sede', $search['sede']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_statistiche/classe', $search['classe']);
      // recupera dati
      $dati = $staff->statisticheAlunni($data_obj, $search);
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_statistiche.html.twig', [
      'pagina_titolo' => 'page.staff_statistiche',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Visualizza statistiche sulla condotta degli studenti
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/studenti/condotta/{pagina}', name: 'staff_studenti_condotta', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function studentiCondotta(Request $request, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    $search = [];
    // recupera criteri dalla sessione
    $search['sede'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_condotta/sede');
    $sede = ($search['sede'] > 0 ? $this->em->getRepository(Sede::class)->find($search['sede']) : null);
    $search['inizio'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_condotta/inizio',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio'));
    $inizio = DateTime::createFromFormat('Y-m-d', $search['inizio']);
    $search['fine'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_condotta/fine',
      (new DateTime())->format('Y-m-d'));
    $fine = DateTime::createFromFormat('Y-m-d', $search['fine']);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_condotta/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_condotta/pagina', $pagina);
    }
    // legge sede
    $sedeStaff = $this->getUser()->getSede();
    // form di ricerca
    if ($sedeStaff) {
      $opzioniSedi[$sedeStaff->getNomeBreve()] = $sedeStaff;
    } else {
      $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    }
    foreach ($opzioniSedi as $s) {
      $info['sedi'][$s->getId()] = $s->getNomeBreve();
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_condotta', FormType::class)
      ->add('sede', ChoiceType::class, ['label' => 'label.sede',
        'data' => $sede,
        'choices' => $opzioniSedi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_sede',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('inizio', DateType::class, ['label' => false,
        'data' => $inizio,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker', 'title' => 'label.data_inizio'],
        'format' => 'dd/MM/yyyy',
        'required' => false])
      ->add('fine', DateType::class, ['label' => false,
        'data' => $fine,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker', 'title' => 'label.data_fine'],
        'format' => 'dd/MM/yyyy',
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.visualizza'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['sede'] = (is_object($form->get('sede')->getData()) ? $form->get('sede')->getData()->getId() : 0);
      $search['inizio'] = ($form->get('inizio')->getData() ? $form->get('inizio')->getData()->format('Y-m-d') : 0);
      $search['fine'] = ($form->get('fine')->getData() ? $form->get('fine')->getData()->format('Y-m-d') : 0);
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_condotta/sede', $search['sede']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_condotta/inizio', $search['inizio']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_condotta/fine', $search['fine']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_condotta/pagina', $pagina);
    }
    // recupera dati
    $dati = $this->em->getRepository(Nota::class)->statisticaCondotta($search, $pagina);
    $info['pagina'] = $pagina;
    $info['inizio'] = $search['inizio'];
    $info['fine'] = $search['fine'];
    // mostra la pagina di risposta
    return $this->renderHtml('ruolo_staff', 'studenti_condotta', $dati, $info, [$form->createView()]);
  }

  /**
   * Visualizza i componenti del consiglio di classe
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/staff/docenti/cdc', name: 'staff_docenti_cdc', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function docentiCdc(Request $request): Response {
    // inizializza
    $dati = [];
    $info = [];
    // criteri di ricerca
    $classe = $this->em->getRepository(Classe::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/staff_docenti_cdc/classe', 0));
    $classeId = $classe ? $classe->getId() : 0;
    // form di ricerca
    $sede = $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : 0;
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni($sede, false);
    foreach ($opzioniClassi as $sede => $lista) {
      $prec = null;
      $precKey = null;
      foreach ($lista as $key => $val) {
        if ($prec && empty($prec->getGruppo()) && $prec->getAnno() == $val->getAnno() &&
            $prec->getSezione() == $val->getSezione() && !empty($val->getGruppo())) {
          unset($opzioniClassi[$sede][$precKey]);
        }
        $prec = $val;
        $precKey = $key;
      }
    }
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'classe',
      'values' => [$classe, $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $classe = $form->get('classe')->getData();
      $classeId = $classe ? $classe->getId() : 0;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_cdc/classe', $classeId);
    }
    if ($classe) {
      // informazioni
      $info['classe'] = $classe;
      // recupera dati
      $dati = $this->em->getRepository(Cattedra::class)->cattedreClasse($classe);
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/docenti_cdc.html.twig', [
      'pagina_titolo' => 'page.staff.cdc',
      'titolo' => 'title.staff.cdc',
      'form' => [$form->createView()],
      'dati' => $dati,
      'info' => $info]);
  }

}
