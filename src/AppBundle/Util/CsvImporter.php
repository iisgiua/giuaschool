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


namespace AppBundle\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\ValidatorBuilderInterface;
use AppBundle\Entity\Cattedra;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Ata;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Alunno;
use AppBundle\Entity\Genitore;
use AppBundle\Entity\Colloquio;
use AppBundle\Entity\Orario;


/**
 * CsvImporter - classe di utilità per il caricamento dei dati da file CSV
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
   * @var UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   */
  private $encoder;

  /**
   * @var ValidatorInterface $validator Gestore della validazione dei dati
   */
  private $validator;

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
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param ValidatorInterface $validator Gestore della validazione dei dati
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans, UserPasswordEncoderInterface $encoder,
                               ValidatorBuilderInterface $valbuilder) {
    $this->em = $em;
    $this->trans = $trans;
    $this->encoder = $encoder;
    $this->validator = $valbuilder->getValidator();
    $this->fh = null;
    $this->header = array();
  }

  /**
   * Importa i docenti da file CSV
   *
   * @param UploadedFile $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista dei docenti importati
   */
  public function importaDocenti(UploadedFile $file, Form $form) {
    $header = array('cognome', 'nome', 'sesso', 'username', 'password', 'email', 'codiceFiscale');
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->get('file')->addError(new FormError($this->trans->trans($error)));
      return null;
    }
    // lettura dati
    $imported = array();
    $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
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
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_data', ['%num%' => $count])));
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
      $fields['username'] = strtolower(trim($fields['username']));
      $fields['password'] = trim($fields['password']);
      $fields['email'] = trim($fields['email']);
      $fields['codiceFiscale'] = strtoupper(trim($fields['codiceFiscale']));
      // controlla campi obbligatori
      if (empty($fields['cognome']) || empty($fields['nome']) || empty($fields['sesso'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_required', ['%num%' => $count])));
        return $imported;
      }
      if (empty($fields['username'])) {
        // crea username
        $empty_fields['username'] = true;
        $username = $fields['nome'].'.'.$fields['cognome'];
        $username = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $username));
        $fields['username'] = preg_replace('/[^a-z\.]+/', '', $username);
      }
      if (empty($fields['password'])) {
        // crea password
        $empty_fields['password'] = true;
        $fields['password'] = substr(str_shuffle($pwdchars), 0, 5).substr(str_shuffle($pwdchars), 0, 5);
      }
      if (empty($fields['email'])) {
        // crea finta email
        $empty_fields['email'] = true;
        $fields['email'] = $fields['username'].'@noemail.local';
      }
      if (empty($fields['codiceFiscale'])) {
        // valore null
        $empty_fields['codiceFiscale'] = true;
        $fields['codiceFiscale'] = null;
      }
      // controlla esistenza di docente
      $docente = $this->em->getRepository('AppBundle:Docente')->findOneByUsername($fields['username']);
      if ($docente) {
        // docente esiste
        if ($form->get('onlynew')->getData()) {
          // nessuna modifica
          $imported['NONE'][$count] = $fields;
        } else {
          // modifica docente
          if (isset($empty_fields['username'])) {
            // errore: non modifica utente con username generata automaticamente
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError($this->trans->trans('exception.file_duplicated', ['%num%' => $count])));
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
        }
      } else {
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
   * @param UploadedFile $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista dei docenti importati
   */
  public function importaCattedre(UploadedFile $file, Form $form) {
    $header = array('docente','usernameDocente','classe','materia','alunno','usernameAlunno','tipo','supplenza');
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->get('file')->addError(new FormError($this->trans->trans($error)));
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
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_data', ['%num%' => $count])));
        return $imported;
      }
      // lettura campi
      $fields = array();
      $empty_fields = array();
      foreach ($data as $key=>$val) {
        $fields[$this->header[$key]] = $val;
      }
      // formattazione campi
      $fields['docente'] =
        strtoupper(str_replace([' ',"'","`","\t","\r","\n"], '', iconv('UTF-8', 'ASCII//TRANSLIT',$fields['docente'])));
      $fields['usernameDocente'] = strtolower(trim($fields['usernameDocente']));
      $fields['classe'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['classe']));
      $fields['materia'] =
        strtoupper(str_replace([' ',',','(',')',"'","`","\t","\r","\n"], '', iconv('UTF-8', 'ASCII//TRANSLIT',$fields['materia'])));
      $fields['alunno'] =
        strtoupper(str_replace([' ',"'","`","\t","\r","\n"], '', iconv('UTF-8', 'ASCII//TRANSLIT',$fields['alunno'])));
      $fields['usernameAlunno'] = strtolower(trim($fields['usernameAlunno']));
      $fields['tipo'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['tipo']));
      $fields['supplenza'] = ($fields['supplenza'] > 0 ? 1 : 0);
      // controlla campi obbligatori
      if ((empty($fields['docente']) && empty($fields['usernameDocente'])) ||
           empty($fields['classe']) || empty($fields['materia'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_required', ['%num%' => $count])));
        return $imported;
      }
      // controlla esistenza di docente
      if (!empty($fields['usernameDocente'])) {
        $lista = $this->em->getRepository('AppBundle:Docente')->findByUsername($fields['usernameDocente']);
        unset($fields['docente']);
      } else {
        $lista = $this->em->getRepository('AppBundle:Docente')->findByNomeNormalizzato($fields['docente']);
        unset($fields['usernameDocente']);
      }
      if (count($lista) == 0) {
        // errore: docente non esiste
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_mancante', ['%num%' => $count])));
        return $imported;
      } elseif (count($lista) > 1) {
        // errore: docente non esiste
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_duplicato', ['%num%' => $count])));
        return $imported;
      }
      $docente = $lista[0];
      // controlla esistenza di classe
      $lista = $this->em->getRepository('AppBundle:Classe')->findBy(array(
        'anno' => $fields['classe']{0},
        'sezione' => $fields['classe']{1}));
      if (count($lista) != 1) {
        // errore: classe
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_classe', ['%num%' => $count])));
        return $imported;
      }
      $classe = $lista[0];
      // controlla esistenza di materia
      $lista = $this->em->getRepository('AppBundle:Materia')->findByNomeNormalizzato($fields['materia']);
      if (count($lista) != 1) {
        // errore: materia
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_materia', ['%num%' => $count])));
        return $imported;
      }
      $materia = $lista[0];
      // controlla esistenza di alunno
      if (!empty($fields['usernameAlunno'])) {
        $lista = $this->em->getRepository('AppBundle:Alunno')->findByUsername($fields['usernameAlunno']);
        unset($fields['alunno']);
      } elseif (!empty($fields['alunno'])) {
        $lista = $this->em->getRepository('AppBundle:Alunno')->findByNomeNormalizzato($fields['alunno']);
        unset($fields['usernameAlunno']);
      } else {
        // alunno non specificato
        unset($fields['alunno']);
        unset($fields['usernameAlunno']);
        $lista = null;
        $alunno = null;
      }
      if ($lista !== null && count($lista) == 0) {
        // errore: alunno non esiste
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_alunno_mancante', ['%num%' => $count])));
        return $imported;
      } elseif ($lista !== null && count($lista) > 1) {
        // errore: alunno duplicato
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_alunno_duplicato', ['%num%' => $count])));
        return $imported;
      } elseif ($lista !== null) {
        $alunno = $lista[0];
      }
      // controlla tipo
      if (empty($fields['tipo'])) {
        // default
        $fields['tipo'] = 'N';
      }
      // controlli incrociati su sostegno
      if ($materia->getTipo() == 'S') {
        // sostegno
        //-- $fields['tipo'] = 'S';
        if ($alunno && $alunno->getClasse() != $classe) {
          // classe diversa da quella di alunno
          fclose($this->fh);
          $this->fh = null;
          $form->addError(new FormError($this->trans->trans('exception.file_classe', ['%num%' => $count])));
          return $imported;
        }
      } else {
        // materia non è sostegno, nessun alunno deve essere presente
        $alunno = null;
        unset($fields['alunno']);
        unset($fields['usernameAlunno']);
        if ($fields['tipo'] == 'S') {
          // tipo sostegno su materia non di sostegno
          fclose($this->fh);
          $this->fh = null;
          $form->addError(new FormError($this->trans->trans('exception.file_tipo', ['%num%' => $count])));
          return $imported;
        }
      }
      // controlla esistenza di cattedra
      $lista = $this->em->getRepository('AppBundle:Cattedra')->findBy(array(
        'docente' => $docente, 'classe' => $classe, 'materia' => $materia, 'alunno' => $alunno));
      if (count($lista) > 0) {
        // cattedra esiste già: salta
        unset($fields['tipo']);
        unset($fields['supplenza']);
        $imported['NONE'][$count] = $fields;
        continue;
      }
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
    }
    // ok
    fclose($this->fh);
    $this->fh = null;
    return $imported;
  }

  /**
   * Importa gli alunni da file CSV
   *
   * @param UploadedFile $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista dei docenti importati
   */
  public function importaAlunni(UploadedFile $file, Form $form) {
    $header = array('cognome', 'nome', 'sesso', 'dataNascita', 'comuneNascita', 'codiceFiscale',
      'citta', 'indirizzo', 'numeriTelefono', 'bes', 'frequenzaEstero', 'religione', 'credito3', 'credito4',
      'classe', 'email');
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->get('file')->addError(new FormError($this->trans->trans($error)));
      return null;
    }
    // lettura dati
    $imported = array();
    $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
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
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_data', ['%num%' => $count])));
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
      $date = \DateTime::createFromFormat('!d/m/Y', trim($fields['dataNascita']));
      if (!$date || $date->format('d/m/Y') != trim($fields['dataNascita'])) {
        // errore data
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_date', array(
          '%date%' => $fields['dataNascita'], '%num%' => $count))));
        return $imported;
      } else {
        $fields['dataNascita'] = $date;
      }
      $fields['comuneNascita'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['comuneNascita']))));
      $fields['codiceFiscale'] = strtoupper(trim($fields['codiceFiscale']));
      $fields['citta'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['citta']))));
      $fields['indirizzo'] = preg_replace('/\s+/', ' ', ucwords(strtolower(trim($fields['indirizzo']))));
      $telefono = array();
      foreach (explode(',', $fields['numeriTelefono']) as $t) {
        if (trim($t) != '') {
          $telefono[] = trim($t);
        }
      }
      $fields['numeriTelefono'] = $telefono;
      $fields['bes'] = strtoupper(trim($fields['bes']));
      $fields['frequenzaEstero'] = trim($fields['frequenzaEstero']);
      $fields['religione'] = strtoupper(trim($fields['religione']));
      $fields['credito3'] = trim($fields['credito3']);
      $fields['credito4'] = trim($fields['credito4']);
      $fields['classe'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['classe']));
      $fields['email'] = trim($fields['email']);
      // controlla campi obbligatori
      if (empty($fields['cognome']) || empty($fields['nome']) || empty($fields['sesso']) ||
          empty($fields['dataNascita']) || empty($fields['comuneNascita']) || empty($fields['codiceFiscale'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_required', ['%num%' => $count])));
        return $imported;
      }
      // controlla campi opzionali
      if (empty($fields['citta'])) {
        // citta può essere vuoto
        $empty_fields['citta'] = true;
      }
      if (empty($fields['indirizzo'])) {
        // indirizzo può essere vuoto
        $empty_fields['indirizzo'] = true;
      }
      if (empty($fields['numeriTelefono'])) {
        // numeriTelefono può essere vuoto
        $empty_fields['numeriTelefono'] = true;
      }
      if (empty($fields['bes'])) {
        // bes default
        $empty_fields['bes'] = true;
        $fields['bes'] = 'N';
      }
      if (empty($fields['frequenzaEstero'])) {
        // frequenzaEstero default
        $empty_fields['frequenzaEstero'] = true;
        $fields['frequenzaEstero'] = 0;
      } else {
        $fields['frequenzaEstero'] = ($fields['frequenzaEstero'] > 0 ? 1 : 0);
      }
      if (empty($fields['religione'])) {
        // religione non può essere vuoto
        $empty_fields['religione'] = true;
        $fields['religione'] = 'S';
      }
      if (empty($fields['credito3'])) {
        // credito3 default
        $empty_fields['credito3'] = true;
        $fields['credito3'] = 0;
      } else {
        $fields['credito3'] = intval($fields['credito3']);
      }
      if (empty($fields['credito4'])) {
        // credito4 default
        $empty_fields['credito4'] = true;
        $fields['credito4'] = 0;
      } else {
        $fields['credito4'] = intval($fields['credito4']);
      }
      if (empty($fields['classe'])) {
        $empty_fields['classe'] = true;
        $fields['classe'] = null;
      } else {
        // controlla esistenza di classe
        if ($fields['classe'] == 'NESSUNA') {
          // nessuna classe
          $fields['classe'] = null;
        } else {
          // classe esistente
          $lista = $this->em->getRepository('AppBundle:Classe')->findBy(array(
            'anno' => $fields['classe']{0},
            'sezione' => $fields['classe']{1}));
          if (count($lista) != 1) {
            // errore: classe
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError($this->trans->trans('exception.file_classe', ['%num%' => $count])));
            return $imported;
          }
          $fields['classe'] = $lista[0];
        }
      }
      // crea username
      $empty_fields['username'] = true;
      $username = $fields['nome'].'.'.$fields['cognome'];
      $username = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $username));
      $username = preg_replace('/[^a-z\.]+/', '', $username);
      $result = $this->em->createQueryBuilder()
        ->select('COUNT(a) AS cnt')
        ->from('AppBundle:Alunno', 'a')
        ->where('a.username LIKE :username')
        ->setParameter(':username', $username.'.s%')
        ->getQuery()
        ->execute();
      $fields['username'] = $username.'.s'.(1 + $result[0]['cnt']);
      // crea username genitore
      $empty_fields['usernameGenitore'] = true;
      $result = $this->em->createQueryBuilder()
        ->select('COUNT(g) AS cnt')
        ->from('AppBundle:Genitore', 'g')
        ->where('g.username LIKE :username')
        ->setParameter(':username', $username.'.f%')
        ->getQuery()
        ->execute();
      $fields['usernameGenitore'] = $username.'.f'.(1 + $result[0]['cnt']);
      // crea password
      $empty_fields['password'] = true;
      $fields['password'] = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
      if (empty($fields['email'])) {
        // crea finta email
        $empty_fields['email'] = true;
        $fields['email'] = $fields['username'].'@noemail.local';
      }
      // controlla esistenza di alunno (su codice fiscale)
      $alunno = $this->em->getRepository('AppBundle:Alunno')->findOneByCodiceFiscale($fields['codiceFiscale']);
      if ($alunno) {
        // alunno esiste
        if ($form->get('onlynew')->getData()) {
          // nessuna modifica
          $fields['dataNascita'] = $fields['dataNascita']->format('d/m/Y');
          $fields['numeriTelefono'] = implode(', ', $fields['numeriTelefono']);
          $fields['classe'] = ($fields['classe'] ? $fields['classe']->getAnno().' '.$fields['classe']->getSezione() : '');
          foreach ($fields as $k=>$v) {
            if (isset($empty_fields[$k])) {
              unset($fields[$k]);
            }
          }
          $imported['NONE'][$count] = $fields;
        } else {
          // modifica alunno
          $error = $this->modificaAlunno($alunno, $fields, $empty_fields);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          foreach ($fields as $k=>$v) {
            if (isset($empty_fields[$k])) {
              unset($fields[$k]);
            }
          }
          $imported['EDIT'][$count] = $fields;
        }
      } else {
        // crea nuovo alunno
        $error = $this->nuovoAlunno($fields);
        if ($error) {
          // errore
          fclose($this->fh);
          $this->fh = null;
          $form->addError(new FormError('# '.$count.': '.$error));
          return $imported;
        }
        $imported['NEW'][$count] = $fields;
      }
    }
    // ok
    fclose($this->fh);
    $this->fh = null;
    return $imported;
  }


  /**
   * Importa i colloqui dei docenti da file CSV
   *
   * @param UploadedFile $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista dei docenti importati
   */
  public function importaColloqui(UploadedFile $file, Form $form) {
    $header = array('docente', 'username', 'sede', 'giorno', 'ora', 'frequenza', 'note');
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->get('file')->addError(new FormError($this->trans->trans($error)));
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
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_data', ['%num%' => $count])));
        return $imported;
      }
      // lettura campi
      $fields = array();
      $empty_fields = array();
      foreach ($data as $key=>$val) {
        $fields[$this->header[$key]] = $val;
      }
      // formattazione campi
      $fields['docente'] =
        strtoupper(str_replace([' ',"'","`","\t","\r","\n"], '', iconv('UTF-8', 'ASCII//TRANSLIT',$fields['docente'])));
      $fields['username'] = strtolower(trim($fields['username']));
      $fields['sede'] = strtoupper(str_replace([' ',"\t","\r","\n"], '',$fields['sede']));
      $fields['giorno'] =
        strtoupper(str_replace([' ',"'","`","\t","\r","\n"], '', iconv('UTF-8', 'ASCII//TRANSLIT',$fields['giorno'])));
      $fields['ora'] = trim($fields['ora']);
      $fields['frequenza'] = strtoupper(trim($fields['frequenza']));
      $fields['note'] = trim($fields['note']);
      // controlla campi obbligatori
      if ((empty($fields['docente']) && empty($fields['username'])) ||
           empty($fields['sede']) || empty($fields['giorno']) || empty($fields['ora']) ||
           empty($fields['frequenza'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_required', ['%num%' => $count])));
        return $imported;
      }
      // controlla esistenza di docente
      if (!empty($fields['username'])) {
        $lista = $this->em->getRepository('AppBundle:Docente')->findByUsername($fields['username']);
        unset($fields['docente']);
      } else {
        $lista = $this->em->getRepository('AppBundle:Docente')->findByNomeNormalizzato($fields['docente']);
        unset($fields['username']);
      }
      if (count($lista) == 0) {
        // errore: docente non esiste
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_mancante', ['%num%' => $count])));
        return $imported;
      } elseif (count($lista) > 1) {
        // errore: docente duplicato
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_docente_duplicato', ['%num%' => $count])));
        return $imported;
      }
      $docente = $lista[0];
      // controlla esistenza di sede
      $lista = $this->em->getRepository('AppBundle:Sede')->findByCitta($fields['sede']);
      if (count($lista) != 1) {
        // errore: sede
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_sede', ['%num%' => $count])));
        return $imported;
      }
      $sede = $lista[0];
      // legge orario
      $scansione_oraria = $this->em->getRepository('AppBundle:ScansioneOraria')->createQueryBuilder('so')
        ->join('so.orario', 'o')
        ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede')
        ->setParameters(['data' => (new \DateTime())->format('Y-m-d'), 'sede' => $sede])
        ->getQuery()
        ->getResult();
      if (!$scansione_oraria) {
        // errore: orario
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_orario', ['%num%' => $count])));
        return $imported;
      }
      $ore = array();
      foreach ($scansione_oraria as $so) {
        $ore[$so->getGiorno()][$so->getOra()] = [$so->getInizio()->format('H:i'),
          $so->getFine()->format('H:i'), $so->getDurata()];
      }
      // controlla giorno
      $lista_giorni = ['DOMENICA', 'LUNEDI', 'MARTEDI', 'MERCOLEDI', 'GIOVEDI', 'VENERDI', 'SABATO'];
      $giorno = array_search($fields['giorno'], $lista_giorni);
      if ($giorno === false || !isset($ore[$giorno])) {
        // errore: giorno
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_giorno', ['%num%' => $count])));
        return $imported;
      }
      // controlla ora
      $ora = intval($fields['ora']);
      if (!isset($ore[$giorno][$ora])) {
        // errore: ora
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_ora', ['%num%' => $count])));
        return $imported;
      }
      // controlla frequenza
      $lista_frequenza = ['S', '1', '2', '3', '4'];
      $frequenza = $fields['frequenza'];
      if (!in_array($frequenza, $lista_frequenza)) {
        // errore: frequenza
        fclose($this->fh);
        $this->fh = null;
        $form->addError(new FormError($this->trans->trans('exception.file_frequenza', ['%num%' => $count])));
        return $imported;
      }
      // controlla esistenza di colloquio
      $colloquio = $this->em->getRepository('AppBundle:Colloquio')->findBy(array(
        'docente' => $docente, 'orario' => $scansione_oraria[0]->getOrario()));
      if ($colloquio) {
        // colloquio esiste
        if ($form->get('onlynew')->getData()) {
          // nessuna modifica
          $imported['NONE'][$count] = $fields;
        } else {
          // modifica colloquio
          $error = $this->modificaColloquio($colloquio[0], $giorno, $ora, $frequenza, $fields['note']);
          if ($error) {
            // errore
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError('# '.$count.': '.$error));
            return $imported;
          }
          $imported['EDIT'][$count] = $fields;
        }
      } else {
        // crea nuovo colloquio
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
   * @param UploadedFile $file File da importare
   * @param Form $form Form su cui visualizzare gli errori
   *
   * @return array Lista degli ATA importati
   */
  public function importaAta(UploadedFile $file, Form $form) {
    $header = array('cognome', 'nome', 'sesso', 'username', 'password', 'email', 'sede', 'tipo');
    // controllo file
    $error = $this->checkFile($file, $header);
    if ($error) {
      // errore
      if ($this->fh) {
        fclose($this->fh);
        $this->fh = null;
      }
      $form->get('file')->addError(new FormError($this->trans->trans($error)));
      return null;
    }
    // lettura dati
    $imported = array();
    $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
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
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_data', ['%num%' => $count])));
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
      $fields['username'] = strtolower(trim($fields['username']));
      $fields['password'] = trim($fields['password']);
      $fields['email'] = trim($fields['email']);
      $fields['sede'] = strtoupper(trim($fields['sede']));
      $fields['tipo'] = strtoupper(trim($fields['tipo']));
      // controlla campi obbligatori
      if (empty($fields['cognome']) || empty($fields['nome']) || empty($fields['sesso'])) {
        // errore
        fclose($this->fh);
        $this->fh = null;
        $form->get('file')->addError(new FormError($this->trans->trans('exception.file_required', ['%num%' => $count])));
        return $imported;
      }
      if (empty($fields['username'])) {
        // crea username
        $empty_fields['username'] = true;
        $username = $fields['nome'].'.'.$fields['cognome'];
        $username = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $username));
        $fields['username'] = preg_replace('/[^a-z\.]+/', '', $username);
      }
      if (empty($fields['password'])) {
        // crea password
        $empty_fields['password'] = true;
        $fields['password'] = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
      }
      if (empty($fields['email'])) {
        // crea finta email
        $empty_fields['email'] = true;
        $fields['email'] = $fields['username'].'@noemail.local';
      }
      if (empty($fields['sede'])) {
        // valore null
        $empty_fields['sede'] = true;
        $fields['sede'] = null;
      }
      if (empty($fields['tipo'])) {
        // default: amministrativo
        $empty_fields['tipo'] = true;
        $fields['sede'] = 'A';
      }
      // controlla esistenza
      $ata = $this->em->getRepository('AppBundle:Ata')->findOneByUsername($fields['username']);
      if ($ata) {
        // utente esiste
        if ($form->get('onlynew')->getData()) {
          // nessuna modifica
          $imported['NONE'][$count] = $fields;
        } else {
          // modifica utente
          if (isset($empty_fields['username'])) {
            // errore: non modifica utente con username generata automaticamente
            fclose($this->fh);
            $this->fh = null;
            $form->addError(new FormError($this->trans->trans('exception.file_duplicated', ['%num%' => $count])));
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
        }
      } else {
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
   * @param UploadedFile $file File da importare
   * @param array $header Lista dei campi da importare
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function checkFile(UploadedFile $file, $header) {
    $this->fh = null;
    $this->header = array();
    if (!$file->isValid()) {
      // errore di upload
      return 'exception.file_upload';
    }
    if (strtolower($file->getClientOriginalExtension()) != 'csv') {
      // errore di formato
      return 'exception.file_format';
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
    $password = $this->encoder->encodePassword($docente, $docente->getPasswordNonCifrata());
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
      $password = $this->encoder->encodePassword($docente, $docente->getPasswordNonCifrata());
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
      ->setSupplenza($fields['supplenza'] == 1)
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
    // crea oggetto alunno (nessuna password per impedirne il login)
    $alunno = (new Alunno())
      ->setUsername($fields['username'])
      ->setPasswordNonCifrata('NOPASSWORD')
      ->setPassword('NOPASSWORD')
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
      ->setNumeriTelefono($fields['numeriTelefono'])
      ->setBes($fields['bes'])
      ->setFrequenzaEstero($fields['frequenzaEstero'])
      ->setReligione($fields['religione'])
      ->setCredito3($fields['credito3'])
      ->setCredito4($fields['credito4'])
      ->setClasse($fields['classe']);
    // valida dati alunno
    $errors = $this->validator->validate($alunno);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage().
        ' ['.$fields[$errors[0]->getPropertyPath()].']';
    }
    $this->em->persist($alunno);
    // crea oggetto genitore (con password per abilitarne il login)
    $genitore = (new Genitore())
      ->setUsername($fields['usernameGenitore'])
      ->setPasswordNonCifrata($fields['password'])
      ->setEmail($fields['usernameGenitore'].'@noemail.local')
      ->setAbilitato(true)
      ->setNome($fields['nome'])
      ->setCognome($fields['cognome'])
      ->setSesso($fields['sesso'])
      ->setAlunno($alunno);
    $password = $this->encoder->encodePassword($genitore, $genitore->getPasswordNonCifrata());
    $genitore->setPassword($password);
    // valida dati genitore
    $errors = $this->validator->validate($genitore);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().' genitore: '.$errors[0]->getMessage().
        ' ['.$fields[$errors[0]->getPropertyPath()].']';
    }
    $this->em->persist($genitore);
    // ok, memorizza su db
    $this->em->flush();
    // modifica dati per la visualizzazione
    $fields['dataNascita'] = $fields['dataNascita']->format('d/m/Y');
    $fields['numeriTelefono'] = implode(', ', $fields['numeriTelefono']);
    $fields['classe'] = ($fields['classe'] ? $fields['classe']->getAnno().' '.$fields['classe']->getSezione() : '');
    return null;
  }

  /**
   * Modifica un alunno esistente
   *
   * @param Alunno $alunno Alunno da modificare
   * @param array $fields Lista dei dati dell'alunno
   * @param array $empty_fields Lista dei dati nulli
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function modificaAlunno(Alunno $alunno, &$fields, $empty_fields) {
    // recupera genitori (anche più di uno)
    $genitori = $this->em->getRepository('AppBundle:Genitore')->findBy(['alunno' => $alunno]);
    // modifica dati obbligatori (escluso codice fiscale, usato come identificatore)
    $alunno
      ->setNome($fields['nome'])
      ->setCognome($fields['cognome'])
      ->setSesso($fields['sesso'])
      ->setDataNascita($fields['dataNascita'])
      ->setComuneNascita($fields['comuneNascita']);
    foreach ($genitori as $gen) {
      $gen
        ->setNome($fields['nome'])
        ->setCognome($fields['cognome'])
        ->setSesso($fields['sesso']);
    }
    // modifica dati opzionali solo se specificati
    if (!isset($empty_fields['email'])) {
      $alunno->setEmail($fields['email']);
    }
    if (!isset($empty_fields['citta'])) {
      $alunno->setCitta($fields['citta']);
    }
    if (!isset($empty_fields['indirizzo'])) {
      $alunno->setIndirizzo($fields['indirizzo']);
    }
    if (!isset($empty_fields['numeriTelefono'])) {
      $alunno->setNumeriTelefono($fields['numeriTelefono']);
      // modifica dati per la visualizzazione
      $fields['numeriTelefono'] = implode(', ', $fields['numeriTelefono']);
    }
    if (!isset($empty_fields['bes'])) {
      $alunno->setBes($fields['bes']);
    }
    if (!isset($empty_fields['frequenzaEstero'])) {
      $alunno->setFrequenzaEstero($fields['frequenzaEstero']);
    }
    if (!isset($empty_fields['religione'])) {
      $alunno->setReligione($fields['religione']);
    }
    if (!isset($empty_fields['credito3'])) {
      $alunno->setCredito3($fields['credito3']);
    }
    if (!isset($empty_fields['credito4'])) {
      $alunno->setCredito4($fields['credito4']);
    }
    if (!isset($empty_fields['classe'])) {
      $alunno->setClasse($fields['classe']);
      // modifica dati per la visualizzazione
      $fields['classe'] = ($fields['classe'] ? $fields['classe']->getAnno().' '.$fields['classe']->getSezione() : '');
    }
    // valida dati
    $errors = $this->validator->validate($alunno);
    if (count($errors) > 0) {
      // errore (restituisce solo il primo)
      return $errors[0]->getPropertyPath().': '.$errors[0]->getMessage().
        ' ['.$fields[$errors[0]->getPropertyPath()].']';
    }
    foreach ($genitori as $gen) {
      $errors = $this->validator->validate($gen);
      if (count($errors) > 0) {
        // errore (restituisce solo il primo)
        return $errors[0]->getPropertyPath().' genitore: '.$errors[0]->getMessage().
          ' ['.$fields[$errors[0]->getPropertyPath()].']';
      }
    }
    // ok, memorizza su db
    $this->em->flush();
    // modifica dati per la visualizzazione
    $fields['dataNascita'] = $fields['dataNascita']->format('d/m/Y');
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
    $sede = $this->em->getRepository('AppBundle:Sede')->findOneByCitta($fields['sede']);
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
      ->setSede($sede)
      ->setTipo($fields['tipo']);
    $password = $this->encoder->encodePassword($ata, $ata->getPasswordNonCifrata());
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
    if (!isset($empty_fields['email'])) {
      $ata->setEmail($fields['email']);
    } else {
      unset($fields['email']);
    }
    if (!isset($empty_fields['password'])) {
      $ata->setPasswordNonCifrata($fields['password']);
      $password = $this->encoder->encodePassword($ata, $ata->getPasswordNonCifrata());
      $ata->setPassword($password);
    } else {
      unset($fields['password']);
    }
    if (!isset($empty_fields['tipo'])) {
      $ata->setTipo($fields['tipo']);
    } else {
      unset($fields['tipo']);
    }
    // legge sede
    $sede = $this->em->getRepository('AppBundle:Sede')->findOneByCitta($fields['sede']);
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

}

