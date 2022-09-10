<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use App\Entity\Notifica;
use App\Entity\NotificaInvio;
use App\Entity\Utente;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Docente;
use App\Entity\Ata;
use App\Util\BachecaUtil;


/**
 * Comando per inviare le notifiche
 *
 * @author Antonello Dessì
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
   * @var MailerInterface $mailer Gestore della spedizione delle email
   */
  private $mailer;

  /**
   * @var BachecaUtil $bac Classe di utilità per le funzioni di gestione della bacheca
   */
  private $bac;

  /**
  * @var LoggerInterface $logger Gestore dei log su file
  */
  private $logger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MailerInterface $mailer Gestore della spedizione delle email
   * @param BachecaUtil $bac Classe di utilità per le funzioni di gestione della bacheca
   * @param LoggerInterface $logger Gestore dei log su file
   */
   public function __construct(EntityManagerInterface $em, TranslatorInterface $trans,
                               MailerInterface  $mailer, BachecaUtil $bac, LoggerInterface $logger) {
    parent::__construct();
    $this->em = $em;
    $this->trans = $trans;
    $this->mailer = $mailer;
    $this->bac = $bac;
    $this->logger = $logger;
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
    $istituto = $this->em->getRepository('App\Entity\Istituto')->findOneBy([]);
    // messaggi con priorità
    $notifiche1 = $this->em->getRepository('App\Entity\NotificaInvio')->createQueryBuilder('n')
      ->where('n.stato=:priorita')
      ->orderBy('n.modificato', 'ASC')
      ->setParameters(['priorita' => 'P'])
      ->getQuery()
      ->getResult();
    // messaggi in attesa
    $notifiche2 = $this->em->getRepository('App\Entity\NotificaInvio')->createQueryBuilder('n')
      ->where('n.stato=:attesa')
      ->orderBy('n.modificato', 'ASC')
      ->setParameters(['attesa' => 'A'])
      ->setMaxResults(count($notifiche1) < 5 ? 25 : (count($notifiche1) < 50 ? 10 : 5))
      ->getQuery()
      ->getResult();
    // invio dei messaggi
    foreach (array_merge($notifiche1, $notifiche2)  as $not) {
      // invia un messaggio alla volta
      if ($not->getApp()->getNotifica() == 'E') {
        // notifica via email
        $num += $this->inviaEmail($not, $istituto);
      }
      // rende permanenti modifiche
      $this->em->flush();
    }
    // restituisce numero messaggi inviati
    return $num;
  }

  /**
   * Utilizza l'email per inviare la notifica
   *
   * @param NotificaInvio $notifica Notifica da inviare
   * @param Istituto $istituto Dati dell'istituto scolastico
   *
   * @return int Numero di messaggi inviati
   */
  private function inviaEmail(NotificaInvio $notifica, $istituto) {
    $errore = false;
    $num = 0;
    $dati = $notifica->getDati();
    // crea il messaggio
    $message = (new Email())
      ->from(new Address($istituto->getEmailNotifiche(), $istituto->getIntestazioneBreve()))
      ->to($dati['email'])
      ->subject($dati['oggetto'])
      ->html($notifica->getMessaggio());
    try {
      // invia email
      $this->mailer->send($message);
      $notifica->setStato('S');
      $num = 1;
    } catch (\Exception $err) {
      // errore di spedizione
      $notifica->setStato('E');
      $dati = $notifica->getDati();
      $dati['errore'] = 'Mailer';
      $notifica->setDati($dati);
      $this->logger->notice('notifica-invia: Errore di spedizione', [$errore_desc]);
    }
    // restituisce messaggi inviati
    return $num;
  }

}
