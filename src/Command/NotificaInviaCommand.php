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
 * Comando per inviare le notifiche
 */
class NotificaInviaCommand extends Command {


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
   * @var Swift_Mailer $mailer Gestore della spedizione delle email
   */
  private $mailer;

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
   * @param \Swift_Mailer $mailer Gestore della spedizione delle email
   * @param BachecaUtil $bac Classe di utilità per le funzioni di gestione della bacheca
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param LoggerInterface $logger Gestore dei log su file
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans, SessionInterface $session, \Swift_Mailer $mailer,
                               BachecaUtil $bac, ConfigLoader $config, LoggerInterface $logger) {
    parent::__construct();
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
    $this->mailer = $mailer;
    $this->bac = $bac;
    $this->config = $config;
    $this->logger = $logger;
    // carica configurazione
    $this->config->loadAll();
  }

  /**
   * Configura la sintassi del comando
   *
   */
  protected function configure() {
    // nome del comando (da inserire dopo "php bin/console")
    $this->setName('app:notifica:invia');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Invia le notifiche');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando esegue l'invio dei messaggi di notifica.");
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
    // inizio
    $this->logger->notice('notifica-invia: Inizio procedura di notifica');
    // invia messaggi
    $num = $this->inviaMessaggi();
    $this->logger->notice('notifica-invia: Invio dei messaggi eseguito', ['num' => $num]);
    // ok, fine
    $this->logger->notice('notifica-invia: Fine procedura di notifica');
    return 0;
  }


  //==================== FUNZIONI PRIVATE  ====================

  /**
   * Invia i messaggi di notifica
   *
   * @return int Numero di messaggi inviati
   */
  private function inviaMessaggi() {
    // inizializza
    $num = 0;
    // messaggi con priorità
    $notifiche1 = $this->em->getRepository('App:NotificaInvio')->createQueryBuilder('n')
      ->where('n.stato=:priorita')
      ->orderBy('n.modificato', 'ASC')
      ->setParameters(['priorita' => 'P'])
      ->getQuery()
      ->getResult();
    // messaggi in attesa
    $notifiche2 = $this->em->getRepository('App:NotificaInvio')->createQueryBuilder('n')
      ->where('n.stato=:attesa')
      ->orderBy('n.modificato', 'ASC')
      ->setParameters(['attesa' => 'A'])
      ->setMaxResults(count($notifiche1) < 5 ? 25 : (count($notifiche1) < 50 ? 10 : 5))
      ->getQuery()
      ->getResult();
    // invio dei messaggi
    foreach (array_merge($notifiche1, $notifiche2)  as $not) {
      // invia un messaggio alla volta
      if ($not->getApp()->getNotifica() == 'T') {
        // notifica via Telegram
        $num += $this->inviaTelegram($not);
      } elseif ($not->getApp()->getNotifica() == 'E') {
        // notifica via email
        $num += $this->inviaEmail($not);
      }
      // rende permanenti modifiche
      $this->em->flush();
    }
    // restituisce numero messaggi inviati
    return $num;
  }

  /**
   * Utilizza Telegram per inviare la notifica
   *
   * @param NotificaInvio $notifica Notifica da inviare
   *
   * @return int Numero di messaggi inviati
   */
  private function inviaTelegram(NotificaInvio $notifica) {
    $telegram_opts = array(
      CURLOPT_URL => '',
      CURLOPT_POSTFIELDS => '',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HEADER => false,
      CURLOPT_HTTPHEADER => ['Content-Type' => 'application/x-www-form-urlencoded', 'charset' => 'utf-8'],
      CURLOPT_CONNECTTIMEOUT => 20,
      CURLOPT_TIMEOUT => 30);
    $errore = false;
    // invia il messaggio
    $telegram_opts[CURLOPT_URL] = 'https://api.telegram.org/bot'.$notifica->getApp()->getDati()['bot'].'/sendMessage';
    $telegram_opts[CURLOPT_POSTFIELDS] = http_build_query(array(
      'chat_id' => $notifica->getDati()['chat'],
      'text' => $notifica->getMessaggio(),
      'parse_mode' => 'HTML'), null, '&');
    $cu = \curl_init();
    $errore = !\curl_setopt_array($cu, $telegram_opts);
    if (!$errore) {
      // esegue chiamata per invio
      $risposta = json_decode(\curl_exec($cu), true);
      $errore = (!isset($risposta['ok']) || !$risposta['ok']);
      if ($errore) {
        // setta errore telegram
        $errore_desc = (isset($risposta['description']) ? $risposta['description'] : 'Telegram');
      }
    } else {
      // setta errore CURL
      $errore_desc = 'CURL';
    }
    \curl_close($cu);
    // cambia stato
    if ($errore) {
      // segnala errore
      $notifica->setStato('E');
      $dati = $notifica->getDati();
      $dati['errore'] = $errore_desc;
      $notifica->setDati($dati);
      $this->logger->notice('notifica-invia: Errore di spedizione', [$errore_desc]);
    } else {
      // tutto ok
      $notifica->setStato('S');
    }
    // restituisce messaggi inviati
    return ($errore ? 0 : 1);
  }

  /**
   * Utilizza l'email per inviare la notifica
   *
   * @param NotificaInvio $notifica Notifica da inviare
   *
   * @return int Numero di messaggi inviati
   */
  private function inviaEmail(NotificaInvio $notifica) {
    $errore = false;
    $num = 0;
    $dati = $notifica->getDati();
    // crea il messaggio
    $message = (new \Swift_Message())
      ->setSubject($dati['oggetto'])
      ->setFrom([$this->session->get('/CONFIG/ISTITUTO/email_notifiche') => $this->session->get('/CONFIG/ISTITUTO/intestazione_breve')])
      ->setTo([$dati['email']])
      ->setBody($notifica->getMessaggio(), 'text/html');
    // invia mail
    if (!$this->mailer->send($message)) {
      // errore di spedizione
      $notifica->setStato('E');
      $dati = $notifica->getDati();
      $dati['errore'] = 'Swift Mailer';
      $notifica->setDati($dati);
      $this->logger->notice('notifica-invia: Errore di spedizione', [$errore_desc]);
    } else {
      // tutto ok
      $notifica->setStato('S');
      $num = 1;
    }
    // restituisce messaggi inviati
    return $num;
  }

}
