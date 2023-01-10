<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use App\Message\NotificaMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;


/**
 * NotificaMessageHandler - gestione dell'invio delle notifiche
 *
 * @author Antonello Dessì
 */
class NotificaMessageHandler implements MessageHandlerInterface {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private EntityManagerInterface $em;

  /**
   * @var TranslatorInterface $trans Gestore delle traduzioni
   */
  private TranslatorInterface $trans;

  /**
   * @var Environment $tpl Gestione template
   */
  private Environment $tpl;

  /**
   * @var MailerInterface $mailer Gestore della spedizione delle email
   */
  private MailerInterface $mailer;

  /**
   * @var LoggerInterface $logger Gestore dei log su file
   */
  private LoggerInterface $logger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param Environment $tpl Gestione template
   * @param MailerInterface $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans,
                              Environment $tpl, MailerInterface $mailer, LoggerInterface $msgLogger) {
    $this->em = $em;
    $this->trans = $trans;
    $this->tpl = $tpl;
    $this->mailer = $mailer;
    $this->logger = $msgLogger;
  }

  /**
   * Invia la notifica
   *
   * @param NotificaMessage $message Dati per l'invio della notifica
   */
  public function __invoke(NotificaMessage $message) {
    // legge dati utente
    $utente = $this->em->getRepository('App\Entity\Utente')->findOneBy(['id' => $message->getUtenteId(),
      'abilitato' => 1]);
    if (!$utente) {
      // nessuna notifica: utente non abilitato
      $this->logger->notice('NotificaMessage: scarta notifica, utente non abilitato', [$message]);
      return;
    }
    // legge dati di notifica dell'utente
    $datiNotifica = $utente->getNotifica();
    if (!empty($datiNotifica['abilitato']) &&
        !in_array($message->getTipo(), $datiNotifica['abilitato'], true)) {
      // nessuna notifica: tipo notifica non abilitata
      $this->logger->notice('NotificaMessage: scarta notifica, tipo notifica non abilitato', [$message]);
      return;
    }
    // invia notifica
    try {
      if (empty($datiNotifica['tipo']) || $datiNotifica['tipo'] == 'email') {
        // invio per email
        $this->notificaEmail($message, $utente->getEmail());
      }
    } catch (\Throwable $e) {
      // errore
      $this->logger->error('NotificaMessage: ERRORE '.$e->getMessage(), [$e]);
    }
  }


  //==================== METODI PRIVATI  ====================

  /**
   * Utilizza l'email per inviare la notifica
   *
   * @param NotificaMessage $message Dati per l'invio della notifica
   * @param string $email Indirizzo email del destinatario
   */
  private function notificaEmail(NotificaMessage $message, string $email): void {
    // legge dati per il mittente
    $istituto = $this->em->getRepository('App\Entity\Istituto')->findOneBy([]);
    // imposta messaggio
    if ($message->getTipo() == 'circolare') {
      // imposta oggetto
      $oggetto = $this->trans->trans('message.notifica_circolare_oggetto',
        ['intestazione_istituto_breve' => $istituto->getIntestazioneBreve()]);
      // imposta testo
      $testo = $this->tpl->render('email/notifica_circolari.html.twig', array(
        'circolari' => $message->getDati(),
        'intestazione_istituto_breve' => $istituto->getIntestazioneBreve(),
        'url_registro' => $istituto->getUrlRegistro()));
    }
    // crea il messaggio
    $msg = (new Email())
      ->from(new Address($istituto->getEmailNotifiche(), $istituto->getIntestazioneBreve()))
      ->to($email)
      ->subject($oggetto)
      ->html($testo);
    // invia email
    $this->mailer->send($msg);
  }

}
