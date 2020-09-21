<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Util;

use Google_Client as GClient;
use Google_Service_Directory as GDirectory;
use Google_Service_Directory_User as GUser;
use Google_Service_Directory_Member as GMember;
use Google_Service_Directory_UserPhoto as GPhoto;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Utente;
use App\Entity\Staff;
use App\Entity\Docente;
use App\Entity\Alunno;


/**
 * AccountProvisioning - classe di utilità per la gestione del provisioning su servizi esterni
 */
class AccountProvisioning {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;

  /**
   * @var string $dirProgetto Percorso per i file dell'applicazione
   */
  private $dirProgetto;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param SessionInterface $session Gestore delle sessioni
   * @param string $dirProgetto Percorso per i file dell'applicazione
   */
  public function __construct(SessionInterface $session, $dirProgetto) {
    $this->session = $session;
    $this->dirProgetto = $dirProgetto;
  }

  /**
   * Inizializza il servizio di gestione della GSuite
   *
   * @return GDirectory Servizio per la gestione della GSuite (o null se errore)
   */
  public function initGsuite() {
    // init
    $service = null;
    try {
      $client = new GClient();
      $client->setAuthConfig($this->dirProgetto.'/config/secrets/registro-elettronico-utenti-gsuite.json');
      $client->setSubject($this->session->get('/CONFIG/ISTITUTO/email_amministratore'));
      $client->setApplicationName("Registro Elettronico Utenti");
      $client->addScope('https://www.googleapis.com/auth/admin.directory.user');
      $client->addScope('https://www.googleapis.com/auth/admin.directory.group');
      $service = new GDirectory($client);
    } catch (\Exception $e) {
      // errore: evita eccezione
    }
    // restituisce il servizio
    return $service;
  }

  /**
   * Crea un nuovo utente sulla GSuite
   * L'utente deve avere impostato l'email corrispondente a <utente_registro>@<dominio_gsuite>
   * Inoltre l'utente deve avere impostato la password in chiaro.
   *
   * @param GDirectory $service Servizio per la gestione della GSuite
   * @param Utente $utente Utente del registro
   *
   * @return string Stato dopo le operazioni eseguite ('OK' o messaggio di errore)
   */
  public function creaUtenteGsuite(GDirectory $service, Utente $utente) {
    // init
    $stato = 'OK';
    if ($utente instanceOf Docente) {
      // nuovo docente/staff/preside
      try {
        // crea utente
        $user = new GUser([
          'name' => ['givenName' => $utente->getNome(),
            'familyName' => $utente->getCognome()],
          'gender' => ['type' => ($utente->getSesso() == 'M' ? 'male' : 'female')],
          'password' => sha1($utente->getPasswordNonCifrata()),
          'hashFunction' => 'SHA-1',
          'primaryEmail' => $utente->getEmail(),
          'orgUnitPath' => '/Docenti']);
        $ris = $service->users->insert($user);
        // aggiunge a gruppo docenti
        $member = new GMember([
          'email' => $utente->getEmail(),
          'role' => 'MEMBER',
          'type' => 'USER']);
        $ris = $service->members->insert('docenti@giua.edu.it', $member);
        // aggiunge a gruppo staff
        if ($utente instanceOf Staff) {
          // staff/preside
          $ris = $service->members->insert('staff@giua.edu.it', $member);
        }
        // aggiunge avatar
        $hash = sha1($utente->getEmail());
        $type = (time() % 2) == 0 ? 'identicon' : 'retro';
        $url = 'https://www.gravatar.com/avatar/'.$hash.'?s=96&d='.$type;
        $image = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(file_get_contents($url)));
        $photo = new GPhoto([
          'photoData' => $image,
          'mimeType' => 'JPEG',
          'height' => 96,
          'width' => 96]);
        $ris = $service->users_photos->update($utente->getEmail(), $photo);
      } catch (\Exception $e) {
        // conserva messaggio di errore
        $stato = json_decode($e->getMessage(), true)['error']['message'];
      }
    } elseif ($utente instanceOf Alunno) {
      // nuovo alunno
die('-- crea alunno');;
    }
    // restituisce stato
    return $stato;
  }

  /**
   * Modifica i dati un utente esistente sulla GSuite
   * L'utente deve avere impostato l'email corrispondente a <utente_registro>@<dominio_gsuite>
   *
   * @param GDirectory $service Servizio per la gestione della GSuite
   * @param Utente $utente Utente del registro dopo le operazioni eseguite
   *
   * @return string Stato dopo le operazioni eseguite ('OK' o messaggio di errore)
   */
  public function modificaUtenteGsuite(GDirectory $service, Utente $utente) {
    // init
    $stato = 'OK';
    if (($utente instanceOf Docente) || ($utente instanceOf Alunno)) {
      // docente/staff/preside/alunno
      try {
        // modifica utente
        $user = new GUser([
          'name' => ['givenName' => $utente->getNome(),
            'familyName' => $utente->getCognome()],
          'gender' => ['type' => ($utente->getSesso() == 'M' ? 'male' : 'female')],
          ]);
        $ris = $service->users->update($utente->getEmail(), $user);
      } catch (\Exception $e) {
        // conserva messaggio di errore
        $stato = json_decode($e->getMessage(), true)['error']['message'];
      }
    }
    // restituisce stato
    return $stato;
  }

  /**
   * Modifica la password di un utente esistente sulla GSuite
   * L'utente deve avere impostato l'email corrispondente a <utente_registro>@<dominio_gsuite>
   * Inoltre l'utente deve avere impostato la password in chiaro.
   *
   * @param GDirectory $service Servizio per la gestione della GSuite
   * @param Utente $utente Utente del registro
   *
   * @return string Stato dopo le operazioni eseguite ('OK' o messaggio di errore)
   */
  public function passwordUtenteGsuite(GDirectory $service, Utente $utente) {
    // init
    $stato = 'OK';
    if (($utente instanceOf Docente) || ($utente instanceOf Alunno)) {
      // docente/staff/preside/alunno
      try {
        // modifica utente
        $user = new GUser([
          'password' => sha1($utente->getPasswordNonCifrata()),
          'hashFunction' => 'SHA-1']);
        $ris = $service->users->update($utente->getEmail(), $user);
      } catch (\Exception $e) {
        // conserva messaggio di errore
        $stato = json_decode($e->getMessage(), true)['error']['message'];
      }
    }
    // restituisce stato
    return $stato;
  }

  /**
   * Sospende un utente esistente sulla GSuite
   *
   * @param GDirectory $service Servizio per la gestione della GSuite
   * @param Utente $utente Utente del registro dopo le operazioni eseguite
   *
   * @return string Stato dopo le operazioni eseguite ('OK' o messaggio di errore)
   */
  public function sospendeUtenteGsuite(GDirectory $service, Utente $utente) {
    // init
    $stato = 'OK';
    if (($utente instanceOf Docente) || ($utente instanceOf Alunno)) {
      // docente/staff/preside/alunno
      try {
        // modifica utente
        $user = new GUser([
          'suspended' => true]);
        $ris = $service->users->update($utente->getEmail(), $user);
      } catch (\Exception $e) {
        // conserva messaggio di errore
        $stato = json_decode($e->getMessage(), true)['error']['message'];
      }
    }
    // restituisce stato
    return $stato;
  }

  /**
   * Inizializza il servizio di gestione di Moodle
   *
   * @return array Dati e client per gestire i servizi Moodle (o null se errore)
   */
  public function initMoodle() {
    // init
    $service = null;
    try {
      $config = json_decode(file_get_contents($this->dirProgetto.'/config/secrets/registro-elettronico-utenti-moodle.json'));
      $client = new Client(['base_uri' => $config->domain]);
      $service = array($config, $client);
    } catch (\Exception $e) {
      // errore: evita eccezione
    }
    // restituisce il servizio
    return $service;
  }

  /**
   * Crea un nuovo utente sul MOODLE
   * L'utente deve avere impostato l'email corrispondente a <utente_registro>@<dominio_gsuite>
   * Inoltre l'utente deve avere impostato la password in chiaro.
   *
   * @param array $service Dati e client per gestire i servizi Moodle
   * @param Utente $utente Utente del registro
   *
   * @return string Stato dopo le operazioni eseguite ('OK' o messaggio di errore)
   */
  public function creaUtenteMoodle($service, Utente $utente) {
    // init
    $stato = 'OK';
    $functionname = 'core_user_create_users';
    $url = '/webservice/rest/server.php?wstoken='.$service[0]->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    if ($utente instanceOf Docente) {
      // nuovo docente/staff/preside
      try {
        // crea utente
        $user = array(
          'firstname' => $utente->getNome(),
          'lastname' => $utente->getCognome(),
          'username' => $utente->getUsername(),
          'password' => $utente->getPasswordNonCifrata(),
          'email' => $utente->getEmail(),
          'mailformat' => 1,
          'city' => $this->session->get('/CONFIG/ISTITUTO/sede_0_citta'),
          'country' => 'IT');
        $ris = $service[1]->post($url, ['form_params' => ['users' => [$user]]]);
        $msg = json_decode($ris->getBody());
        if (isset($msg->exception)) {
          // errore
          $stato = $msg->message;
        } else {
          // aggiunge a gruppo globale
          $functionname = 'core_cohort_add_cohort_members';
          $url = '/webservice/rest/server.php?wstoken='.$service[0]->token.'&wsfunction='.$functionname.
            '&moodlewsrestformat=json';
          $member = array(
            'cohorttype' => [
              'type' => 'idnumber',
              'value' => 'Docenti'],
            'usertype' => [
              'type' => 'username',
              'value' => $utente->getUsername()]);
          $ris = $service[1]->post($url, ['form_params' => ['members' => [$member]]]);
          $msg = json_decode($ris->getBody());
          if (isset($msg->exception)) {
            // errore
            $stato = $msg->message;
          }
        }
      } catch (\Exception $e) {
        // errore imprevisto
        $stato = $e->getMessage();
      }
    } elseif ($utente instanceOf Alunno) {
      // nuovo alunno
die('-- crea alunno');;
    }
    // restituisce stato
    return $stato;
  }

  /**
   * Modifica i dati un utente esistente su MOODLE
   * L'utente deve avere impostato l'email corrispondente a <utente_registro>@<dominio_gsuite>
   *
   * @param array $service Dati e client per gestire i servizi Moodle
   * @param Utente $utente Utente del registro
   *
   * @return string Stato dopo le operazioni eseguite ('OK' o messaggio di errore)
   */
  public function modificaUtenteMoodle($service, Utente $utente) {
    // init
    $stato = 'OK';
    $functionname = 'core_user_get_users_by_field';
    $url1 = '/webservice/rest/server.php?wstoken='.$service[0]->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    $functionname = 'core_user_update_users';
    $url2 = '/webservice/rest/server.php?wstoken='.$service[0]->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    if (($utente instanceOf Docente) || ($utente instanceOf Alunno)) {
      // docente/staff/preside/alunno
      try {
        // legge utente
        $ris = $service[1]->post($url1, ['form_params' => ['field' => 'username',
          'values' => [$utente->getUsername()]]]);
        $msg = json_decode($ris->getBody());
        if (isset($msg->exception)) {
          // errore
          $stato = $msg->message;
        } else {
          // modifica utente
          $user = array(
            'id' => $msg[0]->id,
            'firstname' => $utente->getNome(),
            'lastname' => $utente->getCognome());
          $ris = $service[1]->post($url2, ['form_params' => ['users' => [$user]]]);
          $msg = json_decode($ris->getBody());
          if (isset($msg->exception)) {
            // errore
            $stato = $msg->message;
          }
        }
      } catch (\Exception $e) {
        // errore imprevisto
        $stato = $e->getMessage();
      }
    }
    // restituisce stato
    return $stato;
  }

  /**
   * Modifica la password di un utente esistente su Moodle
   * L'utente deve avere impostato l'email corrispondente a <utente_registro>@<dominio_gsuite>
   * Inoltre l'utente deve avere impostato la password in chiaro.
   *
   * @param array $service Dati e client per gestire i servizi Moodle
   * @param Utente $utente Utente del registro
   *
   * @return string Stato dopo le operazioni eseguite ('OK' o messaggio di errore)
   */
  public function passwordUtenteMoodle($service, Utente $utente) {
    // init
    $stato = 'OK';
    $functionname = 'core_user_get_users_by_field';
    $url1 = '/webservice/rest/server.php?wstoken='.$service[0]->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    $functionname = 'core_user_update_users';
    $url2 = '/webservice/rest/server.php?wstoken='.$service[0]->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    if (($utente instanceOf Docente) || ($utente instanceOf Alunno)) {
      // docente/staff/preside/alunno
      try {
        // legge utente
        $ris = $service[1]->post($url1, ['form_params' => ['field' => 'username',
          'values' => [$utente->getUsername()]]]);
        $msg = json_decode($ris->getBody());
        if (isset($msg->exception)) {
          // errore
          $stato = $msg->message;
        } else {
          // modifica utente
          $user = array(
            'id' => $msg[0]->id,
            'password' => $utente->getPasswordNonCifrata());
          $ris = $service[1]->post($url2, ['form_params' => ['users' => [$user]]]);
          $msg = json_decode($ris->getBody());
          if (isset($msg->exception)) {
            // errore
            $stato = $msg->message;
          }
        }
      } catch (\Exception $e) {
        // errore imprevisto
        $stato = $e->getMessage();
      }
    }
    // restituisce stato
    return $stato;
  }

  /**
   * Sospende un utente esistente su MOODLE
   *
   * @param array $service Dati e client per gestire i servizi Moodle
   * @param Utente $utente Utente del registro
   *
   * @return string Stato dopo le operazioni eseguite ('OK' o messaggio di errore)
   */
  public function sospendeUtenteMoodle($service, Utente $utente) {
    // init
    $stato = 'OK';
    $functionname = 'core_user_get_users_by_field';
    $url1 = '/webservice/rest/server.php?wstoken='.$service[0]->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    $functionname = 'core_user_update_users';
    $url2 = '/webservice/rest/server.php?wstoken='.$service[0]->token.'&wsfunction='.$functionname.
      '&moodlewsrestformat=json';
    if (($utente instanceOf Docente) || ($utente instanceOf Alunno)) {
      // docente/staff/preside/alunno
      try {
        // legge utente
        $ris = $service[1]->post($url1, ['form_params' => ['field' => 'username',
          'values' => [$utente->getUsername()]]]);
        $msg = json_decode($ris->getBody());
        if (isset($msg->exception)) {
          // errore
          $stato = $msg->message;
        } else {
          // modifica utente
          $user = array(
            'id' => $msg[0]->id,
            'suspended' => 1);
          $ris = $service[1]->post($url2, ['form_params' => ['users' => [$user]]]);
          $msg = json_decode($ris->getBody());
          if (isset($msg->exception)) {
            // errore
            $stato = $msg->message;
          }
        }
      } catch (\Exception $e) {
        // errore imprevisto
        $stato = $e->getMessage();
      }
    }
    // restituisce stato
    return $stato;
  }
}

/*********
crea alunno
gestione cattedre docenti
  - crea,sospendi,aggiungiDocente,togliDocente
gestione classi alunni
****/
