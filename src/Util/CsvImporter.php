<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Component\Validator\ValidatorInterface;
use App\Entity\Cattedra;
use App\Entity\Docente;
use App\Entity\Ata;
use App\Entity\Classe;
use App\Entity\Materia;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Colloquio;
use App\Entity\Orario;
use App\Entity\OrarioDocente;
use App\Entity\Provisioning;
use App\Entity\ScansioneOraria;
use App\Entity\Sede;


/**
 * CsvImporter - classe di utilità per il caricamento dei dati da file CSV
 *
 * @author Antonello Dessì
 */
class CsvImporter {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var TranslatorInterface $trans Gestore delle traduzioni
   */
  private $trans;

  /**
   * @var RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  private $reqstack;

  /**
   * @var UserPasswordHasherInterface $hasher Gestore della codifica delle password
   */
  private $hasher;

  /**
   * @var ValidatorInterface $validator Gestore della validazione dei dati
   */
  private $validator;

  /**
   * @var StaffUtil $staff Classe di utilità per le funzioni disponibili allo staff
   */
  private $staff;

  /**
   * @var resource $fh Gestore del file
   */
  private $fh;

  /**
   * @var array $header Lista dei campi da importare
   */
  private $header;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param ValidatorBuilder $valbuilder Costruttore per il gestore della validazione dei dati
   * @param StaffUtil $staff Classe di utilità per le funzioni disponibili allo staff
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans, RequestStack $reqstack,
                              UserPasswordHasherInterface $hasher, ValidatorBuilder $valbuilder, StaffUtil $staff) {
    $this->em = $em;
    $this->trans = $trans;
    $this->reqstack = $reqstack;
    $this->hasher = $hasher;
    $this->validator = $valbuilder->getValidator();
    $this->staff = $staff;
    $this->fh = null;
    $this->header = array();
  }

  /**
   * Importa i docenti da file CSV
   *
   * @param File $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista dei docenti importati
   */
  public function importaDocenti(File $file=null, Form $form) {
    $header = array('cognome', 'nome', 'sesso', 'codiceFiscale', 'username', 'password', 'email');
    $filtro = $form->get('filtro')->getData();
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->addError(new FormError($this->trans->trans($error)));
      return null;
    }
    // lettura dati
    $imported = array();
    $count = 0;
    while (($data = fgetcsv($this->fh)) !== false) {
      $count++;
      if (count($data) == 0 || (count($data) == 1 && $data[0] == '')) {
        // riga vuota, salta
        continue;
      }
      // controllo numero campi
      if (count($data) != count($header)) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_data', ['num' => $count])));
        return $imported;
      }
      // lettura campi
      $fields = array();
      $empty_fields = array();
      foreach ($data as $key=>$val) {
        $fields[$this->header[$key]] = $val;
      }
      // formattazione campi
      $fields['cognome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['cognome']))));
      $fields['nome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['nome']))));
      $fields['sesso'] = strtoupper(trim($fields['sesso']));
      $fields['codiceFiscale'] = strtoupper(trim($fields['codiceFiscale']));
      $fields['username'] = strtolower(trim($fields['username']));
      $fields['password'] = trim($fields['password']);
      $fields['email'] = strtolower(trim($fields['email']));
      // controlla campi obbligatori
      if (empty($fields['cognome']) || empty($fields['nome']) || empty($fields['sesso'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_required', ['num' => $count])));
        return $imported;
      }
      if (empty($fields['codiceFiscale'])) {
        // valore null
        $empty_fields['codiceFiscale'] = true;
        $fields['codiceFiscale'] = null;
      }
      if (empty($fields['username'])) {
        // crea username
        $empty_fields['username'] = true;
        if (strpos($fields['nome'], ' ') !== false) {
          $nomi = explode(' ', $fields['nome']);
          $username = $nomi[0].$nomi[1][0].'.'.$fields['cognome'];
        } else {
          $username = $fields['nome'].'.'.$fields['cognome'];
        }
        $username = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $username));
        $fields['username'] = preg_replace('/[^a-z\.]+/', '', $username);
      }
      if (empty($fields['password'])) {
        // crea password
        $empty_fields['password'] = true;
        $fields['password'] = $this->staff->creaPassword(10);
      }
      if (empty($fields['email'])) {
        // crea finta email
        $empty_fields['email'] = true;
        $fields['email'] = $fields['username'].'@'.($this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider') ?
          $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider_dominio') : $this->reqstack->getSession()->get('/CONFIG/SISTEMA/dominio_default'));
      }
      // controlla esistenza di docente
      $docente = $this->em->getRepository('App\Entity\Docente')->findOneByUsername($fields['username']);
      if ($docente) {
        // docente esiste
        if ($filtro == 'T' || $filtro == 'E') {
          // modifica docente
          if (isset($empty_fields['username'])) {
            // errore: non modifica utente con username generata automaticamente
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError($this->trans->trans('exception.file_duplicated', ['num' => $count])));
            return $imported;
          }
          $error = $this->modificaDocente($docente, $fields, $empty_fields);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['EDIT'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONE'][$count] = $fields;
        }
      } else {
        // docente non esiste
        if ($filtro == 'T' || $filtro == 'N') {
          // crea nuovo docente
          $error = $this->nuovoDocente($fields);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['NEW'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONEW'][$count] = $fields;
        }
      }
    }
    // ok
    fclose($this->fh);
    $this->fh = null;
    return $imported;
  }

  /**
   * Importa i docenti da file CSV
   *
   * @param File $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista delle cattedtre importate
   */
  public function importaCattedre(File $file=null, Form $form) {
    $header = array('usernameDocente','classe','materia','usernameAlunno','tipo','supplenza');
    $filtro = $form->get('filtro')->getData();
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->addError(new FormError($this->trans->trans($error)));
      return null;
    }
    // lettura dati
    $imported = array();
    $count = 0;
    while (($data = fgetcsv($this->fh)) !== false) {
      $count++;
      if (count($data) == 0 || (count($data) == 1 && $data[0] == '')) {
        // riga vuota, salta
        continue;
      }
      // controllo numero campi
      if (count($data) != count($header)) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_data', ['num' => $count])));
        return $imported;
      }
      // lettura campi
      $fields = array();
      $empty_fields = array();
      foreach ($data as $key=>$val) {
        $fields[$this->header[$key]] = $val;
      }
      // formattazione campi
      $fields['usernameDocente'] = strtolower(trim($fields['usernameDocente']));
      $fields['classe'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['classe']));
      $fields['materia'] = strtoupper(str_replace([' ',',','(',')',"'","`","\t","\r","\n"], '',
        iconv('UTF-8', 'ASCII//TRANSLIT', $fields['materia'])));
      $fields['usernameAlunno'] = strtolower(trim($fields['usernameAlunno']));
      $fields['tipo'] = strtoupper(trim($fields['tipo']));
      $fields['supplenza'] = strtoupper(trim($fields['supplenza']));
      // controlla campi obbligatori
      if (empty($fields['usernameDocente']) || empty($fields['classe']) || empty($fields['materia'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_required', ['num' => $count])));
        return $imported;
      }
      // controlla esistenza di docente
      $lista = $this->em->getRepository('App\Entity\Docente')->findByUsername($fields['usernameDocente']);
      if (count($lista) == 0) {
        // errore: docente non esiste
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_mancante', ['num' => $count])));
        return $imported;
      } elseif (count($lista) > 1) {
        // errore: docente non esiste
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_duplicato', ['num' => $count])));
        return $imported;
      }
      $docente = $lista[0];
      // controlla esistenza di classe
      $lista = $this->em->getRepository('App\Entity\Classe')->findBy(array(
        'anno' => $fields['classe'][0],
        'sezione' => $fields['classe'][1]));
      if (count($lista) != 1 || strlen($fields['classe']) != 2) {
        // errore: classe
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_classe', ['num' => $count])));
        return $imported;
      }
      $classe = $lista[0];
      // controlla esistenza di materia
      $lista = $this->em->getRepository('App\Entity\Materia')->findByNomeNormalizzato($fields['materia']);
      if (count($lista) != 1) {
        // errore: materia
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_materia', ['num' => $count])));
        return $imported;
      }
      $materia = $lista[0];
      // controlla esistenza di alunno
      if (!empty($fields['usernameAlunno']) && $fields['usernameAlunno'] != '---') {
        $lista = $this->em->getRepository('App\Entity\Alunno')->findByUsername($fields['usernameAlunno']);
      } elseif ($fields['usernameAlunno'] == '---') {
        // alunno da rimuovere
        $lista = null;
        $alunno = null;
      } else {
        // alunno non specificato
        $empty_fields['usernameAlunno'] = true;
        $lista = null;
        $alunno = null;
      }
      if ($lista !== null && count($lista) == 0) {
        // errore: alunno non esiste
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_alunno_mancante', ['num' => $count])));
        return $imported;
      } elseif ($lista !== null && count($lista) > 1) {
        // errore: alunno duplicato
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_alunno_duplicato', ['num' => $count])));
        return $imported;
      } elseif ($lista !== null) {
        $alunno = $lista[0];
      }
      // controlla altri campi
      if (empty($fields['tipo'])) {
        // default: normale
        $empty_fields['tipo'] = true;
        $fields['tipo'] = 'N';
      }
      if (empty($fields['supplenza'])) {
        // default: no
        $empty_fields['supplenza'] = true;
        $fields['supplenza'] = false;
      } else {
        // imposta valore
        $fields['supplenza'] = ($fields['supplenza'] == 'S');
      }
      // controlli incrociati su sostegno
      if ($materia->getTipo() == 'S' && $fields['tipo'] == 'N') {
        // sostegno
        if ($alunno && $alunno->getClasse() != $classe) {
          // classe diversa da quella di alunno
          fclose($this->fh);
          $this->fh = null;
          $form->addError(new FormError($this->trans->trans('exception.file_classe', ['num' => $count])));
          return $imported;
        }
      } else {
        // materia non è sostegno (o sostegno su potenziamento): alunno ignorato
        $alunno = null;
        $fields['usernameAlunno'] = null;
        $empty_fields['usernameAlunno'] = true;
      }
      // controlla esistenza di cattedra
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->findOneBy(['docente' => $docente,
        'classe' => $classe, 'materia' => $materia]);
      if ($cattedra) {
        // cattedra esiste
        if ($filtro == 'T' || $filtro == 'E') {
          // modifica cattedra
          $error = $this->modificaCattedra($cattedra, $alunno, $fields, $empty_fields);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['EDIT'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONE'][$count] = $fields;
        }
      } else {
        // cattedra non esiste
        if ($filtro == 'T' || $filtro == 'N') {
          // crea nuova cattedra
          $error = $this->nuovaCattedra($fields, $docente, $classe, $materia, $alunno);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['NEW'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONEW'][$count] = $fields;
        }
      }
    }
    // ok
    fclose($this->fh);
    $this->fh = null;
    return $imported;
  }

  /**
   * Importa gli alunni da file CSV
   *
   * @param File $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista degli alunni importati
   */
  public function importaAlunni(File $file=null, Form $form) {
    $header = array('cognome', 'nome', 'sesso', 'dataNascita', 'comuneNascita', 'codiceFiscale',
      'citta', 'indirizzo', 'bes', 'noteBes', 'frequenzaEstero', 'religione', 'credito3', 'credito4',
      'classe', 'username', 'password', 'email',
      'genitore1Cognome', 'genitore1Nome', 'genitore1CodiceFiscale', 'genitore1Telefono',
      'genitore1Username', 'genitore1Password', 'genitore1Email',
      'genitore2Cognome', 'genitore2Nome', 'genitore2CodiceFiscale', 'genitore2Telefono',
      'genitore2Username', 'genitore2Password', 'genitore2Email');
    $filtro = $form->get('filtro')->getData();
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->addError(new FormError($this->trans->trans($error)));
      return null;
    }
    // lettura dati
    $imported = array();
    $count = 0;
    while (($data = fgetcsv($this->fh)) !== false) {
      $count++;
      if (count($data) == 0 || (count($data) == 1 && $data[0] == '')) {
        // riga vuota, salta
        continue;
      }
      // controllo numero campi
      if (count($data) != count($header)) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_data', ['num' => $count])));
        return $imported;
      }
      // lettura campi
      $fields = array();
      $empty_fields = array();
      foreach ($data as $key=>$val) {
        $fields[$this->header[$key]] = $val;
        $empty_fields[$this->header[$key]] = false;
      }
      // formattazione campi
      $fields['cognome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['cognome']))));
      $fields['nome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['nome']))));
      $fields['sesso'] = strtoupper(trim($fields['sesso']));
      $fields['dataNascita'] = trim($fields['dataNascita']);
      $fields['comuneNascita'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['comuneNascita']))));
      $fields['codiceFiscale'] = strtoupper(trim($fields['codiceFiscale']));
      $fields['citta'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['citta']))));
      $fields['indirizzo'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['indirizzo']))));
      $fields['bes'] = strtoupper(trim($fields['bes']));
      $fields['noteBes'] = trim(str_replace(["\t","\r","\n",'  '], ['','','',' ',],$fields['noteBes']));
      $fields['frequenzaEstero'] = strtoupper(trim($fields['frequenzaEstero']));
      $fields['religione'] = strtoupper(trim($fields['religione']));
      $fields['credito3'] = trim($fields['credito3']);
      $fields['credito4'] = trim($fields['credito4']);
      $fields['classe'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['classe']));
      $fields['username'] = strtolower(trim($fields['username']));
      $fields['password'] = trim($fields['password']);
      $fields['email'] = strtolower(trim($fields['email']));
      $fields['genitore1Cognome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['genitore1Cognome']))));
      $fields['genitore1Nome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['genitore1Nome']))));
      $fields['genitore1CodiceFiscale'] = strtoupper(trim($fields['genitore1CodiceFiscale']));
      $telefono = array();
      foreach (explode(',', $fields['genitore1Telefono']) as $tel) {
        $tel = preg_replace('/\s/', '', $tel);
        $tel = (substr($tel, 0, 3) == '+39') ? substr($tel, 3) : $tel;
        if ($tel != '' && $tel != str_repeat('0', strlen($tel))) {
          $telefono[] = $tel;
        }
      }
      $fields['genitore1Telefono'] = $telefono;
      $fields['genitore1Username'] = strtolower(trim($fields['genitore1Username']));
      $fields['genitore1Password'] = trim($fields['genitore1Password']);
      $fields['genitore1Email'] = strtolower(trim($fields['genitore1Email']));
      $fields['genitore2Cognome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['genitore2Cognome']))));
      $fields['genitore2Nome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['genitore2Nome']))));
      $fields['genitore2CodiceFiscale'] = strtoupper(trim($fields['genitore2CodiceFiscale']));
      $telefono = array();
      foreach (explode(',', $fields['genitore2Telefono']) as $tel) {
        $tel = preg_replace('/\s/', '', $tel);
        $tel = (substr($tel, 0, 3) == '+39') ? substr($tel, 3) : $tel;
        if ($tel != '' && $tel != str_repeat('0', strlen($tel))) {
          $telefono[] = $tel;
        }
      }
      $fields['genitore2Telefono'] = $telefono;
      $fields['genitore2Username'] = strtolower(trim($fields['genitore2Username']));
      $fields['genitore2Password'] = trim($fields['genitore2Password']);
      $fields['genitore2Email'] = strtolower(trim($fields['genitore2Email']));
      // controlla campi
      if (empty($fields['cognome'])) {
        // cognome può essere vuoto in modifica
        $empty_fields['cognome'] = true;
      }
      if (empty($fields['nome'])) {
        // nome può essere vuoto in modifica
        $empty_fields['nome'] = true;
      }
      if (empty($fields['sesso'])) {
        // sesso può essere vuoto in modifica
        $empty_fields['sesso'] = true;
      }
      if (empty($fields['dataNascita'])) {
        // dataNascita può essere vuoto in modifica
        $empty_fields['dataNascita'] = true;
        $fields['dataNascita'] = null;
      } else {
        $date = \DateTime::createFromFormat('!d/m/Y', $fields['dataNascita']);
        if (!$date || $date->format('d/m/Y') != $fields['dataNascita']) {
          // errore data
          fclose($this->fh);
          $this->fh = null;
          $form->addError(new FormError($this->trans->trans('exception.file_date', array(
            'data' => $fields['dataNascita'], 'num' => $count))));
          return $imported;
        }
        $fields['dataNascita'] = $date;
      }
      if (empty($fields['comuneNascita'])) {
        // comuneNascita può essere vuoto in modifica
        $empty_fields['comuneNascita'] = true;
      }
      if (empty($fields['codiceFiscale'])) {
        // codiceFiscale può essere vuoto in modifica
        $empty_fields['codiceFiscale'] = true;
      }
      if (empty($fields['citta'])) {
        // citta può essere vuoto
        $empty_fields['citta'] = true;
      }
      if (empty($fields['indirizzo'])) {
        // indirizzo può essere vuoto
        $empty_fields['indirizzo'] = true;
      }
      if (empty($fields['bes'])) {
        // bes default
        $empty_fields['bes'] = true;
        $fields['bes'] = 'N';
      }
      if (empty($fields['noteBes'])) {
        // noteBes può essere vuoto
        $empty_fields['noteBes'] = true;
      }
      if (empty($fields['frequenzaEstero'])) {
        // frequenzaEstero default
        $empty_fields['frequenzaEstero'] = true;
        $fields['frequenzaEstero'] = 0;
      } else {
        $fields['frequenzaEstero'] = ($fields['frequenzaEstero'] == 'S') ? 1 : 0;
      }
      if (empty($fields['religione'])) {
        // religione default
        $empty_fields['religione'] = true;
        $fields['religione'] = 'S';
      }
      if (empty($fields['credito3'])) {
        // credito3 default
        $empty_fields['credito3'] = true;
        $fields['credito3'] = 0;
      } else {
        $fields['credito3'] = (int) $fields['credito3'];
      }
      if (empty($fields['credito4'])) {
        // credito4 default
        $empty_fields['credito4'] = true;
        $fields['credito4'] = 0;
      } else {
        $fields['credito4'] = (int) $fields['credito4'];
      }
      if (empty($fields['classe'])) {
        // classe può essere non definita
        $empty_fields['classe'] = true;
        $fields['classe'] = null;
      } else {
        // controlla esistenza di classe
        if ($fields['classe'] == 'NESSUNA') {
          // nessuna classe assegnata
          $fields['classe'] = null;
        } else {
          // classe esistente
          $classe = $this->em->getRepository('App\Entity\Classe')->findOneBy(array(
            'anno' => $fields['classe'][0], 'sezione' => $fields['classe'][1]));
          if (!$classe) {
            // errore: classe
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError($this->trans->trans('exception.file_classe', ['num' => $count])));
            return $imported;
          }
          $fields['classe'] = $classe;
        }
      }
      if (empty($fields['username'])) {
        // crea username
        $empty_fields['username'] = true;
        if (strpos($fields['nome'], ' ') !== false) {
          $nomi = explode(' ', $fields['nome']);
          $username = $nomi[0].$nomi[1][0].'.'.$fields['cognome'];
        } else {
          $username = $fields['nome'].'.'.$fields['cognome'];
        }
        $username = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $username));
        $username = preg_replace('/[^a-z\.]+/', '', $username);
        $result = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
          ->where('a.username LIKE :username')
          ->setParameter(':username', $username.'.s%')
          ->orderBy('a.username', 'DESC')
          ->setMaxResults(1)
          ->getQuery()
          ->getOneOrNullResult();
        $suffix = $result ? (1 + substr($result->getUsername(), -1)) : 1;
        $fields['username'] = $username.'.s'.$suffix;
      }
      if (empty($fields['password'])) {
        // crea password
        $empty_fields['password'] = true;
        $fields['password'] = $this->staff->creaPassword(8);
      }
      if (empty($fields['email'])) {
        // crea email
        $empty_fields['email'] = true;
        $fields['email'] = $fields['username'].'@'.($this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider') ?
          $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider_dominio') :
          $this->reqstack->getSession()->get('/CONFIG/SISTEMA/dominio_default'));
      }
      if (empty($fields['genitore1Cognome'])) {
        // default genitore1Cognome
        $empty_fields['genitore1Cognome'] = true;
        $fields['genitore1Cognome'] = '#NESSUN DATO#';
      }
      if (empty($fields['genitore1Nome'])) {
        // default genitore1Nome
        $empty_fields['genitore1Nome'] = true;
        $fields['genitore1Nome'] = '#NESSUN DATO#';
      }
      if (empty($fields['genitore1CodiceFiscale'])) {
        // default genitore1CodiceFiscale
        $empty_fields['genitore1CodiceFiscale'] = true;
        $fields['genitore1CodiceFiscale'] = bin2hex(random_bytes(6)).substr(uniqid(), -4);
      }
      if (empty($fields['genitore1Telefono'])) {
        // genitore1Telefono può essere vuoto
        $empty_fields['genitore1Telefono'] = true;
      }
      if (empty($fields['genitore1Username'])) {
        // crea genitore1Username
        $empty_fields['genitore1Username'] = true;
        $fields['genitore1Username'] = substr($fields['username'], 0, -2).'f'.substr($fields['username'], -1);
      }
      if (empty($fields['genitore1Password'])) {
        // crea genitore1Password
        $empty_fields['genitore1Password'] = true;
        $fields['genitore1Password'] = $this->staff->creaPassword(8);
      }
      if (empty($fields['genitore1Email'])) {
        // default genitore1Email
        $empty_fields['genitore1Email'] = true;
        $fields['genitore1Email'] = $fields['genitore1Username'].'@'.
          $this->reqstack->getSession()->get('/CONFIG/SISTEMA/dominio_default');
      }
      if (empty($fields['genitore2Cognome'])) {
        // default genitore2Cognome
        $empty_fields['genitore2Cognome'] = true;
        $fields['genitore2Cognome'] = '#NESSUN DATO#';
      }
      if (empty($fields['genitore2Nome'])) {
        // default genitore2Nome
        $empty_fields['genitore2Nome'] = true;
        $fields['genitore2Nome'] = '#NESSUN DATO#';
      }
      if (empty($fields['genitore2CodiceFiscale'])) {
        // default genitore2CodiceFiscale
        $empty_fields['genitore2CodiceFiscale'] = true;
        $fields['genitore2CodiceFiscale'] = bin2hex(random_bytes(6)).substr(uniqid(), -4);
      }
      if (empty($fields['genitore2Telefono'])) {
        // genitore2Telefono può essere vuoto
        $empty_fields['genitore2Telefono'] = true;
      }
      if (empty($fields['genitore2Username'])) {
        // crea genitore2Username
        $empty_fields['genitore2Username'] = true;
        $fields['genitore2Username'] = substr($fields['username'], 0, -2).'g'.substr($fields['username'], -1);
      }
      if (empty($fields['genitore2Password'])) {
        // crea genitore2Password
        $empty_fields['genitore2Password'] = true;
        $fields['genitore2Password'] = $this->staff->creaPassword(8);
      }
      if (empty($fields['genitore2Email'])) {
        // default genitore2Email
        $empty_fields['genitore2Email'] = true;
        $fields['genitore2Email'] = $fields['genitore2Username'].'@'.
          $this->reqstack->getSession()->get('/CONFIG/SISTEMA/dominio_default');
      }
      // controlla modalità di modifica
      $alunno = null;
      $genitore1 = null;
      $genitore2 = null;
      if (!$empty_fields['username'] && !$empty_fields['genitore1Username'] && !$empty_fields['genitore2Username']) {
        // controlla esistenza di alunno
        $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneByUsername($fields['username']);
        $genitore1 = $this->em->getRepository('App\Entity\Genitore')->findOneBy(['username' => $fields['genitore1Username'],
          'alunno' => $alunno]);
        $genitore2 = $this->em->getRepository('App\Entity\Genitore')->findOneBy(['username' => $fields['genitore2Username'],
          'alunno' => $alunno]);
      }
      $modifica = $alunno && $genitore1 && $genitore2;
      // controlla modalità di inserimento
      if (!$modifica) {
        if ($empty_fields['cognome'] || $empty_fields['nome'] || $empty_fields['sesso'] ||
            $empty_fields['dataNascita'] || $empty_fields['comuneNascita'] || $empty_fields['codiceFiscale']) {
          // errore
          fclose($this->fh);
          $this->fh = null;
          $form->addError(new FormError($this->trans->trans('exception.file_required', ['num' => $count])));
          return $imported;
        }
      }
      if ($modifica) {
        // utente esiste
        if ($filtro == 'T' || $filtro == 'E') {
          // modifica utente
          $error = $this->modificaAlunno($alunno, $genitore1, $genitore2, $fields, $empty_fields);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          // dati per la visualizzazione
          $fields['dataNascita'] = $fields['dataNascita']->format('d/m/Y');
          $fields['classe'] = ($fields['classe'] ? $fields['classe'] : '');
          $fields['genitore1Telefono'] = implode(', ', $fields['genitore1Telefono']);
          $fields['genitore2Telefono'] = implode(', ', $fields['genitore2Telefono']);
          foreach ($fields as $k=>$v) {
            if ($empty_fields[$k]) {
              unset($fields[$k]);
            }
          }
          $imported['EDIT'][$count] = $fields;
        } else {
          // nessuna modifica
          $fields['dataNascita'] = $fields['dataNascita']->format('d/m/Y');
          $fields['classe'] = ($fields['classe'] ? $fields['classe'] : '');
          $fields['genitore1Telefono'] = implode(', ', $fields['genitore1Telefono']);
          $fields['genitore2Telefono'] = implode(', ', $fields['genitore2Telefono']);
          foreach ($fields as $k=>$v) {
            if ($empty_fields[$k]) {
              unset($fields[$k]);
            }
          }
          $imported['NONE'][$count] = $fields;
        }
      } else {
        // utente non esiste
        if ($filtro == 'T' || ($filtro == 'N' &&
            !$this->em->getRepository('App\Entity\Alunno')->findOneByCodiceFiscale($fields['codiceFiscale']))) {
          // crea nuovo alunno
          $error = $this->nuovoAlunno($fields);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          // dati per la visualizzazione
          $fields['dataNascita'] = $fields['dataNascita']->format('d/m/Y');
          $fields['classe'] = ($fields['classe'] ? $fields['classe'] : '');
          $fields['genitore1Telefono'] = implode(', ', $fields['genitore1Telefono']);
          $fields['genitore2Telefono'] = implode(', ', $fields['genitore2Telefono']);
          $imported['NEW'][$count] = $fields;
        } else {
          // nessuna modifica
          $fields['dataNascita'] = $fields['dataNascita']->format('d/m/Y');
          $fields['classe'] = ($fields['classe'] ? $fields['classe'] : '');
          $fields['genitore1Telefono'] = implode(', ', $fields['genitore1Telefono']);
          $fields['genitore2Telefono'] = implode(', ', $fields['genitore2Telefono']);
          foreach ($fields as $k=>$v) {
            if ($empty_fields[$k]) {
              unset($fields[$k]);
            }
          }
          $imported['NONEW'][$count] = $fields;
        }
      }
    }
    // ok: chiude file
    fclose($this->fh);
    $this->fh = null;
    return $imported;
  }

  /**
   * Importa i colloqui dei docenti da file CSV
   *
   * @param File $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista dei colloqui importati
   */
  public function importaColloqui(File $file=null, Form $form) {
    $header = array('username', 'sede', 'giorno', 'ora', 'frequenza', 'note');
    $filtro = $form->get('filtro')->getData();
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->addError(new FormError($this->trans->trans($error)));
      return null;
    }
    // lettura dati
    $imported = array();
    $count = 0;
    while (($data = fgetcsv($this->fh)) !== false) {
      $count++;
      if (count($data) == 0 || (count($data) == 1 && $data[0] == '')) {
        // riga vuota, salta
        continue;
      }
      // controllo numero campi
      if (count($data) != count($header)) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_data', ['num' => $count])));
        return $imported;
      }
      // lettura campi
      $fields = array();
      $empty_fields = array();
      foreach ($data as $key=>$val) {
        $fields[$this->header[$key]] = $val;
      }
      // formattazione campi
      $fields['username'] = strtolower(trim($fields['username']));
      $fields['sede'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['sede']));
      $fields['giorno'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['giorno']));
      $fields['ora'] = trim($fields['ora']);
      $fields['frequenza'] = strtoupper(trim($fields['frequenza']));
      $fields['note'] = trim($fields['note']);
      // controlla campi obbligatori
      if (empty($fields['username']) || empty($fields['sede']) || empty($fields['giorno']) || empty($fields['ora']) ||
           empty($fields['frequenza'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_required', ['num' => $count])));
        return $imported;
      }
      // controlla esistenza di docente
      $lista = $this->em->getRepository('App\Entity\Docente')->findByUsername($fields['username']);
      if (count($lista) == 0) {
        // errore: docente non esiste
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_mancante', ['num' => $count])));
        return $imported;
      } elseif (count($lista) > 1) {
        // errore: docente duplicato
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_duplicato', ['num' => $count])));
        return $imported;
      }
      $docente = $lista[0];
      // controlla esistenza di sede
      $lista = $this->em->getRepository('App\Entity\Sede')->findByCitta($fields['sede']);
      if (count($lista) != 1) {
        // errore: sede
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_sede', ['num' => $count])));
        return $imported;
      }
      $sede = $lista[0];
      // legge orario
      $scansione_oraria = $this->em->getRepository('App\Entity\ScansioneOraria')->createQueryBuilder('so')
        ->join('so.orario', 'o')
        ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede')
        ->setParameters(['data' => (new \DateTime())->format('Y-m-d'), 'sede' => $sede])
        ->getQuery()
        ->getResult();
      if (!$scansione_oraria) {
        // errore: orario
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_orario', ['num' => $count])));
        return $imported;
      }
      $ore = array();
      foreach ($scansione_oraria as $so) {
        $ore[$so->getGiorno()][$so->getOra()] = [$so->getInizio()->format('H:i'),
          $so->getFine()->format('H:i'), $so->getDurata()];
      }
      // controlla giorno
      $lista_giorni = ['DO', 'LU', 'MA', 'ME', 'GI', 'VE', 'SA'];
      $giorno = array_search($fields['giorno'], $lista_giorni);
      if ($giorno === false || !isset($ore[$giorno])) {
        // errore: giorno
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_giorno', ['num' => $count])));
        return $imported;
      }
      // controlla ora
      $ora = intval($fields['ora']);
      if (!isset($ore[$giorno][$ora])) {
        // errore: ora
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_ora', ['num' => $count])));
        return $imported;
      }
      // controlla frequenza
      $lista_frequenza = ['S', '1', '2', '3', '4'];
      $frequenza = $fields['frequenza'];
      if (!in_array($frequenza, $lista_frequenza)) {
        // errore: frequenza
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_frequenza', ['num' => $count])));
        return $imported;
      }
      // controlla esistenza di colloquio
      $colloquio = $this->em->getRepository('App\Entity\Colloquio')->findOneBy(['docente' => $docente,
        'orario' => $scansione_oraria[0]->getOrario()]);
      if ($colloquio) {
        // colloquio esiste
        if ($filtro == 'T' || $filtro == 'E') {
          // modifica colloquio
          $error = $this->modificaColloquio($colloquio, $giorno, $ora, $frequenza, $fields['note']);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['EDIT'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONE'][$count] = $fields;
        }
      } else {
        // crea nuovo colloquio
        if ($filtro == 'T' || $filtro == 'N') {
          // inserisce
          $error = $this->nuovoColloquio($docente, $scansione_oraria[0]->getOrario(), $giorno, $ora,
            $frequenza, $fields['note']);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['NEW'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONEW'][$count] = $fields;
        }
      }
    }
    // ok
    fclose($this->fh);
    $this->fh = null;
    return $imported;
  }

  /**
   * Importa i dati del personale ATA da file CSV
   *
   * @param File $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista degli ATA importati
   */
  public function importaAta(File $file=null, Form $form) {
    $header = array('cognome', 'nome', 'sesso', 'codiceFiscale', 'username', 'password', 'email', 'tipo', 'segreteria', 'sede');
    $filtro = $form->get('filtro')->getData();
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->addError(new FormError($this->trans->trans($error)));
      return null;
    }
    // lettura dati
    $imported = array();
    $count = 0;
    while (($data = fgetcsv($this->fh)) !== false) {
      $count++;
      if (count($data) == 0 || (count($data) == 1 && $data[0] == '')) {
        // riga vuota, salta
        continue;
      }
      // controllo numero campi
      if (count($data) != count($header)) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_data', ['num' => $count])));
        return $imported;
      }
      // lettura campi
      $fields = array();
      $empty_fields = array();
      foreach ($data as $key=>$val) {
        $fields[$this->header[$key]] = $val;
      }
      // formattazione campi
      $fields['cognome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['cognome']))));
      $fields['nome'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['nome']))));
      $fields['sesso'] = strtoupper(trim($fields['sesso']));
      $fields['codiceFiscale'] = strtoupper(trim($fields['codiceFiscale']));
      $fields['username'] = strtolower(trim($fields['username']));
      $fields['password'] = trim($fields['password']);
      $fields['email'] = strtolower(trim($fields['email']));
      $fields['tipo'] = strtoupper(trim($fields['tipo']));
      $fields['segreteria'] = strtoupper(trim($fields['segreteria']));
      $fields['sede'] = strtoupper(trim($fields['sede']));
      // controlla campi obbligatori
      if (empty($fields['cognome']) || empty($fields['nome']) || empty($fields['sesso'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_required', ['num' => $count])));
        return $imported;
      }
      if (empty($fields['codiceFiscale'])) {
        // valore null
        $empty_fields['codiceFiscale'] = true;
        $fields['codiceFiscale'] = null;
      }
      if (empty($fields['username'])) {
        // crea username
        $empty_fields['username'] = true;
        if (strpos($fields['nome'], ' ') !== false) {
          $nomi = explode(' ', $fields['nome']);
          $username = $nomi[0].$nomi[1][0].'.'.$fields['cognome'];
        } else {
          $username = $fields['nome'].'.'.$fields['cognome'];
        }
        $username = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $username));
        $fields['username'] = preg_replace('/[^a-z\.]+/', '', $username);
      }
      if (empty($fields['password'])) {
        // crea password
        $empty_fields['password'] = true;
        $fields['password'] = $this->staff->creaPassword(8);
      }
      if (empty($fields['email'])) {
        // crea finta email
        $empty_fields['email'] = true;
        $fields['email'] = $fields['username'].'@'.$this->reqstack->getSession()->get('/CONFIG/SISTEMA/dominio_default');
      }
      if (empty($fields['tipo'])) {
        // default: amministrativo
        $empty_fields['tipo'] = true;
        $fields['tipo'] = 'A';
      }
      if (empty($fields['segreteria'])) {
        // default: no
        $empty_fields['segreteria'] = true;
        $fields['segreteria'] = false;
      } else {
        // valore dipende da tipo
        $fields['segreteria'] = ($fields['tipo'] == 'A' || $fields['tipo'] == 'D') ?
          ($fields['segreteria'] == 'S') : false;
      }
      if (empty($fields['sede'])) {
        // valore null
        $empty_fields['sede'] = true;
        $fields['sede'] = null;
      }
      // controlla esistenza
      $ata = $this->em->getRepository('App\Entity\Ata')->findOneByUsername($fields['username']);
      if ($ata) {
        // utente esiste
        if ($filtro == 'T' || $filtro == 'E') {
          // modifica utente
          if (isset($empty_fields['username'])) {
            // errore: non modifica utente con username generata automaticamente
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError($this->trans->trans('exception.file_duplicated', ['num' => $count])));
            return $imported;
          }
          $error = $this->modificaAta($ata, $fields, $empty_fields);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['EDIT'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONE'][$count] = $fields;
        }
      } else {
        // utente non esiste
        if ($filtro == 'T' || $filtro == 'N') {
          // crea nuovo
          $error = $this->nuovoAta($fields);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['NEW'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONEW'][$count] = $fields;
        }
      }
    }
    // ok
    fclose($this->fh);
    $this->fh = null;
    return $imported;
  }

  /**
   * Importa l'orario dei docenti da file CSV
   *
   * @param File $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista degli orari importati
   */
  public function importaOrario(File $file=null, Form $form) {
    $header = array('username', 'sede', 'giorno', 'ora', 'classe', 'materia');
    $filtro = $form->get('filtro')->getData();
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->addError(new FormError($this->trans->trans($error)));
      return null;
    }
    // lettura dati
    $imported = array();
    $count = 0;
    while (($data = fgetcsv($this->fh)) !== false) {
      $count++;
      if (count($data) == 0 || (count($data) == 1 && $data[0] == '')) {
        // riga vuota, salta
        continue;
      }
      // controllo numero campi
      if (count($data) != count($header)) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_data', ['num' => $count])));
        return $imported;
      }
      // lettura campi
      $fields = array();
      $empty_fields = array();
      foreach ($data as $key=>$val) {
        $fields[$this->header[$key]] = $val;
      }
      // formattazione campi
      $fields['username'] = strtolower(trim($fields['username']));
      $fields['sede'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['sede']));
      $fields['giorno'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['giorno']));
      $fields['ora'] = trim($fields['ora']);
      $fields['classe'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['classe']));
      $fields['materia'] = strtoupper(str_replace([' ',',','(',')',"'","`","\t","\r","\n"], '',
        iconv('UTF-8', 'ASCII//TRANSLIT', $fields['materia'])));
      // controlla campi obbligatori
      if (empty($fields['username']) || empty($fields['sede']) || empty($fields['giorno']) || empty($fields['ora']) ||
          empty($fields['classe']) || empty($fields['materia'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_required', ['num' => $count])));
        return $imported;
      }
      // controlla esistenza di docente
      $lista = $this->em->getRepository('App\Entity\Docente')->findByUsername($fields['username']);
      if (count($lista) == 0) {
        // errore: docente non esiste
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_mancante', ['num' => $count])));
        return $imported;
      } elseif (count($lista) > 1) {
        // errore: docente duplicato
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_duplicato', ['num' => $count])));
        return $imported;
      }
      $docente = $lista[0];
      // controlla esistenza di sede
      $lista = $this->em->getRepository('App\Entity\Sede')->findByCitta($fields['sede']);
      if (count($lista) != 1) {
        // errore: sede
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_sede', ['num' => $count])));
        return $imported;
      }
      $sede = $lista[0];
      // legge orario
      $definizione_orario = $this->em->getRepository('App\Entity\Orario')->createQueryBuilder('o')
        ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede')
        ->setParameters(['data' => (new \DateTime())->format('Y-m-d'), 'sede' => $sede])
        ->getQuery()
        ->getResult();
      $scansione_oraria = $this->em->getRepository('App\Entity\ScansioneOraria')->createQueryBuilder('so')
        ->join('so.orario', 'o')
        ->where('o.id=:orario')
        ->setParameters(['orario' => ($definizione_orario ? $definizione_orario[0] : null)])
        ->getQuery()
        ->getResult();
      if (!$scansione_oraria) {
        // errore: orario
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_orario', ['num' => $count])));
        return $imported;
      }
      $ore = array();
      foreach ($scansione_oraria as $so) {
        $ore[$so->getGiorno()][$so->getOra()] = [$so->getInizio()->format('H:i'),
          $so->getFine()->format('H:i'), $so->getDurata()];
      }
      // controlla giorno
      $lista_giorni = ['DO', 'LU', 'MA', 'ME', 'GI', 'VE', 'SA'];
      $giorno = array_search($fields['giorno'], $lista_giorni);
      if ($giorno === false || !isset($ore[$giorno])) {
        // errore: giorno
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_giorno', ['num' => $count])));
        return $imported;
      }
      // controlla ora
      $ora = intval($fields['ora']);
      if (!isset($ore[$giorno][$ora])) {
        // errore: ora
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_ora', ['num' => $count])));
        return $imported;
      }
      // controlla esistenza di classe
      $classe = null;
      if ($fields['classe'] != '---') {
        $lista = $this->em->getRepository('App\Entity\Classe')->findBy(array(
          'anno' => $fields['classe'][0],
          'sezione' => $fields['classe'][1]));
        if (count($lista) != 1 || strlen($fields['classe']) != 2) {
          // errore: classe
          fclose($this->fh);
          $this->fh = null;
          $form->addError(new FormError($this->trans->trans('exception.file_classe', ['num' => $count])));
          return $imported;
        }
        $classe = $lista[0];
      }
      // controlla esistenza di materia
      $materia = null;
      if ($fields['materia'] != '---') {
        $lista = $this->em->getRepository('App\Entity\Materia')->findByNomeNormalizzato($fields['materia']);
        if (count($lista) != 1) {
          // errore: materia
          fclose($this->fh);
          $this->fh = null;
          $form->addError(new FormError($this->trans->trans('exception.file_materia', ['num' => $count])));
          return $imported;
        }
        $materia = $lista[0];
      }
      // controlla esistenza cattedra
      if ($classe && $materia) {
        $lista = $this->em->getRepository('App\Entity\Cattedra')->findBy(['docente' => $docente,
          'classe' => $classe, 'materia' => $materia]);
        if (count($lista) != 1) {
          // errore: cattedra
          fclose($this->fh);
          $this->fh = null;
          $form->addError(new FormError($this->trans->trans('exception.file_cattedra_mancante', ['num' => $count])));
          return $imported;
        }
        $cattedra = $lista[0];
      } else {
        // cancella dato
        $classe = null;
        $materia = null;
        $cattedra = null;
      }
      // controlla esistenza di orario
      $orario = $this->em->getRepository('App\Entity\OrarioDocente')->createQueryBuilder('od')
        ->join('od.orario', 'o')
        ->join('od.cattedra', 'c')
        ->where('o.id=:orario AND c.docente=:docente AND od.giorno=:giorno AND od.ora=:ora')
        ->setParameters(['orario' => $definizione_orario[0], 'docente' => $docente,
          'giorno' => $giorno, 'ora' => $ora])
        ->getQuery()
        ->getOneOrNullResult();
      if ($orario) {
        // orario esiste
        if ($filtro == 'T' || $filtro == 'E') {
          // modifica orario
          $error = $this->modificaOrario($orario, $cattedra);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['EDIT'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONE'][$count] = $fields;
        }
      } else {
        // crea nuovo orario
        if (($filtro == 'T' || $filtro == 'N') && $cattedra) {
          // inserisce
          $error = $this->nuovoOrario($definizione_orario[0], $giorno, $ora, $cattedra);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['NEW'][$count] = $fields;
        } else {
          // nessuna modifica
          $imported['NONEW'][$count] = $fields;
        }
      }
    }
    // ok
    fclose($this->fh);
    $this->fh = null;
    return $imported;
  }


  //==================== METODI PRIVATI ====================

  /**
   * Controlla il file caricato
   *
   * @param File $file File da importare
   * @param array $header Lista dei campi da importare
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function checkFile(File $file=null, $header) {
    $this->fh = null;
    $this->header = array();
    if (!$file) {
      // errore file mancante
      return 'exception.file_mancante';
    }
    // apre file
    $this->fh = fopen($file->getPathname(), 'r');
    if (!$this->fh) {
      // errore di lettura
      return 'exception.file_read';
    }
    // controlla header
    $row = fgetcsv($this->fh);
    if (!$row) {
      // file vuoto o errore di lettura
      return 'exception.file_read';
    }
    $row = array_map('strtolower', $row);
    if (count($row) != count($header)) {
      // numero campi
      return 'exception.file_field';
    }
    foreach ($header as $field) {
      if (($pos = array_search(strtolower($field), $row)) === false) {
        // campo mancante
        $this->header = array();
        return 'exception.file_field';
      }
      $this->header[$pos] = $field;
    }
    // file ok
    return null;
  }

  /**
   * Crea un nuovo docente
   *
   * @param array $fields Lista dei dati del docente
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function nuovoDocente($fields) {
    // crea oggetto docente
    $docente = (new Docente())
      ->setUsername($fields['username'])
      ->setPasswordNonCifrata($fields['password'])
      ->setEmail($fields['email'])
      ->setAbilitato(true)
      ->setNome($fields['nome'])
      ->setCognome($fields['cognome'])
      ->setSesso($fields['sesso'])
      ->setCodiceFiscale($fields['codiceFiscale']);
    $password = $this->hasher->hashPassword($docente, $docente->getPasswordNonCifrata());
    $docente->setPassword($password);
    // valida dati
    $errors = $this->validator->validate($docente);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // ok, memorizza su db
      $this->em->persist($docente);
      $this->em->flush();
      // provisioning
      $provisioning = (new Provisioning())
        ->setUtente($docente)
        ->setFunzione('creaUtente')
        ->setDati(['password' => $docente->getPasswordNonCifrata()]);
      $this->em->persist($provisioning);
      $this->em->flush();
      return null;
    }
  }

  /**
   * Modifica un docente esistente
   *
   * @param Docente $docente Docente da modificare
   * @param array $fields Lista dei dati del docente
   * @param array $empty_fields Lista dei dati nulli
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function modificaDocente(Docente $docente, &$fields, $empty_fields) {
    // modifica dati obbligatori
    $docente
      ->setNome($fields['nome'])
      ->setCognome($fields['cognome'])
      ->setSesso($fields['sesso']);
    // modifica dati opzionali solo se specificati
    if (!isset($empty_fields['email'])) {
      $docente->setEmail($fields['email']);
    } else {
      unset($fields['email']);
    }
    if (!isset($empty_fields['codiceFiscale'])) {
      $docente->setCodiceFiscale($fields['codiceFiscale']);
    } else {
      unset($fields['codiceFiscale']);
    }
    if (!isset($empty_fields['password'])) {
      $docente->setPasswordNonCifrata($fields['password']);
      $password = $this->hasher->hashPassword($docente, $docente->getPasswordNonCifrata());
      $docente->setPassword($password);
    } else {
      unset($fields['password']);
    }
    // valida dati
    $errors = $this->validator->validate($docente);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // provisioning
      $provisioning = (new Provisioning())
        ->setUtente($docente)
        ->setFunzione('modificaUtente')
        ->setDati([]);
      $this->em->persist($provisioning);
      if (!isset($empty_fields['password'])) {
        $provisioning = (new Provisioning())
          ->setUtente($docente)
          ->setFunzione('passwordUtente')
          ->setDati(['password' => $docente->getPasswordNonCifrata()]);
        $this->em->persist($provisioning);
      }
      // ok, memorizza su db
      $this->em->flush();
      return null;
    }
  }

  /**
   * Crea una nuova cattedra
   *
   * @param array $fields Lista dei dati della cattedra
   * @param Docente $docente Docente esistente
   * @param Classe $classe Classe esistente
   * @param Materia $materia Materia esistente
   * @param Alunno $alunno Alunno esistente (opzionale, usato per sostegno)
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function nuovaCattedra($fields, Docente $docente, Classe $classe, Materia $materia, Alunno $alunno=null) {
    // crea oggetto cattedra
    $cattedra = (new Cattedra())
      ->setAttiva(true)
      ->setSupplenza($fields['supplenza'])
      ->setTipo($fields['tipo'])
      ->setMateria($materia)
      ->setDocente($docente)
      ->setClasse($classe)
      ->setAlunno($alunno);
    // valida dati
    $errors = $this->validator->validate($cattedra);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // ok, memorizza su db
      $this->em->persist($cattedra);
      $this->em->flush();
      // provisioning
      $provisioning = (new Provisioning())
        ->setUtente($cattedra->getDocente())
        ->setFunzione('aggiungeCattedra')
        ->setDati(['cattedra' => $cattedra->getId()]);
      $this->em->persist($provisioning);
      $this->em->flush();
      return null;
    }
  }

  /**
   * Crea un nuovo alunno e relativo genitore
   *
   * @param array $fields Lista dei dati dell'alunno
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function nuovoAlunno(&$fields) {
    // crea utente alunno
    $alunno = (new Alunno())
      ->setUsername($fields['username'])
      ->setPasswordNonCifrata($fields['password'])
      ->setEmail($fields['email'])
      ->setAbilitato(true)
      ->setNome($fields['nome'])
      ->setCognome($fields['cognome'])
      ->setSesso($fields['sesso'])
      ->setDataNascita($fields['dataNascita'])
      ->setComuneNascita($fields['comuneNascita'])
      ->setCodiceFiscale($fields['codiceFiscale'])
      ->setCitta($fields['citta'])
      ->setIndirizzo($fields['indirizzo'])
      ->setBes($fields['bes'])
      ->setNoteBes($fields['noteBes'])
      ->setFrequenzaEstero($fields['frequenzaEstero'])
      ->setReligione($fields['religione'])
      ->setCredito3($fields['credito3'])
      ->setCredito4($fields['credito4'])
      ->setClasse($fields['classe']);
    $password = $this->hasher->hashPassword($alunno, $alunno->getPasswordNonCifrata());
    $alunno->setPassword($password);
    // valida dati alunno
    $errors = $this->validator->validate($alunno);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().' (alunno): '.$errors[0]->getMessage().
        ' ['.$fields[$errors[0]->getPropertyPath()].']';
    }
    $this->em->persist($alunno);
    // crea utente genitore1
    $genitore = (new Genitore())
      ->setUsername($fields['genitore1Username'])
      ->setPasswordNonCifrata($fields['genitore1Password'])
      ->setEmail($fields['genitore1Email'])
      ->setAbilitato(true)
      ->setNome($fields['genitore1Nome'])
      ->setCognome($fields['genitore1Cognome'])
      ->setSesso('M')
      ->setCodiceFiscale($fields['genitore1CodiceFiscale'])
      ->setNumeriTelefono($fields['genitore1Telefono'])
      ->setAlunno($alunno);
    $password = $this->hasher->hashPassword($genitore, $genitore->getPasswordNonCifrata());
    $genitore->setPassword($password);
    // valida dati genitore
    $errors = $this->validator->validate($genitore);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().' (genitore1): '.$errors[0]->getMessage().
        ' ['.$fields[$errors[0]->getPropertyPath()].']';
    }
    $this->em->persist($genitore);
    // crea utente genitore2
    $genitore = (new Genitore())
      ->setUsername($fields['genitore2Username'])
      ->setPasswordNonCifrata($fields['genitore2Password'])
      ->setEmail($fields['genitore2Email'])
      ->setAbilitato(true)
      ->setNome($fields['genitore2Nome'])
      ->setCognome($fields['genitore2Cognome'])
      ->setSesso('F')
      ->setCodiceFiscale($fields['genitore2CodiceFiscale'])
      ->setNumeriTelefono($fields['genitore2Telefono'])
      ->setAlunno($alunno);
    $password = $this->hasher->hashPassword($genitore, $genitore->getPasswordNonCifrata());
    $genitore->setPassword($password);
    // valida dati genitore
    $errors = $this->validator->validate($genitore);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().' (genitore2): '.$errors[0]->getMessage().
        ' ['.$fields[$errors[0]->getPropertyPath()].']';
    }
    $this->em->persist($genitore);
    // provisioning (per alunno)
    $provisioning = (new Provisioning())
      ->setUtente($alunno)
      ->setFunzione('creaUtente')
      ->setDati(['password' => $alunno->getPasswordNonCifrata()]);
    $this->em->persist($provisioning);
    if ($alunno->getClasse()) {
      // inserisce in classe
      $provisioning = (new Provisioning())
        ->setUtente($alunno)
        ->setFunzione('aggiungeAlunnoClasse')
        ->setDati(['classe' => $alunno->getClasse()->getId()]);
      $this->em->persist($provisioning);
    }
    // ok, memorizza su db
    $this->em->flush();
    return null;
  }

  /**
   * Modifica un alunno esistente
   *
   * @param Alunno $alunno Alunno da modificare
   * @param Genitore $genitore1 Genitore 1 da modificare
   * @param Genitore $genitore2 Genitore 2 da modificare
   * @param array $fields Lista dei dati dell'alunno
   * @param array $empty_fields Lista dei dati nulli
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function modificaAlunno(Alunno $alunno, Genitore $genitore1, Genitore $genitore2, &$fields, $empty_fields) {
    // modifica dati di alunno
    if (!$empty_fields['password']) {
      $alunno->setPasswordNonCifrata($fields['password']);
      $password = $this->hasher->hashPassword($alunno, $alunno->getPasswordNonCifrata());
      $alunno->setPassword($password);
    }
    if (!$empty_fields['email']) {
      $alunno->setEmail($fields['email']);
    }
    if (!$empty_fields['nome']) {
      $alunno->setNome($fields['nome']);
    }
    if (!$empty_fields['cognome']) {
      $alunno->setCognome($fields['cognome']);
    }
    if (!$empty_fields['sesso']) {
      $alunno->setSesso($fields['sesso']);
    }
    if (!$empty_fields['dataNascita']) {
      $alunno->setDataNascita($fields['dataNascita']);
    }
    if (!$empty_fields['comuneNascita']) {
      $alunno->setComuneNascita($fields['comuneNascita']);
    }
    if (!$empty_fields['codiceFiscale']) {
      $alunno->setCodiceFiscale($fields['codiceFiscale']);
    }
    if (!$empty_fields['citta']) {
      $alunno->setCitta($fields['citta']);
    }
    if (!$empty_fields['indirizzo']) {
      $alunno->setIndirizzo($fields['indirizzo']);
    }
    if (!$empty_fields['bes']) {
      $alunno->setBes($fields['bes']);
    }
    if (!$empty_fields['noteBes']) {
      $alunno->setNoteBes($fields['noteBes']);
    }
    if (!$empty_fields['frequenzaEstero']) {
      $alunno->setFrequenzaEstero($fields['frequenzaEstero']);
    }
    if (!$empty_fields['religione']) {
      $alunno->setReligione($fields['religione']);
    }
    if (!$empty_fields['credito3']) {
      $alunno->setCredito3($fields['credito3']);
    }
    if (!$empty_fields['credito4']) {
      $alunno->setCredito4($fields['credito4']);
    }
    if (!$empty_fields['classe']) {
      $classePrec = $alunno->getClasse();
      $alunno->setClasse($fields['classe']);
    }
    // valida dati alunno
    $errors = $this->validator->validate($alunno);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().' (alunno): '.$errors[0]->getMessage().
        ' ['.$fields[$errors[0]->getPropertyPath()].']';
    }
    // modifica dati di genitore1
    if (!$empty_fields['genitore1Password']) {
      $genitore1->setPasswordNonCifrata($fields['genitore1Password']);
      $password = $this->hasher->hashPassword($genitore1, $genitore1->getPasswordNonCifrata());
      $genitore1->setPassword($password);
    }
    if (!$empty_fields['genitore1Email']) {
      $genitore1->setEmail($fields['genitore1Email']);
    }
    if (!$empty_fields['genitore1Nome']) {
      $genitore1->setNome($fields['genitore1Nome']);
    }
    if (!$empty_fields['genitore1Cognome']) {
      $genitore1->setCognome($fields['genitore1Cognome']);
    }
    if (!$empty_fields['genitore1CodiceFiscale']) {
      $genitore1->setCodiceFiscale($fields['genitore1CodiceFiscale']);
    }
    if (!$empty_fields['genitore1Telefono']) {
      $genitore1->setNumeriTelefono($fields['genitore1Telefono']);
    }
    // valida dati genitore1
    $errors = $this->validator->validate($genitore1);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().' (genitore1): '.$errors[0]->getMessage().
        ' ['.$fields[$errors[0]->getPropertyPath()].']';
    }
    // modifica dati di genitore2
    if (!$empty_fields['genitore2Password']) {
      $genitore2->setPasswordNonCifrata($fields['genitore2Password']);
      $password = $this->hasher->hashPassword($genitore2, $genitore2->getPasswordNonCifrata());
      $genitore2->setPassword($password);
    }
    if (!$empty_fields['genitore2Email']) {
      $genitore2->setEmail($fields['genitore2Email']);
    }
    if (!$empty_fields['genitore2Nome']) {
      $genitore2->setNome($fields['genitore2Nome']);
    }
    if (!$empty_fields['genitore2Cognome']) {
      $genitore2->setCognome($fields['genitore2Cognome']);
    }
    if (!$empty_fields['genitore2CodiceFiscale']) {
      $genitore2->setCodiceFiscale($fields['genitore2CodiceFiscale']);
    }
    if (!$empty_fields['genitore2Telefono']) {
      $genitore1->setNumeriTelefono($fields['genitore2Telefono']);
    }
    // valida dati genitore2
    $errors = $this->validator->validate($genitore2);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().' (genitore2): '.$errors[0]->getMessage().
        ' ['.$fields[$errors[0]->getPropertyPath()].']';
    }
    // provisioning
    $provisioning = (new Provisioning())
      ->setUtente($alunno)
      ->setFunzione('modificaUtente')
      ->setDati([]);
    $this->em->persist($provisioning);
    if (!$empty_fields['classe'] && $alunno->getClasse() && !$classePrec) {
      // inserisce in classe
      $provisioning = (new Provisioning())
        ->setUtente($alunno)
        ->setFunzione('aggiungeAlunnoClasse')
        ->setDati(['classe' => $alunno->getClasse()->getId()]);
      $this->em->persist($provisioning);
    } elseif (!$empty_fields['classe'] && $alunno->getClasse() && $classePrec &&
              $alunno->getClasse()->getId() != $classePrec->getId()) {
        // cambia classe
        $provisioning = (new Provisioning())
          ->setUtente($alunno)
          ->setFunzione('rimuoveAlunnoClasse')
          ->setDati(['classe' => $classePrec->getId()]);
        $this->em->persist($provisioning);
        $provisioning = (new Provisioning())
          ->setUtente($alunno)
          ->setFunzione('aggiungeAlunnoClasse')
          ->setDati(['classe' => $alunno->getClasse()->getId()]);
        $this->em->persist($provisioning);
    } elseif (!$empty_fields['classe'] && !$alunno->getClasse() && $classePrec) {
        // rimuove classe
        $provisioning = (new Provisioning())
          ->setUtente($alunno)
          ->setFunzione('rimuoveAlunnoClasse')
          ->setDati(['classe' => $classePrec->getId()]);
        $this->em->persist($provisioning);
    }
    // ok, memorizza su db
    $this->em->flush();
    return null;
  }

  /**
   * Crea un nuovo colloquio
   *
   * @param Docente $docente Docente che deve fare il colloquio
   * @param Orario $orario Orario per la sede
   * @param int $giorno Giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   * @param int $ora Numero dell'ora di lezione [1,2,...]
   * @param string $frequenza Frequenza del colloquio [S=settimanale, 1=prima settimana, 2=seconda settimana, 3=terza settimana, 4=quarta settimana]
   * @param string $note Note informative sul colloquio
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function nuovoColloquio(Docente $docente, Orario $orario, $giorno, $ora, $frequenza, $note) {
    // crea oggetto colloquio
    $colloquio = (new Colloquio())
      ->setDocente($docente)
      ->setOrario($orario)
      ->setGiorno($giorno)
      ->setOra($ora)
      ->setFrequenza($frequenza)
      ->setNote($note);
    // valida dati
    $errors = $this->validator->validate($colloquio);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // ok, memorizza su db
      $this->em->persist($colloquio);
      $this->em->flush();
      return null;
    }
  }

  /**
   * Modifica un colloquio esistente
   *
   * @param Colloquio $colloquio Colloquio da modificare
   * @param int $giorno Giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   * @param int $ora Numero dell'ora di lezione [1,2,...]
   * @param string $frequenza Frequenza del colloquio [S=settimanale, 1=prima settimana, 2=seconda settimana, 3=terza settimana, 4=quarta settimana]
   * @param string $note Note informative sul colloquio
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function modificaColloquio(Colloquio $colloquio, $giorno, $ora, $frequenza, $note) {
    // crea oggetto colloquio
    $colloquio
      ->setGiorno($giorno)
      ->setOra($ora)
      ->setFrequenza($frequenza)
      ->setNote($note);
    // valida dati
    $errors = $this->validator->validate($colloquio);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // ok, memorizza su db
      $this->em->flush();
      return null;
    }
  }

  /**
   * Crea un nuovo ATA
   *
   * @param array $fields Lista dei dati dell'utente
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function nuovoAta($fields) {
    // legge sede
    $sede = $this->em->getRepository('App\Entity\Sede')->findOneByCitta($fields['sede']);
    if ($fields['sede'] && !$sede) {
      // errore (restituisce solo il primo)
      $error = $this->trans->trans('exception.file_ata_sede');
      return $error;
    }
    // crea oggetto
    $ata = (new Ata())
      ->setUsername($fields['username'])
      ->setPasswordNonCifrata($fields['password'])
      ->setEmail($fields['email'])
      ->setAbilitato(true)
      ->setNome($fields['nome'])
      ->setCognome($fields['cognome'])
      ->setSesso($fields['sesso'])
      ->setCodiceFiscale($fields['codiceFiscale'])
      ->setSede($sede)
      ->setTipo($fields['tipo'])
      ->setSegreteria($fields['segreteria']);
    $password = $this->hasher->hashPassword($ata, $ata->getPasswordNonCifrata());
    $ata->setPassword($password);
    // valida dati
    $errors = $this->validator->validate($ata);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // ok, memorizza su db
      $this->em->persist($ata);
      $this->em->flush();
      return null;
    }
  }

  /**
   * Modifica un ATA esistente
   *
   * @param Ata $ata Utente da modificare
   * @param array $fields Lista dei dati dell'utente
   * @param array $empty_fields Lista dei dati nulli
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function modificaAta(Ata $ata, &$fields, $empty_fields) {
    // modifica dati obbligatori
    $ata
      ->setNome($fields['nome'])
      ->setCognome($fields['cognome'])
      ->setSesso($fields['sesso']);
    // modifica dati opzionali solo se specificati
    if (!isset($empty_fields['codiceFiscale'])) {
      $ata->setEmail($fields['codiceFiscale']);
    } else {
      unset($fields['codiceFiscale']);
    }
    if (!isset($empty_fields['password'])) {
      $ata->setPasswordNonCifrata($fields['password']);
      $password = $this->hasher->hashPassword($ata, $ata->getPasswordNonCifrata());
      $ata->setPassword($password);
    } else {
      unset($fields['password']);
    }
    if (!isset($empty_fields['email'])) {
      $ata->setEmail($fields['email']);
    } else {
      unset($fields['email']);
    }
    if (!isset($empty_fields['tipo'])) {
      $ata->setTipo($fields['tipo']);
    } else {
      unset($fields['tipo']);
    }
    if (!isset($empty_fields['segreteria'])) {
      $ata->setSegreteria($fields['segreteria']);
    } else {
      unset($fields['segreteria']);
    }
    // legge sede
    $sede = $this->em->getRepository('App\Entity\Sede')->findOneByCitta($fields['sede']);
    if (!isset($empty_fields['sede']) && !$sede) {
      // errore (restituisce solo il primo)
      $error = $this->trans->trans('exception.file_ata_sede');
      return $error;
    }
    if (!isset($empty_fields['sede'])) {
      $ata->setSede($sede);
    } else {
      unset($fields['sede']);
    }
    // valida dati
    $errors = $this->validator->validate($ata);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // ok, memorizza su db
      $this->em->flush();
      return null;
    }
  }

  /**
   * Modifica una cattedra esistente
   * NB: non viene effettuato provisioning
   *
   * @param Cattedra $cattedra Cattedra da modificare
   * @param Alunno $alunno Alunno da modificare
   * @param array $fields Lista dei dati dell'utente
   * @param array $empty_fields Lista dei dati nulli
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function modificaCattedra(Cattedra $cattedra, Alunno $alunno=null, &$fields, $empty_fields) {
    // modifica dati opzionali solo se specificati
    if (!isset($empty_fields['usernameAlunno'])) {
      $cattedra->setAlunno($alunno);
    } else {
      unset($fields['usernameAlunno']);
    }
    if (!isset($empty_fields['tipo'])) {
      $cattedra->setTipo($fields['tipo']);
    } else {
      unset($fields['tipo']);
    }
    if (!isset($empty_fields['supplenza'])) {
      $cattedra->setSupplenza($fields['supplenza']);
    } else {
      unset($fields['supplenza']);
    }
    // valida dati
    $errors = $this->validator->validate($cattedra);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // ok, memorizza su db
      $this->em->flush();
      return null;
    }
  }

  /**
   * Crea un nuovo colloquio
   *
   * @param Orario $orario Orario per la sede
   * @param int $giorno Giorno della settimana [0=domenica, 1=lunedì, ... 6=sabato]
   * @param int $ora Numero dell'ora di lezione [1,2,...]
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function nuovoOrario(Orario $orario, $giorno, $ora, Cattedra $cattedra) {
    // crea oggetto orario
    $orario = (new OrarioDocente())
      ->setOrario($orario)
      ->setGiorno($giorno)
      ->setOra($ora)
      ->setCattedra($cattedra);
    // valida dati
    $errors = $this->validator->validate($orario);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // ok, memorizza su db
      $this->em->persist($orario);
      $this->em->flush();
      return null;
    }
  }

  /**
   * Modifica un orario esistente
   *
   * @param OrarioDocente $orario Orario del docente da modificare
   * @param Cattedra $cattedra Cattedra del docente
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function modificaOrario(OrarioDocente $orario, Cattedra $cattedra=null) {
    if ($cattedra) {
      // modifica catttedra di orario
      $orario->setCattedra($cattedra);
    } else {
      // cancella orario
      $this->em->remove($orario);
    }
    // valida dati
    $errors = $this->validator->validate($orario);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage();
    } else {
      // ok, memorizza su db
      $this->em->flush();
      return null;
    }
  }

}
