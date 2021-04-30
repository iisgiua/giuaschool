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


namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Notifica;
use App\Entity\NotificaInvio;
use App\Entity\Utente;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Docente;
use App\Entity\Ata;
use App\Util\BachecaUtil;
use App\Util\ConfigLoader;


/**
 * Comando per preparare i messaggi delle notifiche
 */
class NotificaPreparaCommand extends Command {


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
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;

  /**
   * @var \Twig\Environment $tpl Gestione template
   */
  private $tpl;

  /**
   * @var BachecaUtil $bac Classe di utilità per le funzioni di gestione della bacheca
   */
  private $bac;

  /**
   * @var ConfigLoader $config Gestore della configurazione su database
   */
  private $config;

  /**
   * @var LoggerInterface $logger Gestore dei log su file
   */
  private $logger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   * @param \Twig\Environment $tpl Gestione template
   * @param BachecaUtil $bac Classe di utilità per le funzioni di gestione della bacheca
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param LoggerInterface $logger Gestore dei log su file
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans,  SessionInterface $session,
                              \Twig\Environment $tpl, BachecaUtil $bac, ConfigLoader $config, LoggerInterface $logger) {
    parent::__construct();
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
    $this->tpl = $tpl;
    $this->bac = $bac;
    $this->config = $config;
    $this->logger = $logger;
  }

  /**
   * Configura la sintassi del comando
   *
   */
  protected function configure() {
    // nome del comando (da inserire dopo "php bin/console")
    $this->setName('app:notifica:prepara');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Prepara le notifiche');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando crea i messaggi che verranno inviati come notifica.");
    // argomenti del comando
    // .. nessuno
  }

  /**
   * Usato per inizializzare le variabili prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
  }

  /**
   * Usato per validare gli argomenti prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
  }

  /**
   * Esegue il comando
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   *
   * @return null|int Restituisce un valore nullo o 0 se tutto ok, altrimenti un codice di errore come numero intero
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // carica configurazione
    $this->config->carica();
    // inizio
    $this->logger->notice('notifica-prepara: Inizio procedura di preparazione delle notifiche');
    // recupero notifiche
    $dati = $this->leggeNotifiche();
    $num = count($dati, COUNT_RECURSIVE) - count($dati);
    $this->logger->notice('notifica-prepara: Lettura delle azioni di notifica eseguita', ['num' => $num]);
    // crea messaggi per notifiche
    $num = $this->creaMessaggi($dati);
    $this->logger->notice('notifica-prepara: Creazione dei messaggi eseguita', ['num' => $num]);
    // cancella vecchi messaggi
    $num = $this->cancellaMessaggi();
    $this->logger->notice('notifica-prepara: Cancellazione dei vecchi messaggi eseguita', ['num' => $num]);
    // ok, fine
    $this->logger->notice('notifica-prepara: Fine procedura di preparazione delle notifiche');
    return 0;
  }


  //==================== FUNZIONI PRIVATE  ====================

  /**
   * Restituisce la lista degli oggetti da notificare
   *
   * @return array Dati formattati come un array associativo
   */
  private function leggeNotifiche() {
    $dati = array();
    // legge notifiche tranne quelle degli ultimi 15 minuti
    $limite = (new \DateTime())->modify('-15 min');
    $notifiche = $this->em->getRepository('App:Notifica')->createQueryBuilder('n')
      ->where('n.modificato<:limite')
      ->orderBy('n.modificato', 'ASC')
      ->setParameters(['limite' => $limite->format('Y-m-d H:i:s')])
      ->getQuery()
      ->getResult();
    $nuovi = array();
    foreach ($notifiche as $n) {
      if (isset($dati[$n->getOggettoNome()][$n->getOggettoId()])) {
        // cancella precedente notifica
        $this->em->remove($dati[$n->getOggettoNome()][$n->getOggettoId()]);
      }
      $dati[$n->getOggettoNome()][$n->getOggettoId()] = $n;
      if ($n->getAzione() == 'A') {
        // oggetto creato
        $nuovi[$n->getOggettoNome()][$n->getOggettoId()] = 1;
      } elseif ($n->getAzione() == 'D' && isset($nuovi[$n->getOggettoNome()][$n->getOggettoId()])) {
        // oggetto creato e poi rimosso (nessuna notifica)
        unset($dati[$n->getOggettoNome()][$n->getOggettoId()]);
        $this->em->remove($n);
      }
    }
    // legge le notifiche modificate negli ultimi 15 minuti
    $notifiche = $this->em->getRepository('App:Notifica')->createQueryBuilder('n')
      ->where('n.modificato>=:limite')
      ->orderBy('n.modificato', 'ASC')
      ->setParameters(['limite' => $limite->format('Y-m-d H:i:s')])
      ->getQuery()
      ->getResult();
    foreach ($notifiche as $n) {
      // considera solo le notifiche modificate
      if (isset($dati[$n->getOggettoNome()][$n->getOggettoId()])) {
        // cancella precedente notifica
        $this->em->remove($dati[$n->getOggettoNome()][$n->getOggettoId()]);
        $dati[$n->getOggettoNome()][$n->getOggettoId()] = $n;
        if ($n->getAzione() == 'D' && isset($nuovi[$n->getOggettoNome()][$n->getOggettoId()])) {
          // oggetto creato e poi rimosso (nessuna notifica)
          unset($dati[$n->getOggettoNome()][$n->getOggettoId()]);
          $this->em->remove($n);
        }
      }
    }
    // rende permanenti le modifiche
    $this->em->flush();
    // restituisce dati
    return $dati;
  }

  /**
   * Crea i messaggi per le notifiche
   *
   * @param array $dati Dati formattati come un array associativo
   *
   * @return int Numero di messaggi creati
   */
  private function creaMessaggi($dati) {
    $num = 0;
    // controlla se è attiva la notifica delle circolari
    $notifica_circolari = $this->em->getRepository('App:Configurazione')->getParametro('notifica_circolari', []);
    $ora_notifica = explode(',', $notifica_circolari);
    $adesso = new \DateTime();
    $attiva_notifica_circolari = in_array($adesso->format('H'), $ora_notifica, true);
    // scansione notifiche
    foreach ($dati as $ogg=>$lista) {
      if ($ogg != 'Circolare') {
        // esclude circolari
        foreach ($lista as $id=>$notifica) {
          // esegue azioni diverse per ogni oggetto
          $func = 'creaMessaggio'.$ogg;
          $num += $this->$func($id, $notifica);
        }
      } elseif ($attiva_notifica_circolari) {
        // circolari in orario di notifica
        $destinatari = array();
        foreach ($lista as $id=>$notifica) {
          // crea messaggi per circolare
          $this->creaMessaggioCircolare($id, $notifica, $destinatari);
        }
        // raggruppa messaggi per destinatari
        foreach ($destinatari as $id=>$circ) {
          // notifica abilitata per l'utente
          $utente = $circ[0]['utente'];
          $tipo = ($utente instanceof Alunno ? 'A' : ($utente instanceof Genitore ? 'G' :
            ($utente instanceof Docente ? 'D' : ($utente instanceof Ata ? 'T' : ''))));
          $dati_notifica = $utente->getNotifica();
          $app = null;
          if (empty($dati_notifica) && strpos('DT', $tipo) !== false) {
            // forza invio via email per docenti/ata
            $app = $this->em->getRepository('App:App')->findOneBy(['notifica' => 'E',
              'abilitati' => 'DT', 'attiva' => 1]);
          } elseif (!empty($dati_notifica)) {
            // legge app di notifica
            $app = $this->em->getRepository('App:App')->findOneBy(['id' => $dati_notifica['app'],
              'attiva' => 1]);
          }
          if ($app && $app->getNotifica() != 'N' && $tipo && strpos($app->getAbilitati(), $tipo) !== false) {
            $stato = 'A';
            // crea messaggio
            if ($app->getNotifica() == 'E') {
              // notifica via email
              $testo = $this->tpl->render('email/notifica_circolari.html.twig', array(
                'circolari' => $circ,
                'intestazione_istituto_breve' => $this->session->get('/CONFIG/ISTITUTO/intestazione_breve'),
                'url_registro' => $this->session->get('/CONFIG/ISTITUTO/url_registro'),
                'email_amministratore' => $this->session->get('/CONFIG/ISTITUTO/email_amministratore')));
              // aggiunge dati
              $dati_notifica['oggetto'] = $this->trans->trans('message.notifica_circolare_oggetto', [
                'intestazione_istituto_breve' => $this->session->get('/CONFIG/ISTITUTO/intestazione_breve')]);
              $dati_notifica['email'] = $utente->getEmail();
              // imposta la precedenza su altri messaggi
              $stato = 'P';
            } else {
              // notifica tramite messaggio
              $testo = $this->trans->trans('message.presenti_nuove_circolari', ['num' => count($circ)]);
              $testo = htmlspecialchars(str_replace(["\r", "\n\n"], ["\n", "\n"], $testo));
            }
            // crea notifica per l'invio
            $notifica_invio = (new NotificaInvio())
              ->setStato($stato)
              ->setMessaggio($testo)
              ->setApp($app)
              ->setDati($dati_notifica);
            $this->em->persist($notifica_invio);
            $num++;
          }
        }
        // rende permanenti modifiche
        $this->em->flush();
      }
    }
    // restituisce numero messaggi
    return $num;
  }

  /**
   * Crea il messaggio per le notifiche sugli avvisi
   *
   * @param int $id ID dell'avviso
   * @param Notifica $notifica Notifica da cui creare il messaggio
   *
   * @return int Numero di messaggi creati
   */
  private function creaMessaggioAvviso($id, $notifica) {
    $num = 0;
    if ($notifica->getAzione() != 'D') {
      // notifica solo nuovo avviso (anche su modifica)
      $avviso = $this->em->getRepository('App:Avviso')->find($id);
      if ($avviso) {
        // solo avvisi esistenti
        $filtri = $this->bac->filtriAvviso($avviso);
        $destinatari = array();
        if ($avviso->getTipo() == 'V' || $avviso->getTipo() == 'P') {
          // destinatari per verifiche o compiti
          $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
            ->where('a.notifica IS NOT NULL AND (a.classe IN (:classi) OR a.id IN (:utenti))')
            ->setParameters(['classi' => array_column($filtri['classi'], 'classe'),
              'utenti' => array_column($filtri['utenti'], 'alunno')])
            ->getQuery()
            ->getResult();
          $destinatari = array_merge($destinatari, $alunni);
        } else {
          // destinatari per altri tipi di avvisi
          //-- if ($avviso->getDestinatariAlunni()) {
            //-- // alunni destinatari
            //-- $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
              //-- ->where('a.notifica IS NOT NULL AND a.classe IN (:classi)')
              //-- ->setParameters(['classi' => array_column($filtri['classi'], 'classe')])
              //-- ->getQuery()
              //-- ->getResult();
            //-- $destinatari = array_merge($destinatari, $alunni);
          //-- }
        }
        // testo avviso
        switch ($avviso->getTipo()) {
          case 'C':   // generico
            $testo = $this->trans->trans('message.notifica_generica', [
              'data' => $avviso->getData()->format('d/m/Y'),
              'testo' => $avviso->getTesto()
              ]);
            break;
          case 'E':   // entrata posticipata
          case 'U':   // uscita anticipata
            $data = $avviso->getData()->format('d/m/Y');
            $ora = ($avviso->getOra() ? $avviso->getOra()->format('G:i') : '');
            $ora2 = ($avviso->getOraFine() ? $avviso->getOraFine()->format('G:i') : '');
            $testo = str_replace(['{DATA}', '{ORA}'], [$data, $ora], $avviso->getTesto());
            break;
          case 'A':   // attività
            $data = $avviso->getData()->format('d/m/Y');
            $ora = ($avviso->getOra() ? $avviso->getOra()->format('G:i') : '');
            $ora2 = ($avviso->getOraFine() ? $avviso->getOraFine()->format('G:i') : '');
            $testo = str_replace(['{DATA}', '{INIZIO}', '{FINE}'], [$data, $ora, $ora2], $avviso->getTesto());
            break;
          case 'V':   // verifiche
            $testo = $this->trans->trans('message.notifica_verifica', [
              'materia' => $avviso->getCattedra()->getMateria()->getNomeBreve(),
              'data' => $avviso->getData()->format('d/m/Y')
              ]);
            break;
          case 'P':   // compiti
            $testo = $this->trans->trans('message.notifica_compito', [
              'materia' => $avviso->getCattedra()->getMateria()->getNomeBreve(),
              'data' => $avviso->getData()->format('d/m/Y')
              ]);
            break;
          default:    // ALTRO
            $testo = '';
            break;
        }
        // crea messaggi
        foreach ($destinatari as $dest) {
          // crea messaggi per l'invio
          $num += $this->creaNotificaInvio($testo, $dest);
        }
      }
    }
    // elimina notifica
    $this->em->remove($notifica);
    $this->em->flush();
    // restituisce numero messaggi
    return $num;
  }

  /**
   * Crea il messaggio per le notifiche sulle valutazioni
   *
   * @param int $id ID dell'avviso
   * @param Notifica $notifica Notifica da cui creare il messaggio
   *
   * @return int Numero di messaggi creati
   */
  private function creaMessaggioValutazione($id, $notifica) {
    $num = 0;
    if ($notifica->getAzione() != 'D') {
      // notifica solo nuovo voto (anche su modifica)
      $valutazione = $this->em->getRepository('App:Valutazione')->find($id);
      if ($valutazione && $valutazione->getVisibile()) {
        // solo valutazioni esistenti e visibili
        $destinatari = array($valutazione->getAlunno());
        // controlla destinatari
        foreach ($destinatari as $dest) {
          if (!empty($dest->getNotifica())) {
            // solo destinatari con notifica attivata
            $voto = '';
            if ($valutazione->getVoto()) {
              $voto_int = (int) ($valutazione->getVoto() + 0.25);
              $voto_dec = $valutazione->getVoto() - (int) ($valutazione->getVoto());
              $voto = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
            }
            $testo = $this->trans->trans('message.notifica_valutazione', [
              'tipo' => $this->trans->trans('label.valutazione_'.$valutazione->getTipo()),
              'materia' => $valutazione->getLezione()->getMateria()->getNomeBreve(),
              'data' => $valutazione->getLezione()->getData()->format('d/m/Y'),
              'voto' => $voto,
              'giudizio' => $valutazione->getGiudizio()
              ]);
            $num += $this->creaNotificaInvio($testo, $dest);
          }
        }
      }
    }
    // elimina notifica
    $this->em->remove($notifica);
    $this->em->flush();
    // restituisce numero messaggi
    return $num;
  }

  /**
   * Crea il messaggio per le notifiche di benvenuto
   *
   * @param int $id ID dell'avviso
   * @param Notifica $notifica Notifica da cui creare il messaggio
   *
   * @return int Numero di messaggi creati
   */
  private function creaMessaggioUtente($id, $notifica) {
    $num = 0;
    if ($notifica->getAzione() != 'D') {
      // notifica solo nuovo utente
      $utente = $this->em->getRepository('App:Utente')->find($id);
      if ($utente && $utente->getAbilitato()) {
        // solo utenti esistenti e abilitati
        $destinatari = array($utente);
        // controlla destinatari
        foreach ($destinatari as $dest) {
          if (!empty($dest->getNotifica())) {
            // solo destinatari con notifica attivata
            $testo = $this->trans->trans('message.notifica_registrazione');
            $num += $this->creaNotificaInvio($testo, $dest);
          }
        }
      }
    }
    // elimina notifica
    $this->em->remove($notifica);
    $this->em->flush();
    // restituisce numero messaggi
    return $num;
  }

  /**
   * Crea il messaggio per le notifiche sulle circolari
   *
   * @param int $id ID della circolare
   * @param Notifica $notifica Notifica da cui creare il messaggio
   * @param array $destinatari Lista dei destinatari e delle loro circolari
   */
  private function creaMessaggioCircolare($id, $notifica, &$destinatari) {
    if ($notifica->getAzione() != 'D') {
      // notifica solo nuova circolare
      $circolare = $this->em->getRepository('App:Circolare')->findOneBy(['id' => $id, 'pubblicata' => 1]);
      if ($circolare) {
        // solo circolari esistenti e pubblicate
        $utenti = $this->em->getRepository('App:Circolare')->notifica($circolare);
        foreach ($utenti as $u) {
          // memorizza circolari per utente
          $utente = $this->em->getRepository('App:Utente')->findOneBy(['id' => $u, 'abilitato' => 1]);
          if ($utente && ($circolare->getNotifica() || !empty($utente->getNotifica()))) {
            // solo utenti a cui notificare
            $destinatari[$u][] = array('utente' => $utente, 'numero' => $circolare->getNumero(),
              'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto());
          }
        }
      }
    }
    // elimina notifica
    $this->em->remove($notifica);
  }

  /**
   * Predispone la notifica per l'invio
   *
   * @param string $testo Testo del messaggio da inviare
   * @param Utente $utente Utente a cui inviare il messaggio
   *
   * @return int Numero di messaggi creati
   */
  private function creaNotificaInvio($testo, Utente $utente) {
    $num = 0;
    // legge app
    $dati = $utente->getNotifica();
    $tipo = ($utente instanceof Alunno ? 'A' : ($utente instanceof Genitore ? 'G' :
      ($utente instanceof Docente ? 'D' : ($utente instanceof Ata ? 'T' : ''))));
    $app = null;
    if (isset($dati['app'])) {
      $app = $this->em->getRepository('App:App')->findOneBy(['id' => $dati['app'], 'attiva' => 1]);
    }
    if ($app && $app->getNotifica() != 'N' && $tipo && strpos($app->getAbilitati(), $tipo) !== false) {
      // crea notifica per l'invio
      if (!isset($dati['priority'])) {
        // priorità predefinata: 50 (max 100)
        $dati['priority'] = 50;
      }
      $notifica_invio = (new NotificaInvio())
        ->setStato('A')
        ->setMessaggio(htmlspecialchars(str_replace(["\r", "\n\n"], ["\n", "\n"], $testo)))
        ->setApp($app)
        ->setDati($dati);
      $this->em->persist($notifica_invio);
      $num = 1;
    }
    // restituisce numero messaggi
    return $num;
  }

  /**
   * Cancella i messaggi spediti da molto tempo
   *
   * @return int Numero di messaggi cancellati
   */
  private function cancellaMessaggi() {
    // limite di 10 giorni
    $limite = (new \DateTime())->modify('-10 days');
    // cancella messaggi
    $num = $this->em->createQueryBuilder()
      ->delete('App:NotificaInvio', 'n')
      ->where('n.modificato<:limite AND n.stato=:spedito')
      ->setParameters(['limite' => $limite->format('Y-m-d H:i:s'), 'spedito' => 'S'])
      ->getQuery()
      ->execute();
    // restituisce numero messaggi
    return $num;
  }

}
