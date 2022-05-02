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


namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
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
      ->setValore('1.4.2')
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
      ->setDescrizione("Se presente, indica l'uso di un identity provider esterno (es. SSO su Google)<br>[testo]")
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
    $param[] = (new Configurazione())
      ->setCategoria('SISTEMA')
      ->setParametro('spid')
      ->setDescrizione("Indica la modalità dell'accesso SPID: 'no' = non utilizzato, 'si' = utilizzato, 'validazione' = utilizzato in validazione.<br>[si|no|validazione]")
      ->setGestito(true)
      ->setValore('no');
    //--- categoria SCUOLA
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_scolastico')
      ->setDescrizione("Anno scolastico corrente<br>[formato: 'AAAA/AAAA']")
      ->setValore('2021/2022');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_inizio')
      ->setDescrizione("Data dell'inizio dell'anno scolastico<br>[formato: 'AAAA-MM-GG']")
      ->setValore('2021-09-22');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('anno_fine')
      ->setDescrizione("Data della fine dell'anno scolastico<br>[formato: 'AAAA-MM-GG']")
      ->setValore('2022-06-12');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_nome')
      ->setDescrizione("Nome del primo periodo dell'anno scolastico (primo trimestre/quadrimestre)<br>[testo]")
      ->setValore('Primo Quadrimestre');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo1_fine')
      ->setDescrizione("Data della fine del primo periodo, da 'anno_inizio' sino al giorno indicato incluso<br>[formato: 'AAAA-MM-GG']")
      ->setValore('2022-01-31');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_nome')
      ->setDescrizione("Nome del secondo periodo dell'anno scolastico (secondo trimestre/quadrimestre/pentamestre)<br>[testo]")
      ->setValore('Secondo Quadrimestre');
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('periodo2_fine')
      ->setDescrizione("Data della fine del secondo periodo, da 'periodo1_fine'+1 sino al giorno indicato incluso (se non è usato un terzo periodo, la data dovrà essere uguale a 'anno_fine')<br>[formato 'AAAA-MM-GG']")
      ->setValore('2022-06-12');
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
      ->setDescrizione("Mesi con i colloqui generali, nei quali non si può prenotare il colloquio individuale<br>[lista separata da virgola dei numeri dei mesi]")
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
    $lista = ['min' => 20, 'max' => 27, 'suff' => 23, 'med' => 23,
      'valori' => '20,21,22,23,24,25,26,27',
      'etichette' => '"NC","","","Suff.","","","","Ottimo"',
      'voti' => '"Non Classificato","Insufficiente","Mediocre","Sufficiente","Discreto","Buono","Distinto","Ottimo"',
      'votiAbbr' => '"NC","Insufficiente","Mediocre","Sufficiente","Discreto","Buono","Distinto","Ottimo"'];
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('voti_finali_R')
      ->setDescrizione("Lista dei voti finali per Religione<br>[lista serializzata]")
      ->setGestito(true)
      ->setValore(serialize($lista));
    $lista = ['min' => 2, 'max' => 10, 'suff' => 6, 'med' => 5,
      'valori' => '2,3,4,5,6,7,8,9,10',
      'etichette' => '"NC",3,4,5,6,7,8,9,10',
      'voti' => '"Non Classificato",3,4,5,6,7,8,9,10',
      'votiAbbr' => '"NC",3,4,5,6,7,8,9,10'];
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('voti_finali_E')
      ->setDescrizione("Lista dei voti finali per Educazione Civica<br>[lista serializzata]")
      ->setGestito(true)
      ->setValore(serialize($lista));
    $lista = ['min' => 4, 'max' => 10, 'suff' => 6, 'med' => 6,
      'valori' => '4,5,6,7,8,9,10',
      'etichette' => '"NC",5,6,7,8,9,10',
      'voti' => '"Non Classificato",5,6,7,8,9,10',
      'votiAbbr' => '"NC",5,6,7,8,9,10'];
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('voti_finali_C')
      ->setDescrizione("Lista dei voti finali per Condotta<br>[lista serializzata]")
      ->setGestito(true)
      ->setValore(serialize($lista));
    $lista = ['min' => 0, 'max' => 10, 'suff' => 6, 'med' => 5,
      'valori' => '0,1,2,3,4,5,6,7,8,9,10',
      'etichette' => '"NC",1,2,3,4,5,6,7,8,9,10',
      'voti' => '"Non Classificato",1,2,3,4,5,6,7,8,9,10',
      'votiAbbr' => '"NC",1,2,3,4,5,6,7,8,9,10'];
    $param[] = (new Configurazione())
      ->setCategoria('SCUOLA')
      ->setParametro('voti_finali_N')
      ->setDescrizione("Lista dei voti finali per le altre materie<br>[lista serializzata]")
      ->setGestito(true)
      ->setValore(serialize($lista));
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
      ->setValore('');
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
      'Test', // dati per i test dell'applicazione
    );
  }

}
