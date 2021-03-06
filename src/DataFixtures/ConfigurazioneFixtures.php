<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Configurazione;


/**
 * ConfigurazioneFixtures - dati iniziali di test
 *
 *  Dati caricati per ogni parametro della configurazione:
 *    $categoria: nome della categoria del parametro
 *    $parametro: nome del parametro (testo senza spazi, univoco)
 *    $descrizione: descrizione dell'uso del parametro
 *    $valore: valore del parametro (default: nullo)
 *    $gestito: vero se il parametro è gestito da una apposita procedura, falso altrimenti
 */
class ConfigurazioneFixtures extends Fixture implements FixtureGroupInterface {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Carica i dati da inizializzare nel database
   *
   * @param ObjectManager $em Gestore dei dati su database
   */
  public function load(ObjectManager $em) {
    // carica dati
    //--- categoria SISTEMA
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('versione')
      ->setDescrizione("Numero di versione dell'applicazione<br>[testo]")
      ->setValore('1.3.0')
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('manutenzione_inizio')
      ->setDescrizione("Inizio della modalità manutenzione durante la quale il registro è offline<br>[formato: 'AAAA-MM-GG HH:MM']")
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('manutenzione_fine')
      ->setDescrizione("Fine della modalità manutenzione durante la quale il registro è offline<br>[formato: 'AAAA-MM-GG HH:MM']")
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('banner_login')
      ->setDescrizione("Messaggio da visualizzare nella pagina pubblica di login<br>[testo HTML]")
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('banner_home')
      ->setDescrizione("Messaggio da visualizzare nella pagina home degli utenti autenticati<br>[testo HTML]")
      ->setGestito(true);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('id_provider')
      ->setDescrizione("Se presente indica l'uso di un identity provider esterno (es. SSO su GSuite)<br>[testo]")
      ->setGestito(false);
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('dominio_default')
      ->setDescrizione("Indica il dominio di posta predefinito per le email degli utenti (usato nell'importazione)<br>[testo]")
      ->setGestito(false)
      ->setValore('noemail.local');
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('dominio_id_provider')
      ->setDescrizione("Nel caso si utilizzi un identity provider esterno, indica il dominio di posta predefinito per le email degli utenti (usato nell'importazione)<br>[testo]")
      ->setGestito(false);
    //--- categoria SCUOLA
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_scolastico')
      ->setDescrizione("Anno scolastico corrente<br>[formato: 'AAAA/AAAA']")
      ->setValore('2020/2021');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_inizio')
      ->setDescrizione("Data dell'inizio dell'anno scolastico<br>[formato: 'AAAA-MM-GG']")
      ->setValore('2020-09-22');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_fine')
      ->setDescrizione("Data della fine dell'anno scolastico<br>[formato: 'AAAA-MM-GG']")
      ->setValore('2021-06-12');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_nome')
      ->setDescrizione("Nome del primo periodo dell'anno scolastico (primo trimestre/quadrimestre)<br>[testo]")
      ->setValore('Primo Quadrimestre');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_fine')
      ->setDescrizione("Data della fine del primo periodo, da 'anno_inizio' sino al giorno indicato incluso<br>[formato: 'AAAA-MM-GG']")
      ->setValore('2021-01-31');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_nome')
      ->setDescrizione("Nome del secondo periodo dell'anno scolastico (secondo trimestre/quadrimestre/pentamestre)<br>[testo]")
      ->setValore('Secondo Quadrimestre');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_fine')
      ->setDescrizione("Data della fine del secondo periodo, da 'periodo1_fine'+1 sino al giorno indicato incluso (se non è usato un terzo periodo, la data dovrà essere uguale a 'anno_fine')<br>[formato 'AAAA-MM-GG']")
      ->setValore('2021-06-12');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo3_nome')
      ->setDescrizione("Nome del terzo periodo dell'anno scolastico (terzo trimestre) o vuoto se non usato (se è usato un terzo periodo, inizia a 'periodo2_fine'+1 e finisce a 'anno_fine')<br>[testo]")
      ->setValore('');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('ritardo_breve')
      ->setDescrizione("Numero di minuti per la definizione di ritardo breve (non richiede giustificazione)<br>[intero]")
      ->setValore('10');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('mesi_colloqui')
      ->setDescrizione("Mesi con i colloqui generali, nei quali non si può prenotare il colloquio individuale<br>[lista separata da virgola dei numeri dei mesi in formato MM]")
      ->setValore('12,3');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('notifica_circolari')
      ->setDescrizione("Ore di notifica giornaliera delle nuove circolari<br>[lista separata da virgola delle ore in formato HH]")
      ->setValore('15,18,20');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('assenze_dichiarazione')
      ->setDescrizione("Indica se le assenze online devono inglobare l'autodichiarazione NO-COVID<br>[booleano, 0 o 1]")
      ->setValore('0');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('assenze_ore')
      ->setDescrizione("Indica se le assenze devono essere gestite su base oraria e non giornaliera<br>[booleano, 0 o 1]")
      ->setValore('0');
    //--- categoria ACCESSO
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('blocco_inizio')
      ->setDescrizione("Inizio orario del blocco di alcune modalità di accesso per i docenti<br>[formato: 'HH:MM', vuoto se nessun blocco]")
      ->setValore('');
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('blocco_fine')
      ->setDescrizione("Fine orario del blocco di alcune modalità di accesso per i docenti<br>[formato 'HH:MM', vuoto se nessun blocco]")
      ->setValore('');
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('ip_scuola')
      ->setDescrizione("Lista degli IP dei router di scuola (accerta che login provenga da dentro l'istituto)<br>[lista separata da virgole degli IP]")
      // localhost: 127.0.0.1
      ->setValore('127.0.0.1');
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('giorni_festivi_istituto')
      ->setDescrizione("Indica i giorni festivi settimanali per l'intero istituto<br>[lista separata da virgole nel formato: 0=domenica, 1=lunedì, ... 6=sabato]")
      ->setValore('0');
    $param[] = (new Configurazione())
      ->setCategoria('ACCESSO')
      ->setParametro('giorni_festivi_classi')
      ->setDescrizione("Indica i giorni festivi settimanali per singole classi (per gestire settimana corta anche per solo alcune classi)<br>[lista separata da virgole nel formato 'giorno:classe'; giorno: 0=domenica, 1=lunedì, ... 6=sabato; classe: 1A, 2A, ...]")
      ->setValore('6:1S,6:2S,6:1T,6:2T');
    // rende persistenti i parametri
    foreach ($param as $obj) {
      $em->persist($obj);
    }
    // memorizza dati
    $em->flush();
  }

  /**
   * Restituisce la lista dei gruppi a cui appartiene la fixture
   *
   * @return array Lista dei gruppi di fixture
   */
  public static function getGroups(): array {
    return array(
      'App', // dati iniziali dell'applicazione
    );
  }

}
