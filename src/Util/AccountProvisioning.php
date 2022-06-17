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


namespace App\Util;

use Google_Client as GClient;
use Google_Service_Directory as GDirectory;
use Google_Service_Directory_User as GUser;
use Google_Service_Directory_Member as GMember;
use Google_Service_Directory_UserPhoto as GPhoto;
use Google_Service_Classroom as GClassroom;
use Google_Service_Classroom_Student as GStudent;
use Google_Service_Classroom_Teacher as GTeacher;
use Google_Service_Classroom_Course as GCourse;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Utente;
use App\Entity\Alunno;
use App\Entity\Staff;
use App\Entity\Cattedra;
use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\Materia;


/**
 * AccountProvisioning - classe di utilità per la gestione del provisioning su servizi esterni
 */
class AccountProvisioning {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;

  /**
   * @var string $dirProgetto Percorso per i file dell'applicazione
   */
  private $dirProgetto;

  /**
   * @var array $serviceGsuite Lista di servizi per la gestione della GSuite
   */
  private $serviceGsuite;

  /**
   * @var array $serviceMoodle Dati e client per gestire i servizi Moodle
   */
  private $serviceMoodle;

  /**
   * @var array $log Lista azioni eseguite senza errori
   */
  private $log;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param string $dirProgetto Percorso per i file dell'applicazione
   */
  public function __construct(EntityManagerInterface $em, SessionInterface $session, $dirProgetto) {
    $this->em = $em;
    $this->session = $session;
    $this->dirProgetto = $dirProgetto;
    $this->serviceGsuite = null;
    $this->serviceMoodle = null;
    $this->log = array();
  }

  /**
   * Svuota il log delle azioni
   *
   */
  public function svuotaLog() {
    $this->log = array();
  }

  /**
   * Restituisce il log delle azioni
   *
   * @return array Log delle azioni eseguite correttamente
   */
  public function log() {
    return $this->log;
  }

  /**
   * Inizializza i servizi di gestione dei sistemi esterni
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function inizializza() {
    if (($errore = $this->inizializzaGsuite())) {
      // errore
      return $errore;
    }
    $this->log[] = 'inizializzaGsuite';
    if (($errore = $this->inizializzaMoodle())) {
      // errore
      return $errore;
    }
    $this->log[] = 'inizializzaMoodle';
    // tutto ok
    return null;
  }

  /**
   * Crea un nuovo utente sui sistemi esterni
   *
   * @param Utente $utente Nuovo utente da creare
   * @param string $password Password in chiaro dell'utente
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function creaUtente(Utente $utente, $password) {
    $tipo = ($utente instanceOf Docente ? 'D' : 'A');
    // GSuite: crea utente
    if (($errore = $this->creaUtenteGsuite($utente->getNome(), $utente->getCognome(), $utente->getSesso(),
         $utente->getEmail(), $password, $tipo))) {
      // errore
      return $errore;
    }
    $this->log[] = 'creaUtenteGsuite: '.$utente->getNome().', '.$utente->getCognome().', '.$utente->getSesso().', '.
      $utente->getEmail().', '.$password.', '.$tipo;
    // MOODLE: crea utente
    if (($errore = $this->creaUtenteMoodle($utente->getNome(), $utente->getCognome(), $utente->getUsername(),
         $utente->getEmail(), $password, $tipo))) {
      // errore
      return $errore;
    }
    $this->log[] = 'creaUtenteMoodle: '.$utente->getNome().', '.$utente->getCognome().', '.$utente->getUsername().', '.
      $utente->getEmail().', '.$password.', '.$tipo;
    // tutto ok
    return null;
  }

  /**
   * Modifica i dati di un utente sui sistemi esterni
   *
   * @param Utente $utente Utente da modificare
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function modificaUtente(Utente $utente) {
    // GSuite: modifica utente
    if (($errore = $this->modificaUtenteGsuite($utente->getEmail(), $utente->getNome(), $utente->getCognome(),
         $utente->getSesso()))) {
      // errore
      return $errore;
    }
    $this->log[] = 'modificaUtenteGsuite: '.$utente->getEmail().', '.$utente->getNome().', '.$utente->getCognome().
      ', '.$utente->getSesso();
    // MOODLE: modifica utente
    try {
      $idutente = $this->idUtenteMoodle($utente->getUsername());
      $this->log[] = 'idUtenteMoodle: '.$utente->getUsername().' -> '.$idutente;
    } catch (\Exception $e) {
      // errore
      return $e->getMessage();
    }
    if (($errore = $this->modificaUtenteMoodle($idutente, $utente->getNome(), $utente->getCognome()))) {
      // errore
      return $errore;
    }
    $this->log[] = 'modificaUtenteMoodle: '.$idutente.', '.$utente->getNome().', '.$utente->getCognome();
    // tutto ok
    return null;
  }

  /**
   * Modifica la password di un utente sui sistemi esterni
   *
   * @param Utente $utente Utente da modificare
   * @param string $password Password in chiaro dell'utente
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function passwordUtente(Utente $utente, $password) {
    // cambia password solo sull'Identity provider (GSuite)
    if (($errore = $this->passwordUtenteGsuite($utente->getEmail(), $password))) {
      // errore
      return $errore;
    }
    $this->log[] = 'passwordUtenteGsuite: '.$utente->getEmail().', '.$password;
    // tutto ok
    return null;
  }

  /**
   * Disconnette l'utente dal sistema esterno che fa da identity provider.
   * ATTENZIONE: disconnette da tutti i dispositivi dell'utente
   *
   * @param string $utente Email dell'utente da disconnettere
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function disconnetteUtente($utente) {
    // disconnette dall'identity provider (GSuite)
    $errore = null;
    try {
      $ris = $this->serviceGsuite['directory']->users->signOut($utente);
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[disconnetteUtente] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
      return $errore;
    }
    $this->log[] = 'disconnetteUtente: '.$utente;
    // tutto ok
    return null;
  }

  /**
   * Sospende o riattiva un utente sui sistemi esterni
   *
   * @param Utente $utente Utente da modificare
   * @param boolean $sospeso Vero per sospendere l'utente, falso per riattivarlo
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function sospendeUtente(Utente $utente, $sospeso) {
    $tipo = ($utente instanceOf Docente ? 'D' : 'A');
    // GSuite: sospende utente
    if (($errore = $this->sospendeUtenteGsuite($utente->getEmail(), $tipo, $sospeso))) {
      // errore
      return $errore;
    }
    $this->log[] = 'sospendeUtenteGsuite: '.$utente->getEmail().', '.$sospeso;
    // MOODLE: sospende utente
    if (($errore = $this->sospendeUtenteMoodle($utente->getUsername(), $tipo, $sospeso))) {
      // errore
      return $errore;
    }
    $this->log[] = 'sospendeUtenteMoodle: '.$utente->getUsername().', '.$tipo.', '.$sospeso;
    // tutto ok
    return null;
  }

  /**
   * Aggiunge un alunno alla classe indicata e ai relativi corsi
   * NB: escluso Sostegno e Ed.Civica
   *
   * @param Alunno $alunno Alunno da aggiungere
   * @param Classe $classe Classe di destinazione dell'alunnno
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function aggiungeAlunnoClasse(Alunno $alunno, Classe $classe) {
    $dominio = $this->session->get('/CONFIG/SISTEMA/dominio_id_provider');
    $nomeclasse = $classe->getAnno().$classe->getSezione();
    $anno = substr($this->session->get('/CONFIG/SCUOLA/anno_inizio'), 0, 4);
    // GSuite: aggiunge a gruppo classe
    $gruppo = 'studenti'.strtolower($nomeclasse).'@'.$dominio;
    if (($errore = $this->aggiungeUtenteGruppoGsuite($alunno->getEmail(), $gruppo))) {
      // errore
      return $errore;
    }
    $this->log[] = 'aggiungeUtenteGruppoGsuite: '.$alunno->getEmail().', '.$gruppo;
    // GSuite: aggiunge ai corsi della classe
    $cattedre = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('DISTINCT m.nomeBreve')
      ->join('c.docente', 'd')
      ->join('c.materia', 'm')
      ->where('c.attiva=:attiva AND c.classe=:classe AND d.abilitato=:abilitato AND m.tipo NOT IN (:materie)')
      ->setParameters(['attiva' => 1, 'classe' => $classe, 'abilitato' => 1, 'materie' => ['S', 'E']])
      ->getQuery()
      ->getArrayResult();
    foreach ($cattedre as $cat) {
      $corso = strtoupper($nomeclasse.'-'.str_replace([' ','.',',','(',')'], '', $cat['nomeBreve']).'-'.$anno);
      if (($errore = $this->aggiungeAlunnoCorsoGsuite($alunno->getEmail(), $corso))) {
        // errore
        return $errore;
      }
      $this->log[] = 'aggiungeAlunnoCorsoGsuite: '.$alunno->getEmail().', '.$corso;
    }
    // MOODLE: aggiunge a gruppo classe e ai relativi corsi
    $gruppo = strtoupper($nomeclasse);
    if (($errore = $this->aggiungeUtenteGruppoMoodle($alunno->getUsername(), $gruppo))) {
      // errore
      return $errore;
    }
    $this->log[] = 'aggiungeUtenteGruppoMoodle: '.$alunno->getUsername().', '.$gruppo;
    // tutto ok
    return null;
  }

  /**
   * Rimuove un alunno dalla classe indicata e dai relativi corsi
   *
   * @param Alunno $alunno Alunno da rimuovere
   * @param Classe $classe Classe da cui rimuovere dell'alunnno
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function rimuoveAlunnoClasse(Alunno $alunno, Classe $classe) {
    $dominio = $this->session->get('/CONFIG/SISTEMA/dominio_id_provider');
    $nomeclasse = $classe->getAnno().$classe->getSezione();
    $anno = substr($this->session->get('/CONFIG/SCUOLA/anno_inizio'), 0, 4);
    // GSuite: rimuove da gruppo classe
    $gruppo = 'studenti'.strtolower($nomeclasse).'@'.$dominio;
    if (($errore = $this->rimuoveUtenteGruppoGsuite($alunno->getEmail(), $gruppo))) {
      // errore
      return $errore;
    }
    $this->log[] = 'rimuoveUtenteGruppoGsuite: '.$alunno->getEmail().', '.$gruppo;
    // GSuite: rimuove dai corsi della classe
    $cattedre = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('DISTINCT m.nomeBreve')
      ->join('c.docente', 'd')
      ->join('c.materia', 'm')
      ->where('c.attiva=:attiva AND c.classe=:classe AND d.abilitato=:abilitato AND m.tipo!=:sostegno AND m.tipo!=:civica')
      ->setParameters(['attiva' => 1, 'classe' => $classe, 'abilitato' => 1, 'sostegno' => 'S', 'civica' => 'E'])
      ->getQuery()
      ->getArrayResult();
    foreach ($cattedre as $cat) {
      $corso = strtoupper($nomeclasse.'-'.str_replace([' ','.',',','(',')'], '', $cat['nomeBreve']).'-'.$anno);
      if (($errore = $this->rimuoveAlunnoCorsoGsuite($alunno->getEmail(), $corso))) {
        // errore
        return $errore;
      }
      $this->log[] = 'rimuoveAlunnoCorsoGsuite: '.$alunno->getEmail().', '.$corso;
    }
    // MOODLE: rimuove da gruppo classe e dai relativi corsi
    $gruppo = strtoupper($nomeclasse);
    try {
      $idutente = $this->idUtenteMoodle($alunno->getUsername());
      $this->log[] = 'idUtenteMoodle: '.$alunno->getUsername().' -> '.$idutente;
      $idgruppo = $this->idGruppoMoodle($gruppo);
      $this->log[] = 'idGruppoMoodle: '.$gruppo.' -> '.$idgruppo;
    } catch (\Exception $e) {
      // errore
      return $e->getMessage();
    }
    if (($errore = $this->rimuoveUtenteGruppoMoodle($idutente, $idgruppo))) {
      // errore
      return $errore;
    }
    $this->log[] = 'rimuoveUtenteGruppoMoodle: '.$idutente.', '.$idgruppo;
    // tutto ok
    return null;
  }

  /**
   * Modifica la classe di un alunno e lo associa ai relativi corsi
   *
   * @param Alunno $alunno Alunno di cui modificare la classe
   * @param Classe $origine Classe di provenienza dell'alunnno
   * @param Classe $destinazione Classe di destinazione dell'alunnno
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function modificaAlunnoClasse(Alunno $alunno, Classe $origine, Classe $destinazione) {
    // rimuove da vecchia classe
    if (($errore = $this->rimuoveAlunnoClasse($alunno, $origine))) {
      // errore
      return $errore;
    }
    $this->log[] = 'rimuoveAlunnoClasse: ['.$alunno.'], ['.$origine.']';
    // aggiunge a nuova classe
    if (($errore = $this->aggiungeAlunnoClasse($alunno, $destinazione))) {
      // errore
      return $errore;
    }
    $this->log[] = 'aggiungeAlunnoClasse: ['.$alunno.'], ['.$destinazione.']';
    // tutto ok
    return null;
  }

  /**
   * Crea un corso sui sistemi esterni relativo alla cattedra indicata.
   * Aggiunge docente a CdC.
   *
   * @param Cattedra $cattedra Cattedra di cui creare il corso
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function aggiungeCattedra(Cattedra $cattedra) {
    $docente = $cattedra->getDocente()->getEmail();
    $nomeclasse = $cattedra->getClasse()->getAnno().$cattedra->getClasse()->getSezione();
    $anno = substr($this->session->get('/CONFIG/SCUOLA/anno_inizio'), 0, 4);
    $coordinatore = ($cattedra->getClasse()->getCoordinatore() == $cattedra->getDocente()) ||
      ($cattedra->getClasse()->getSegretario() == $cattedra->getDocente());
    $docente_username = $cattedra->getDocente()->getUsername();
    $sede = $cattedra->getClasse()->getSede()->getCitta();
    $indirizzo = $cattedra->getClasse()->getCorso()->getNomeBreve();
    // gestione cattedra di sostegno
    if ($cattedra->getMateria()->getTipo() == 'S') {
      // cattedra di SOSTEGNO: tutte le materie
      $cattedre = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
        ->join('c.classe', 'cl')
        ->join('c.docente', 'd')
        ->join('c.materia', 'm')
        ->where('c.attiva=:attiva AND cl.id=:classe AND d.abilitato=:abilitato AND m.tipo NOT IN (:materie)')
        ->setParameters(['attiva' => 1, 'classe' => $cattedra->getClasse(), 'abilitato' => 1, 'materie' => ['S', 'E']])
        ->getQuery()
        ->getResult();
    } elseif ($cattedra->getMateria()->getTipo() != 'E') {
      // cattedra curricolare (escluso Ed.Civica)
      $cattedre = [$cattedra];
    } else {
      // niente da fare
      $cattedre = [];
    }
    // crea corsi
    foreach ($cattedre as $cat) {
      $materia = $cat->getMateria()->getNomeBreve();
      $corso = strtoupper($nomeclasse.'-'.str_replace([' ','.',',','(',')'], '', $materia).'-'.$anno);
      // GSuite: crea corso
      if (($errore = $this->creaCorsoGsuite($docente, $nomeclasse, $materia, $anno) )) {
        // errore
        return $errore;
      }
      $this->log[] = 'creaCorsoGsuite: '.$docente.', '.$nomeclasse.', '.$materia.', '.$anno;
      // GSuite: controlla se ci sono studenti nel $corso
      try {
        // lista studenti
        $students = $this->serviceGsuite['classroom']->courses_students->listCoursesStudents('d:'.$corso);
      } catch (\Exception $e) {
        // errore
        $msg = json_decode($e->getMessage(), true);
        $errore = '[aggiungeCattedra] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
        return $errore;
      }
      if (count($students['students']) == 0) {
        // GSuite: aggiunge studenti al corso
        $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
          ->select('a.email')
          ->where('a.abilitato=:abilitato AND a.classe=:classe')
          ->setParameters(['abilitato' => 1, 'classe' => $cattedra->getClasse()])
          ->getQuery()
          ->getArrayResult();
        foreach ($alunni as $alu) {
          if (($errore = $this->aggiungeAlunnoCorsoGsuite($alu['email'], $corso))) {
            // errore
            return $errore;
          }
          $this->log[] = 'aggiungeAlunnoCorsoGsuite: '.$alu['email'].', '.$corso;
        }
      }
      // MOODLE: crea corso
      if (($errore = $this->creaCorsoMoodle($docente_username, $nomeclasse, $sede, $indirizzo, $materia, $anno))) {
        // errore
        return $errore;
      }
      $this->log[] = 'creaCorsoMoodle: '.$docente_username.', '.$nomeclasse.', '.$sede.', '.$indirizzo.', '.$materia.', '.$anno;
      // MOODLE: controlla se ci sono gruppi classe nel $corso
      try {
        $functionname = 'core_course_get_courses_by_field';
        $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
          '&moodlewsrestformat=json';
        $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['field' => 'shortname',
          'value' => $corso]]);
        $msg = json_decode($ris->getBody());
        if (isset($msg->exception)) {
          // errore
          $errore = '[aggiungeCattedra] '.$msg->message;
          return $errore;
        }
      } catch (\Exception $e) {
        // errore
        $errore = '[aggiungeCattedra] '.$e->getMessage();
        return $errore;
      }
      if (!in_array('cohort', $msg->courses[0]->enrollmentmethods)) {
        // MOODLE: aggiunge gruppo classe
        $idcorso = $msg->courses[0]->id;
        if (($errore = $this->aggiungeClasseCorsoMoodle($nomeclasse, $idcorso))) {
          // errore
          return $errore;
        }
        $this->log[] = 'aggiungeClasseCorsoMoodle: '.$nomeclasse.', '.$idcorso;
      }
    }
    // GSuite: aggiunge docente a CdC
    if (($errore = $this->aggiungeDocenteCdcGsuite($docente, $nomeclasse, $anno, $coordinatore))) {
      // errore
      return $errore;
    }
    $this->log[] = 'aggiungeDocenteCdcGsuite: '.$docente.', '.$nomeclasse.', '.$anno.', '.$coordinatore;
    // tutto ok
    return null;
  }

  /**
   * Disabilita un corso sui sistemi esterni relativo alla cattedra indicata.
   * Rimuove docente da CdC.
   *
   * @param Docente $docente Dodente del corso da rimuovere
   * @param Classe $classe Classe del corso da rimuovere
   * @param Materia $materia Materia del corso da rimuovere
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function rimuoveCattedra(Docente $docente, Classe $classe, Materia $materia) {
    $docente_email = $docente->getEmail();
    $docente_username = $docente->getUsername();
    $nomeclasse = $classe->getAnno().$classe->getSezione();
    $anno = substr($this->session->get('/CONFIG/SCUOLA/anno_inizio'), 0, 4);
    // controlla se ha altre materie nella classe
    $altre = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('COUNT(c.id)')
      ->join('c.classe', 'cl')
      ->join('c.docente', 'd')
      ->join('c.materia', 'm')
      ->where('c.attiva=:attiva AND cl.id=:classe AND d.abilitato=:abilitato AND d.id=:docente AND m.id!=:materia AND m.tipo!=:civica')
      ->setParameters(['attiva' => 1, 'classe' => $classe, 'abilitato' => 1, 'docente' => $docente,
        'materia' => $materia, 'civica' => 'E'])
      ->getQuery()
      ->getSingleScalarResult();
    // gestione cattedra di sostegno
    if ($materia->getTipo() == 'S') {
      // cattedra di SOSTEGNO: tutte le materie (anche cattedre/docenti disabilitati)
      $cattedre = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
        ->select('DISTINCT m.nomeBreve')
        ->join('c.classe', 'cl')
        ->join('c.docente', 'd')
        ->join('c.materia', 'm')
        ->where('cl.id=:classe AND m.tipo NOT IN (:materie)')
        ->setParameters(['classe' => $classe, 'materie' => ['S', 'E']])
        ->getQuery()
        ->getArrayResult();
    } elseif ($materia->getTipo() == 'E') {
      // ed.civica: non fa nulla
      $cattedre = [];
    } else {
      // cattedra curricolare
      $cattedre = array(['nomeBreve' => $materia->getNomeBreve()]);
    }
    // rimuove docente da corsi
    foreach ($cattedre as $cat) {
      $materia = $cat['nomeBreve'];
      $corso = strtoupper($nomeclasse.'-'.str_replace([' ','.',',','(',')'], '', $materia).'-'.$anno);
      // GSuite: rimuove docente da corso
      if (($errore = $this->rimuoveDocenteCorsoGsuite($docente_email, $corso, ($altre == 0)))) {
        // errore
        return $errore;
      }
      $this->log[] = 'rimuoveDocenteCorsoGsuite: '.$docente_email.', '.$corso.', '.($altre == 0);
      // MOODLE: rimuove docente da corso
      try {
        $idutente = $this->idUtenteMoodle($docente_username);
        $this->log[] = 'idUtenteMoodle: '.$docente_username.' -> '.$idutente;
        $idcorso = $this->idCorsoMoodle($corso);
        $this->log[] = 'idCorsoMoodle: '.$corso.' -> '.$idcorso;
      } catch (\Exception $e) {
        // errore
        return $e->getMessage();
      }
      if (($errore = $this->rimuoveDocenteCorsoMoodle($idutente, $idcorso))) {
        // errore
        return $errore;
      }
      $this->log[] = 'rimuoveDocenteCorsoMoodle: '.$idutente.', '.$idcorso;
    }
    // rimuove docente da CdC
    if ($altre == 0) {
      // GSuite: rimuove docente da CdC
      if (($errore = $this->rimuoveDocenteCdcGsuite($docente_email, $nomeclasse, $anno))) {
        // errore
        return $errore;
      }
      $this->log[] = 'rimuoveDocenteCdcGsuite: '.$docente_email.', '.$nomeclasse.', '.$anno;
    }
    // tutto ok
    return null;
  }

  /**
   * Modifica un corso sui sistemi esterni relativo alla cattedra indicata
   *
   * @param Cattedra $cattedra Cattedra di cui creare il nuovo corso
   * @param Docente $docente Dodente del corso da rimuovere
   * @param Classe $classe Classe del corso da rimuovere
   * @param Materia $materia Materia del corso da rimuovere
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function modificaCattedra(Cattedra $cattedra, Docente $docente, Classe $classe, Materia $materia) {
    // rimuove cattedra precedente
    if (($errore = $this->rimuoveCattedra($docente, $classe, $materia))) {
      // errore
      return $errore;
    }
    $this->log[] = 'rimuoveCattedra: ['.$docente.'], ['.$classe.'], ['.$materia.']';
    // aggiunge nuova cattedra
    if (($errore = $this->aggiungeCattedra($cattedra))) {
      // errore
      return $errore;
    }
    $this->log[] = 'aggiungeCattedra: ['.$cattedra.']';
    // tutto ok
    return null;
  }

  /**
   * Aggiunge coordinatore/segretario a CdC.
   *
   * @param Docente $docente Docente da aggiungere
   * @param Classe $classe Classe del CdC
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function aggiungeCoordinatore(Docente $docente, Classe $classe) {
    $docente_email = $docente->getEmail();
    $nomeclasse = $classe->getAnno().$classe->getSezione();
    $anno = substr($this->session->get('/CONFIG/SCUOLA/anno_inizio'), 0, 4);
    // GSuite: aggiunge docente a CdC come coordinatore/segretario
    if (($errore = $this->aggiungeDocenteCdcGsuite($docente_email, $nomeclasse, $anno, true))) {
      // errore
      return $errore;
    }
    $this->log[] = 'aggiungeDocenteCdcGsuite: '.$docente_email.', '.$nomeclasse.', '.$anno.', '.(true);
    // tutto ok
    return null;
  }

  /**
   * Rimuove coordinatore/segretario da CdC (non rimuove docente da CdC).
   *
   * @param Docente $docente Docente coordinatore da rimuovere
   * @param Classe $classe Classe del CdC
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function rimuoveCoordinatore(Docente $docente, Classe $classe) {
    $docente_email = $docente->getEmail();
    $nomeclasse = $classe->getAnno().$classe->getSezione();
    $anno = substr($this->session->get('/CONFIG/SCUOLA/anno_inizio'), 0, 4);
    // GSuite: aggiunge docente a CdC
    if (($errore = $this->aggiungeDocenteCdcGsuite($docente_email, $nomeclasse, $anno, false))) {
      // errore
      return $errore;
    }
    $this->log[] = 'aggiungeDocenteCdcGsuite: '.$docente_email.', '.$nomeclasse.', '.$anno.', '.(false);
    // tutto ok
    return null;
  }

  /**
   * Sposta coordinatore/segretario da un CdC ad un altro.
   *
   * @param Docente $docente Docente da inserire nel CdC
   * @param Classe $classe Classe del CdC in cui inserire docente
   * @param Docente $docente_prec Docente precedente del CdC
   * @param Classe $classe_prec Classe precedente del CdC
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  public function modificaCoordinatore(Docente $docente, Classe $classe, Docente $docente_prec,
                                     Classe $classe_prec) {
    // rimuove da CdC precedente
    if (($errore = $this->rimuoveCoordinatore($docente_prec, $classe_prec))) {
      // errore
      return $errore;
    }
    $this->log[] = 'rimuoveCoordinatore: ['.$docente_prec.'], ['.$classe_prec.']';
    // aggiunge a nuovo CdC
    if (($errore = $this->aggiungeCoordinatore($docente, $classe))) {
      // errore
      return $errore;
    }
    $this->log[] = 'aggiungeCoordinatore: ['.$docente.'], ['.$classe.']';
    // tutto ok
    return null;
  }


  //==================== METODI PRIVATI PER LA GESTIONE GSUITE ====================

  /**
   * Inizializza i servizi di gestione della GSuite
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function inizializzaGsuite() {
    // init
    $errore = null;
    try {
      $client = new GClient();
      $client->setAuthConfig($this->dirProgetto.'/config/secrets/registro-elettronico-utenti-gsuite.json');
      $client->setSubject($this->session->get('/CONFIG/ISTITUTO/email_amministratore'));
      $client->setApplicationName("Registro Elettronico Utenti");
      $client->addScope('https://www.googleapis.com/auth/admin.directory.user');
      $client->addScope('https://www.googleapis.com/auth/admin.directory.user.security');
      $client->addScope('https://www.googleapis.com/auth/admin.directory.group');
      $client->addScope('https://www.googleapis.com/auth/classroom.rosters');
      $client->addScope('https://www.googleapis.com/auth/classroom.courses');
      $this->serviceGsuite = array();
      $this->serviceGsuite['directory'] = new GDirectory($client);
      $this->serviceGsuite['classroom'] = new GClassroom($client);
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[inizializzaGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Aggiunge un utente a un gruppo della GSuite
   *
   * @param string $utente Email dell'utente (già esistente nel sistema)
   * @param string $gruppo Email del gruppo (già esistente nel sistema)
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function aggiungeUtenteGruppoGsuite($utente, $gruppo) {
    // init
    $errore = null;
    try {
      // controlla se utente già appartiene a gruppo
      $ris = $this->serviceGsuite['directory']->members->hasMember($gruppo, $utente);
      if (!$ris->isMember) {
        // aggiunge utente
        $member = new GMember([
          'email' => $utente,
          'role' => 'MEMBER',
          'type' => 'USER']);
        $ris = $this->serviceGsuite['directory']->members->insert($gruppo, $member);
      }
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[aggiungeUtenteGruppoGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Rimuove un utente da un gruppo della GSuite
   *
   * @param string $utente Email dell'utente (già esistente nel sistema)
   * @param string $gruppo Email del gruppo (già esistente nel sistema)
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function rimuoveUtenteGruppoGsuite($utente, $gruppo) {
    // init
    $errore = null;
    try {
      // controlla se utente già appartiene a gruppo
      $ris = $this->serviceGsuite['directory']->members->hasMember($gruppo, $utente);
      if ($ris->isMember) {
        // rimuove utente
        $ris = $this->serviceGsuite['directory']->members->delete($gruppo, $utente);
      }
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[rimuoveUtenteGruppoGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Crea un utente della GSuite. Aggiunge utente a gruppo (studenti/docenti).
   * I docenti sono aggiunti anche al corso del Collegio dei Docenti.
   *
   * @param string $nome Nome dell'utente
   * @param string $cognome Cognome dell'utente
   * @param string $sesso Sesso dell'utente [M=maschio, F=femmina]
   * @param string $email Email dell'utente (appartente al dominio GSuite)
   * @param string $password Password in chiaro dell'utente
   * @param string $tipo Tipo di utente [D=docente/staff/preside, A=alunno]
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function creaUtenteGsuite($nome, $cognome, $sesso, $email, $password, $tipo) {
    // init
    $errore = null;
    $dominio = $this->session->get('/CONFIG/SISTEMA/dominio_id_provider');
    $anno = substr($this->session->get('/CONFIG/SCUOLA/anno_inizio'), 0, 4);
    // controlla esistenza
    try {
      $esistente = $this->serviceGsuite['directory']->users->get($email);
    } catch (\Exception $e) {
      // utente non esiste
      $esistente = null;
    }
    if ($esistente) {
      // utente esiste: lo riattiva (si presume sia sospeso)
      return $this->sospendeUtenteGsuite($email, $tipo, false);
    }
    // crea nuovo utente
    try {
      // crea utente
      if ($tipo == 'D') {
        // docenti/staff/preside
        $uo = '/Docenti';
        $gravatar = ['identicon', 'retro'];
        $gravatar_type = $gravatar[time() % 2];
        $gruppo = 'docenti@'.$dominio;
      } else {
        // alunni
        $uo = '/Studenti';
        $gravatar = ['monsterid', 'wavatar', 'robohash'];
        $gravatar_type = $gravatar[time() % 3];
        $gruppo = 'studenti@'.$dominio;
      }
      $user = new GUser([
        'name' => ['givenName' => $nome, 'familyName' => $cognome],
        'gender' => ['type' => ($sesso == 'M' ? 'male' : 'female')],
        'password' => sha1($password),
        'hashFunction' => 'SHA-1',
        'primaryEmail' => $email,
        'orgUnitPath' => $uo]);
      $ris = $this->serviceGsuite['directory']->users->insert($user);
      // pausa per essere sicuri che creazione utente sia completa
      sleep(1);
      // aggiunge avatar
      $hash = sha1($email);
      $url = 'https://www.gravatar.com/avatar/'.$hash.'?s=96&d='.$gravatar_type;
      $image = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(file_get_contents($url)));
      $photo = new GPhoto([
        'photoData' => $image,
        'mimeType' => 'JPEG',
        'height' => 96,
        'width' => 96]);
      $ris = $this->serviceGsuite['directory']->users_photos->update($email, $photo);
      // pausa per essere sicuri che creazione utente sia completa
      sleep(3);
      // aggiunge a gruppo
      $errore = $this->aggiungeUtenteGruppoGsuite($email, $gruppo);
      if (!$errore && $tipo == 'D') {
        // aggiunge docente a corso COLLEGIO DEI DOCENTI
        $errore = $this->aggiungeAlunnoCorsoGsuite($email, 'COLLEGIO-DEI-DOCENTI-'.$anno);
      }
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[creaUtenteGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Modifica i dati di un utente della GSuite
   *
   * @param string $email Email dell'utente (appartente al dominio GSuite)
   * @param string $nome Nome dell'utente
   * @param string $cognome Cognome dell'utente
   * @param string $sesso Sesso dell'utente [M=maschio, F=femmina]
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function modificaUtenteGsuite($email, $nome, $cognome, $sesso) {
    // init
    $errore = null;
    try {
      // modifica utente
      $user = new GUser([
        'name' => ['givenName' => $nome, 'familyName' => $cognome],
        'gender' => ['type' => ($sesso == 'M' ? 'male' : 'female')]]);
      $ris = $this->serviceGsuite['directory']->users->update($email, $user);
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[modificaUtenteGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Modifica la password di un utente della GSuite
   *
   * @param string $email Email dell'utente (appartente al dominio GSuite)
   * @param string $password Password in chiaro dell'utente
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function passwordUtenteGsuite($email, $password) {
    // init
    $errore = null;
    try {
      // modifica utente
      $user = new GUser([
        'password' => sha1($password),
        'hashFunction' => 'SHA-1']);
      $ris = $this->serviceGsuite['directory']->users->update($email, $user);
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[passwordUtenteGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Sospende/riattiva un utente della GSuite. Toglie/aggiunge utente da gruppo (studenti/docenti).
   * I docenti sono tolti/aggiunti anche dal corso del Collegio dei Docenti.
   *
   * @param string $email Email dell'utente (appartente al dominio GSuite)
   * @param string $tipo Tipo di utente [D=docente/staff/preside, A=alunno]
   * @param boolean $sospeso Vero per sospendere, falso per riattivare
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function sospendeUtenteGsuite($email, $tipo, $sospeso) {
    // init
    $errore = null;
    $dominio = $this->session->get('/CONFIG/SISTEMA/dominio_id_provider');
    $anno = substr($this->session->get('/CONFIG/SCUOLA/anno_inizio'), 0, 4);
    try {
      if ($tipo == 'D') {
        // docenti/staff/preside
        $uo = $sospeso ? '/Utenti sospesi/Docenti' : '/Docenti';
        $gruppo = 'docenti@'.$dominio;
      } else {
        // alunni
        $uo = $sospeso ? '/Utenti sospesi/Studenti' : '/Studenti';
        $gruppo = 'studenti@'.$dominio;
      }
      // modifica utente
      $user = new GUser([
        'orgUnitPath' => $uo,
        'suspended' => $sospeso]);
      $ris = $this->serviceGsuite['directory']->users->update($email, $user);
      if ($sospeso) {
        // rimuove da gruppo
        $errore = $this->rimuoveUtenteGruppoGsuite($email, $gruppo);
        if (!$errore && $tipo == 'D') {
          // toglie docente da corso COLLEGIO DEI DOCENTI
          $errore = $this->rimuoveAlunnoCorsoGsuite($email, 'COLLEGIO-DEI-DOCENTI-'.$anno);
        }
      } else {
        // aggiunge a gruppo
        $errore = $this->aggiungeUtenteGruppoGsuite($email, $gruppo);
        if (!$errore && $tipo == 'D') {
          // aggiunge docente a corso COLLEGIO DEI DOCENTI
          $errore = $this->aggiungeAlunnoCorsoGsuite($email, 'COLLEGIO-DEI-DOCENTI-'.$anno);
        }
      }
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[passwordUtenteGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Crea nuovo corso o aggiunge docente a corso esistente su GSuite. Aggiunge docente a gruppo classe.
   *
   * @param string $docente Email del docente del corso (utente già esistente)
   * @param string $classe Nome della classe
   * @param string $materia Nome breve della materia
   * @param string $anno Anno scolastico
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function creaCorsoGsuite($docente, $classe, $materia, $anno) {
    // init
    $errore = null;
    $dominio = $this->session->get('/CONFIG/SISTEMA/dominio_id_provider');
    $nomecorso = "$classe - $materia - $anno";
    $corso = strtoupper($classe.'-'.str_replace([' ','.',',','(',')'], '', $materia).'-'.$anno);
    try {
      // controlla esistenza corso
      try {
        $corsoObj = $this->serviceGsuite['classroom']->courses->get('d:'.$corso);
      } catch (\Exception $e) {
        $corsoObj = null;
      }
      if (!$corsoObj) {
        // crea corso
        $course = new GCourse([
          'id' => 'd:'.$corso,
          'name' => $nomecorso,
          'ownerId' => $docente,
          'courseState' => 'ACTIVE']);
        $corsoObj = $this->serviceGsuite['classroom']->courses->create($course);
      } else {
        // controlla se è già docente del corso
        $presente = true;
        try {
          $ris = $this->serviceGsuite['classroom']->courses_teachers->get('d:'.$corso, $docente);
        } catch (\Exception $e) {
          // docente non presente
          $presente = false;
        }
        if (!$presente) {
          // aggiunge docente
          $teacher = new GTeacher([
            'userId' => $docente]);
          $ris = $this->serviceGsuite['classroom']->courses_teachers->create('d:'.$corso, $teacher);
        }
      }
      // aggiunge docente a gruppo classe
      $errore = $this->aggiungeUtenteGruppoGsuite($docente, 'docenti'.strtolower($classe).'@'.$dominio);
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[creaCorsoGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Aggiunge un alunno a un corso della GSuite
   *
   * @param string $studente Email dello studente (già esistente nel sistema)
   * @param string $corso Nome breve del corso (già esistente nel sistema)
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function aggiungeAlunnoCorsoGsuite($studente, $corso) {
    // init
    $errore = null;
    try {
      // controlla se studente è presente in corso
      $presente = true;
      try {
        $ris = $this->serviceGsuite['classroom']->courses_students->get('d:'.$corso, $studente);
      } catch (\Exception $e) {
        // studente non presente
        $presente = false;
      }
      if (!$presente) {
        // aggiunge studente a corso
        $student = new GStudent([
          'userId' => $studente]);
        $ris = $this->serviceGsuite['classroom']->courses_students->create('d:'.$corso, $student);
      }
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[aggiungeAlunnoCorsoGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Rimuove un alunno da un corso della GSuite
   *
   * @param string $studente Email dello studente (già esistente nel sistema)
   * @param string $corso Nome breve del corso (già esistente nel sistema)
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function rimuoveAlunnoCorsoGsuite($studente, $corso) {
    // init
    $errore = null;
    try {
      // controlla se studente è presente in corso
      $presente = true;
      try {
        $ris = $this->serviceGsuite['classroom']->courses_students->get('d:'.$corso, $studente);
      } catch (\Exception $e) {
        // studente non presente
        $presente = false;
      }
      if ($presente) {
        // rimuove studente
        $ris = $this->serviceGsuite['classroom']->courses_students->delete('d:'.$corso, $studente);
      }
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[rimuoveAlunnoCorsoGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Rimuove un docente da un corso della GSuite. Toglie docente da gruppo classe.
   *
   * @param string $docente Email del docente (già esistente nel sistema)
   * @param string $corso Nome breve del corso (già esistente nel sistema)
   * @param boolean $cancellagruppo Vero per cancellare docente da gruppo classe.
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function rimuoveDocenteCorsoGsuite($docente, $corso, $cancellagruppo) {
    // init
    $errore = null;
    $dominio = $this->session->get('/CONFIG/SISTEMA/dominio_id_provider');
    $classe = substr($corso, 0, 2);
    try {
      // lista docenti del corso
      $teachers = $this->serviceGsuite['classroom']->courses_teachers->listCoursesTeachers('d:'.$corso);
      if (count($teachers->teachers) > 1) {
        // più di un docente nel corso
        $user = $this->serviceGsuite['directory']->users->get($docente);
        $course = $this->serviceGsuite['classroom']->courses->get('d:'.$corso);
        if ($user->id == $course->ownerId) {
          // docente è proprietario
          $altro = null;
          foreach ($teachers->teachers as $t) {
            if ($t->userId != $user->id) {
              // trova altro docente
              $altro = $t->userId;
              break;
            }
          }
          // passa la proprietà ad altro docente
          $course->ownerId = $altro;
          $ris = $this->serviceGsuite['classroom']->courses->patch('d:'.$corso, $course, ['updateMask' => 'ownerId']);
        }
        // rimuove docente da corso
        $ris = $this->serviceGsuite['classroom']->courses_teachers->delete('d:'.$corso, $docente);
      }
      if ($cancellagruppo) {
        // rimuove docente da gruppo classe
        $errore = $this->rimuoveUtenteGruppoGsuite($docente, 'docenti'.strtolower($classe).'@'.$dominio);
      }
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[rimuoveDocenteCorsoGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Aggiunge un docente al Consiglio di Classe su GSuite.
   *
   * @param string $docente Email del docente del corso (utente già esistente)
   * @param string $classe Nome della classe
   * @param string $anno Anno scolastico
   * @param boolean $coordinatore Vero per inserire docente come coordinatore/segretario
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function aggiungeDocenteCdcGsuite($docente, $classe, $anno, $coordinatore) {
    // init
    $errore = null;
    $corso = strtoupper('CONSIGLIO-'.$classe.'-'.$anno);
    try {
      // controlla se è presente come coordinatore/segretario
      $presente = true;
      try {
        $ris = $this->serviceGsuite['classroom']->courses_teachers->get('d:'.$corso, $docente);
      } catch (\Exception $e) {
        // docente non presente
        $presente = false;
      }
      if ($presente && !$coordinatore) {
        // docente rimosso da coordinatore/segretario
        $ris = $this->serviceGsuite['classroom']->courses_teachers->delete('d:'.$corso, $docente);
        $presente = false;
      } elseif (!$presente) {
        // controlla se è presente come docente
        $presente = true;
        try {
          $ris = $this->serviceGsuite['classroom']->courses_students->get('d:'.$corso, $docente);
        } catch (\Exception $e) {
          // docente non presente
          $presente = false;
        }
        if ($presente && $coordinatore) {
          // docente rimosso
          $ris = $this->serviceGsuite['classroom']->courses_students->delete('d:'.$corso, $docente);
          $presente = false;
        }
      }
      // inserisce se non presente
      if (!$presente && $coordinatore) {
        // aggiunge coordinatore/segretario
        $teacher = new GTeacher([
          'userId' => $docente]);
        $ris = $this->serviceGsuite['classroom']->courses_teachers->create('d:'.$corso, $teacher);
      } elseif (!$presente && !$coordinatore) {
        // aggiunge docente
        $student = new GStudent([
          'userId' => $docente]);
        $ris = $this->serviceGsuite['classroom']->courses_students->create('d:'.$corso, $student);
      }
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[creaCorsoGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Rimuove un docente dal Consiglio di Classe su GSuite.
   *
   * @param string $docente Email del docente del corso (utente già esistente)
   * @param string $classe Nome della classe
   * @param string $anno Anno scolastico
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function rimuoveDocenteCdcGsuite($docente, $classe, $anno) {
    // init
    $errore = null;
    $corso = strtoupper('CONSIGLIO-'.$classe.'-'.$anno);
    try {
      // controlla se è presente come coordinatore/segretario
      $presente = true;
      try {
        $ris = $this->serviceGsuite['classroom']->courses_teachers->get('d:'.$corso, $docente);
      } catch (\Exception $e) {
        // docente non presente
        $presente = false;
      }
      if ($presente) {
        // docente rimosso da coordinatore/segretario
        $ris = $this->serviceGsuite['classroom']->courses_teachers->delete('d:'.$corso, $docente);
      } else {
        // controlla se è presente come docente
        $presente = true;
        try {
          $ris = $this->serviceGsuite['classroom']->courses_students->get('d:'.$corso, $docente);
        } catch (\Exception $e) {
          // docente non presente
          $presente = false;
        }
        if ($presente) {
          // docente rimosso
          $ris = $this->serviceGsuite['classroom']->courses_students->delete('d:'.$corso, $docente);
        }
      }
    } catch (\Exception $e) {
      // errore
      $msg = json_decode($e->getMessage(), true);
      $errore = '[creaCorsoGsuite] '.(isset($msg['error']) ? $msg['error']['message'] : $e->getMessage());
    }
    // restituisce eventuale errore
    return $errore;
  }


  //==================== METODI PRIVATI PER LA GESTIONE MOODLE ====================

  /**
   * Inizializza il servizio di gestione di Moodle
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function inizializzaMoodle() {
    // init
    $errore = null;
    try {
      $config = json_decode(file_get_contents($this->dirProgetto.'/config/secrets/registro-elettronico-utenti-moodle.json'));
      $client = new Client(['base_uri' => $config->domain]);
      $this->serviceMoodle = array();
      $this->serviceMoodle['config'] = $config;
      $this->serviceMoodle['client'] = $client;
    } catch (\Exception $e) {
      // errore
      $errore = '[inizializzaMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Restituisce l'ID di un utente di MOODLE
   *
   * @param string $utente Username dell'utente (già esistente nel sistema)
   *
   * @return int ID dell'utente (eccezione se non trovato)
   */
  private function idUtenteMoodle($utente) {
    $functionname = 'core_user_get_users_by_field';
    $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['field' => 'username', 'values' => [$utente]]]);
    $msg = json_decode($ris->getBody());
    if (isset($msg->exception)) {
      // esce con errore
      throw new \Exception('[idUtenteMoodle] '.$msg->message);
    }
    // restituisce id utente
    return $msg[0]->id;
  }

  /**
   * Restituisce l'ID di un gruppo di MOODLE
   *
   * @param string $gruppo Nome breve del gruppo (già esistente nel sistema)
   *
   * @return int ID del gruppo (eccezione se non trovato)
   */
  private function idGruppoMoodle($gruppo) {
    $functionname = 'core_cohort_search_cohorts';
    $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    $context = array(
      'contextlevel' => 'system',
    );
    $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['query' => $gruppo, 'context' => $context]]);
    $msg = json_decode($ris->getBody());
    if (isset($msg->exception)) {
      // esce con errore
      throw new \Exception('[idGruppoMoodle] '.$msg->message);
    }
    // restituisce id gruppo
    return $msg->cohorts[0]->id;
  }

  /**
   * Restituisce l'ID di una categoria relativa alla classe di un corso di MOODLE
   *
   * @param string $sede Sede della classe del corso
   * @param string $indirizzo Indirizzo scolastico della classe del corso
   *
   * @return int ID della categoria (eccezione se non trovata)
   */
  private function idCategoriaMoodle($sede, $indirizzo) {
    // crea codice categoria
    $indirizzi = array();
    $indirizzi['Ist. Tecn. Inf. Telecom.'] = 'BT';        // biennio tecnico
    $indirizzi['Ist. Tecn. Chim. Mat. Biotecn.'] = 'BT';  // biennio tecnico
    $indirizzi['Ist. Tecn. Art. Informatica'] = 'INF';    // tecnico articolazione informatica
    $indirizzi['Ist. Tecn. Art. Chimica Mat.'] = 'CHI';   // tecnico articolazione chimica
    $indirizzi['Ist. Tecn. Art. Biotecn. Amb.'] = 'AMB';  // tecnico articolazione biotecnologie ambientali
    $indirizzi['Liceo Scienze Applicate'] = 'LSA';        // liceo scientifico scienze applicate
    $categoria = strtoupper(substr($sede, 0, 2)).'-'.$indirizzi[$indirizzo];
    // cerca categoria
    $functionname = 'core_course_get_categories';
    $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    $criteria = array(
      'key' => 'idnumber',
      'value' => $categoria);
    $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['criteria' => [$criteria]]]);
    $msg = json_decode($ris->getBody());
    if (isset($msg->exception)) {
      // esce con errore
      throw new \Exception('[idCategoriaMoodle] '.$msg->message);
    }
    // restituisce id categoria
    return $msg[0]->id;
  }

  /**
   * Aggiunge un utente a un gruppo di MOODLE
   *
   * @param string $utente Username dell'utente (già esistente nel sistema)
   * @param string $gruppo Nome o nome breve del gruppo (già esistente nel sistema)
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function aggiungeUtenteGruppoMoodle($utente, $gruppo) {
    // init
    $errore = null;
    try {
      $functionname = 'core_cohort_add_cohort_members';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $member = array(
        'cohorttype' => [
          'type' => 'idnumber',
          'value' => $gruppo],
        'usertype' => [
          'type' => 'username',
          'value' => $utente]);
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['members' => [$member]]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[aggiungeUtenteGruppoMoodle] '.$msg->message;
      }
    } catch (\Exception $e) {
      // errore
      $errore = '[aggiungeUtenteGruppoMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Rimuove un utente da un gruppo di MOODLE
   *
   * @param int $idutente ID dell'utente (già esistente nel sistema)
   * @param int $idgruppo ID del gruppo (già esistente nel sistema)
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function rimuoveUtenteGruppoMoodle($idutente, $idgruppo) {
    // init
    $errore = null;
    try {
      $functionname = 'core_cohort_delete_cohort_members';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $member = array(
        'cohortid' => $idgruppo,
        'userid' => $idutente);
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['members' => [$member]]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[rimuoveUtenteGruppoMoodle] '.$msg->message;
      }
    } catch (\Exception $e) {
      // errore
      $errore = '[rimuoveUtenteGruppoMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Crea un utente di MOODLE e lo aggiunge al gruppo (Docenti/Studenti)
   *
   * @param string $nome Nome dell'utente
   * @param string $cognome Cognome dell'utente
   * @param string $username Username dell'utente
   * @param string $email Email dell'utente (appartente al domionio GSuite)
   * @param string $password Password in chiaro dell'utente
   * @param string $tipo Tipo di utente [D=docente/staff/preside, A=alunno]
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function creaUtenteMoodle($nome, $cognome, $username, $email, $password, $tipo) {
    // init
    $errore = null;
    try {
      if ($tipo == 'D') {
        // docenti/staff/preside
        $gruppo = 'Docenti';
      } else {
        // alunni
        $gruppo = 'Studenti';
      }
      // crea utente
      $functionname = 'core_user_create_users';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $user = array(
        'firstname' => $nome,
        'lastname' => $cognome,
        'username' => $username,
        'password' => $password,
        'email' => $email,
        'mailformat' => 1,
        'city' => $this->session->get('/CONFIG/ISTITUTO/sede_0_citta'),
        'country' => 'IT');
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['users' => [$user]]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[creaUtenteMoodle] '.$msg->message;
      } else {
        // aggiunge a gruppo
        $errore = $this->aggiungeUtenteGruppoMoodle($username, $gruppo);
      }
    } catch (\Exception $e) {
      // errore
      $errore = '[creaUtenteMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Modifica i dati di un utente di MOODLE
   *
   * @param int $idutente ID dell'utente (già esistente nel sistema)
   * @param string $nome Nome dell'utente
   * @param string $cognome Cognome dell'utente
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function modificaUtenteMoodle($idutente, $nome, $cognome) {
    // init
    $errore = null;
    try {
      $functionname = 'core_user_update_users';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $user = array(
        'id' => $idutente,
        'firstname' => $nome,
        'lastname' => $cognome);
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['users' => [$user]]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[modificaUtenteMoodle] '.$msg->message;
      }
    } catch (\Exception $e) {
      // errore
      $errore = '[modificaUtenteMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Modifica la password di un utente di MOODLE
   *
   * @param int $idutente ID dell'utente (già esistente nel sistema)
   * @param string $password Password in chiaro dell'utente
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function passwordUtenteMoodle($idutente, $password) {
    // init
    $errore = null;
    try {
      $functionname = 'core_user_update_users';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $user = array(
        'id' => $idutente,
        'password' => $password);
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['users' => [$user]]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[passwordUtenteMoodle] '.$msg->message;
      }
    } catch (\Exception $e) {
      // errore
      $errore = '[passwordUtenteMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Sospende/riattiva un utente di MOODLE e lo toglie/aggiunge dal gruppo (Docenti/Studenti)
   *
   * @param string $utente Username dell'utente (già esistente nel sistema)
   * @param string $tipo Tipo di utente [D=docente/staff/preside, A=alunno]
   * @param boolean $sospeso Vero per sospendere, falso per riattivare
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function sospendeUtenteMoodle($utente, $tipo, $sospeso) {
    // init
    $errore = null;
    try {
      if ($tipo == 'D') {
        // docenti/staff/preside
        $gruppo = 'Docenti';
      } else {
        // alunni
        $gruppo = 'Studenti';
      }
      // ricava id utente
      $idutente = $this->idUtenteMoodle($utente);
      // sospende/riattiva utente
      $functionname = 'core_user_update_users';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $user = array(
        'id' => $idutente,
        'suspended' => $sospeso);
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['users' => [$user]]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[sospendeUtenteMoodle] '.$msg->message;
      } else {
        if ($sospeso) {
          // ricava id gruppo
          $idgruppo = $this->idGruppoMoodle($gruppo);
          // toglie da gruppo
          $errore = $this->rimuoveUtenteGruppoMoodle($idutente, $idgruppo);
        } else {
          // aggiunge a gruppo
          $errore = $this->aggiungeUtenteGruppoMoodle($utente, $gruppo);
        }
      }
    } catch (\Exception $e) {
      // errore
      $errore = '[sospendeUtenteMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Crea corso o aggiunge docente a corso esistente su Moodle
   *
   * @param string $docente Username del docente del corso (utente già esistente)
   * @param string $classe Nome della classe
   * @param string $sede Sede della classe
   * @param string $indirizzo Indirizzo scolastico della classe
   * @param string $materia Nome breve della materia
   * @param string $anno Anno scolastico
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function creaCorsoMoodle($docente, $classe, $sede, $indirizzo, $materia, $anno) {
    // init
    $errore = null;
    $nomecorso = "$classe - $materia - $anno";
    $corso = strtoupper($classe.'-'.str_replace([' ','.',',','(',')'], '', $materia).'-'.$anno);
    try {
      // controlla esistenza corso
      $functionname = 'core_course_get_courses_by_field';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['field' => 'shortname',
        'value' => $corso]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[creaCorsoMoodle] '.$msg->message;
        return $errore;
      }
      if (empty($msg->courses)) {
        // crea corso
        $idcategoria = $this->idCategoriaMoodle($sede, $indirizzo);
        $functionname = 'core_course_create_courses';
        $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
          '&moodlewsrestformat=json';
        $course = array(
          'fullname' => $nomecorso,
          'shortname' => $corso,
          'categoryid' => $idcategoria);
        $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['courses' => [$course]]]);
        $msg = json_decode($ris->getBody());
        if (isset($msg->exception)) {
          // errore
          $errore = '[creaCorsoMoodle] '.$msg->message;
          return $errore;
        } else {
          $idcorso = $msg[0]->id;
        }
      } else {
        // corso esiste
        $idcorso = $msg->courses[0]->id;
      }
      // aggiunge docente
      $iddocente = $this->idUtenteMoodle($docente);
      $functionname = 'enrol_manual_enrol_users';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $enrolment = array(
        'roleid' => 10,   // ruolo 10: docentegiua
        'userid' => $iddocente,
        'courseid' => $idcorso);
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['enrolments' => [$enrolment]]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[creaCorsoMoodle] '.$msg->message;
        return $errore;
      }
    } catch (\Exception $e) {
      // errore
      $errore = '[creaCorsoMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Aggiunge un gruppo classe ad un corso di MOODLE
   *
   * @param string $classe Classe da aggiungere al corso
   * @param int $idcorso ID del corso
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function aggiungeClasseCorsoMoodle($classe, $idcorso) {
    // init
    $errore = null;
    try {
      // trova gruppo classe
      $functionname = 'core_cohort_search_cohorts';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $context = array('contextlevel' => 'system');
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['query' => $classe, 'context' => $context]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[aggiungeClasseCorsoMoodle] '.$msg->message;
        return $errore;
      }
      $idgruppo = $msg->cohorts[0]->id;
      // aggiunge gruppo classe a corso
      $functionname = 'local_ws_enrolcohort_add_instance';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $instance = array(
        'roleid' => 11,   // ruolo 11: studentegiua
        'courseid' => $idcorso,
        'cohortid' => $idgruppo);
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['instance' => $instance]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[aggiungeClasseCorsoMoodle] '.$msg->message;
        return $errore;
      }
    } catch (\Exception $e) {
      // errore
      $errore = '[aggiungeClasseCorsoMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Rimuove un docente da un corso di MOODLE
   *
   * @param int $idutente ID dell'utente (già esistente nel sistema)
   * @param int $idcorso ID del corso (già esistente nel sistema)
   *
   * @return string Eventuale messaggio di errore (stringa nulla se tutto OK)
   */
  private function rimuoveDocenteCorsoMoodle($idutente, $idcorso) {
    // init
    $errore = null;
    try {
      $functionname = 'enrol_manual_unenrol_users';
      $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
        '&moodlewsrestformat=json';
      $enrolment = array(
        'userid' => $idutente,
        'courseid' => $idcorso);
      $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['enrolments' => [$enrolment]]]);
      $msg = json_decode($ris->getBody());
      if (isset($msg->exception)) {
        // errore
        $errore = '[rimuoveDocenteCorsoMoodle] '.$msg->message;
        return $errore;
      }
    } catch (\Exception $e) {
      // errore
      $errore = '[rimuoveDocenteCorsoMoodle] '.$e->getMessage();
    }
    // restituisce eventuale errore
    return $errore;
  }

  /**
   * Restituisce l'ID di un corso di MOODLE
   *
   * @param string $corso Nome breve del corso (già esistente nel sistema)
   *
   * @return int ID del corso (eccezione se non trovato)
   */
  private function idCorsoMoodle($corso) {
    $functionname = 'core_course_get_courses_by_field';
    $url = '/webservice/rest/server.php?wstoken='.$this->serviceMoodle['config']->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    $ris = $this->serviceMoodle['client']->post($url, ['form_params' => ['field' => 'shortname',
      'value' => $corso]]);
    $msg = json_decode($ris->getBody());
    if (isset($msg->exception)) {
      // esce con errore
      throw new \Exception('[idCorsoMoodle] '.$msg->message);
    }
    // restituisce id corso
    return $msg->courses[0]->id;
  }

}
