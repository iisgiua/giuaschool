<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use App\Entity\Utente;
use App\Message\NotificaMessage;
use App\Util\TelegramManager;
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
   * @var TelegramManager $telegram Gestore delle comunicazioni tramite Telegram
   */
  private TelegramManager $telegram;

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
   * @param TelegramManager $telegram Gestore delle comunicazioni tramite Telegram
   * @param LoggerInterface $msgLogger Gestore dei log su file
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $trans,
                              Environment $tpl, MailerInterface $mailer, TelegramManager $telegram,
                              LoggerInterface $msgLogger) {
    $this->em = $em;
    $this->trans = $trans;
    $this->tpl = $tpl;
    $this->mailer = $mailer;
    $this->telegram = $telegram;
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
      $this->logger->notice('NotificaMessage: scarta notifica, utente non abilitato', [$message->getUtenteId()]);
      return;
    }
    // legge dati di notifica dell'utente
    $datiNotifica = $utente->getNotifica();
    if (empty($datiNotifica['abilitato']) ||
        !in_array($message->getTipo(), $datiNotifica['abilitato'], true)) {
      // nessuna notifica: evento notifica non abilitata
      $this->logger->notice('NotificaMessage: scarta notifica, evento notifica non abilitato', [$message->getUtenteId()]);
      return;
    }
    // invia notifica
    try {
      switch ($datiNotifica['tipo']) {
        case 'email':
          // invio per email
          $this->notificaEmail($message, $utente->getEmail());
          break;
        case 'telegram':
          // invio tramite Telegram
          $this->notificaTelegram($message, $utente);
          break;
      }
    } catch (\Throwable $e) {
      // errore
      $this->logger->error('NotificaMessage: ERRORE '.$e->getMessage(), [$e]);
    }
  }

  /**
   * Rimuove da ogni coda le notifiche relative al tag indicato
   * NB: per le circolari, non rimuove le notifiche con raggruppamento di più circolari
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param string $tag Testo usato per identificare la notifica
   */
  public static function delete(EntityManagerInterface $em, string $tag) {
    $connection = $em->getConnection();
    $sql = "DELETE FROM gs_messenger_messages WHERE body LIKE :tag";
    $connection->prepare($sql)->execute(['tag' => '%'.$tag.'%']);
  }

  /**
   * Aggiorna la notifica non ancora inviata modificando solo l'attesa.
   * Se la notifica è ancora nella coda indicata è sufficiente aggiornarla; altrimenti
   * è necessario reinserirla e quindi è bene cancellarla se presente nella coda di invio.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param string $tag Testo usato per identificare la notifica
   * @param string $queue Nome della coda della notifica
   * @param int $delay Nuovo tempo di attesa (in secondi)
   *
   * @return bool Restituisce vero se la notifica è stata aggiornata
   */
  public static function update(EntityManagerInterface $em, string $tag, string $queue, int $delay): bool {
    $ora = (new \DateTime())->modify('+'.$delay.' seconds');
    $connection = $em->getConnection();
    $sql = "UPDATE gs_messenger_messages SET available_at=:ora WHERE queue_name=:queue AND body LIKE :tag AND delivered_at IS NULL";
    $res = $connection->prepare($sql)->execute(['ora' => $ora->format('Y-m-d H:i:s'),
      'queue' => $queue, 'tag' => '%'.$tag.'%']);
    if ($res->rowCount() == 0) {
      // elimina notifica da ogni coda
      $sql = "DELETE FROM gs_messenger_messages WHERE body LIKE :tag";
      $connection->prepare($sql)->execute(['tag' => '%'.$tag.'%']);
    }
    // restituisce vero se notifica aggiornata
    return ($res->rowCount() != 0);
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
    switch ($message->getTipo()) {
      case 'circolare':
        // dati circolare
        $oggetto = $this->trans->trans('message.notifica_circolare_oggetto',
          ['intestazione_istituto_breve' => $istituto->getIntestazioneBreve()]);
        $testo = $this->tpl->render('email/notifica_circolari.html.twig', array(
          'circolari' => $message->getDati(),
          'intestazione_istituto_breve' => $istituto->getIntestazioneBreve(),
          'url_registro' => $istituto->getUrlRegistro()));
        break;
      case 'avviso':
      case 'verifica':
      case 'compito':
        // dati avviso
        $oggetto = $istituto->getIntestazioneBreve().' - '.$message->getDati()['oggetto'];
        $testo = $this->tpl->render('email/notifica_avvisi.html.twig', array(
          'dati' => $message->getDati(),
          'url_registro' => $istituto->getUrlRegistro()));
        break;
      default:
        // errore
        $this->logger->warning('NotificaMessage: evento non previsto per le notifiche via email', [$message->getTipo()]);
        return;
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

  /**
   * Utilizza l'email per inviare la notifica
   *
   * @param NotificaMessage $message Dati per l'invio della notifica
   * @param string $email Indirizzo email del destinatario
   */
  private function notificaTelegram(NotificaMessage $message, Utente $utente): void {
    // legge dati
    $istituto = $this->em->getRepository('App\Entity\Istituto')->findOneBy([]);
    // imposta messaggio
    switch ($message->getTipo()) {
      case 'circolare':
        // dati circolare
        $html = $this->tpl->render('chat/notifica_circolari.html.twig', array(
          'circolari' => $message->getDati(),
          'url_registro' => $istituto->getUrlRegistro()));
        break;
      case 'avviso':
        // dati avviso
        $html = $this->tpl->render('chat/notifica_avvisi.html.twig', array(
          'dati' => $message->getDati(),
          'url_registro' => $istituto->getUrlRegistro()));
        break;
      case 'verifica':
        // dati avviso
        $html = $this->tpl->render('chat/notifica_avvisi.html.twig', array(
          'dati' => $message->getDati(),
          'url_registro' => $istituto->getUrlRegistro()));
        break;
      case 'compito':
        // dati avviso
        $html = $this->tpl->render('chat/notifica_avvisi.html.twig', array(
          'dati' => $message->getDati(),
          'url_registro' => $istituto->getUrlRegistro()));
        break;
      default:
        // errore
        $this->logger->warning('NotificaMessage: evento non previsto per le notifiche via Telegram', [$message->getTipo()]);
        return;
    }
    // invia messaggio
    $ris = $this->telegram->sendMessage($utente, $html);
    if (isset($ris['error'])) {
      // errore invio
      throw new \Exception($ris['error']);
    }
  }

}
