<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\MessageHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;
use DateTime;
use App\Entity\Istituto;
use Exception;
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
#[AsMessageHandler]
class NotificaMessageHandler {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param Environment $tpl Gestione template
   * @param MailerInterface $mailer Gestore della spedizione delle email
   * @param TelegramManager $telegram Gestore delle comunicazioni tramite Telegram
   * @param LoggerInterface $logger Gestore dei log su file
   */
  public function __construct(
      private readonly EntityManagerInterface $em,
      private readonly TranslatorInterface $trans,
      private readonly Environment $tpl,
      private readonly MailerInterface $mailer,
      private readonly TelegramManager $telegram,
      private readonly LoggerInterface $logger)
  {
  }

  /**
   * Invia la notifica
   *
   * @param NotificaMessage $message Dati per l'invio della notifica
   */
  public function __invoke(NotificaMessage $message) {
    // legge dati utente
    $utente = $this->em->getRepository(Utente::class)->findOneBy(['id' => $message->getUtenteId(),
      'abilitato' => 1]);
    if (!$utente) {
      // nessuna notifica: utente non abilitato
      return;
    }
    // legge dati di notifica dell'utente
    $datiNotifica = $utente->getNotifica();
    if (empty($datiNotifica['abilitato']) ||
        !in_array($message->getTipo(), $datiNotifica['abilitato'], true)) {
      // nessuna notifica: evento notifica non abilitato
      return;
    }
    // invia notifica
    try {
      switch ($datiNotifica['tipo']) {
        case 'email':
          // invio per email
          $address = $utente->getEmail();
          if (!empty($address) && !str_ends_with($address, '.local')) {
            // invia
            $this->notificaEmail($message, $utente);
          }
          break;
        case 'telegram':
          // invio tramite Telegram
          if (!empty($datiNotifica['telegram_chat'])) {
            // invia
            $this->notificaTelegram($message, $datiNotifica['telegram_chat']);
          }
          break;
        default:
          // errore
          $this->logger->warning('NotificaMessage: canale non previsto', [$datiNotifica['tipo']]);
          return;
      }
    } catch (Throwable $e) {
      // errore
      $this->logger->error('NotificaMessage: ERRORE '.$e->getMessage(), [
        $datiNotifica['tipo'] == 'email' ? $utente->getEmail() : $datiNotifica['telegram_chat']]);
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
    $connection->prepare($sql)->executeStatement(['tag' => '%'.$tag.'%']);
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
    $ora = (new DateTime())->modify('+'.$delay.' seconds');
    $connection = $em->getConnection();
    $sql = "UPDATE gs_messenger_messages SET available_at=:ora WHERE queue_name=:queue AND body LIKE :tag AND delivered_at IS NULL";
    $res = $connection->prepare($sql)->executeStatement(['ora' => $ora->format('Y-m-d H:i:s'),
      'queue' => $queue, 'tag' => '%'.$tag.'%']);
    if ($res == 0) {
      // elimina notifica da ogni coda
      $sql = "DELETE FROM gs_messenger_messages WHERE body LIKE :tag";
      $connection->prepare($sql)->executeStatement(['tag' => '%'.$tag.'%']);
    }
    // restituisce vero se notifica aggiornata
    return ($res != 0);
  }


  //==================== METODI PRIVATI  ====================

  /**
   * Utilizza l'email per inviare la notifica
   *
   * @param NotificaMessage $message Dati per l'invio della notifica
   * @param Utente $utente Utente destinatario dell'email
   */
  private function notificaEmail(NotificaMessage $message, Utente $utente): void {
    // legge dati per il mittente
    $istituto = $this->em->getRepository(Istituto::class)->findOneBy([]);
    // imposta messaggio
    switch ($message->getTipo()) {
      case 'circolare':
        // dati circolare
        $oggetto = $this->trans->trans('message.notifica_circolare_oggetto',
          ['intestazione_istituto_breve' => $istituto->getIntestazioneBreve()]);
        $testo = $this->tpl->render('email/notifica_circolari.html.twig', [
          'circolari' => $message->getDati(),
          'intestazione_istituto_breve' => $istituto->getIntestazioneBreve(),
          'url_registro' => $istituto->getUrlRegistro()]);
        break;
      case 'avviso':
      case 'verifica':
      case 'compito':
        // dati avviso
        $oggetto = $istituto->getIntestazioneBreve().' - '.$message->getDati()['oggetto'];
        $testo = $this->tpl->render('email/notifica_avvisi.html.twig', [
          'dati' => $message->getDati(),
          'url_registro' => $istituto->getUrlRegistro()]);
        break;
      default:
        // errore
        $this->logger->warning('NotificaMessage: evento non previsto per le notifiche via email', [$message->getTipo()]);
        return;
    }
    // crea il messaggio
    $msg = (new Email())
      ->from(new Address($istituto->getEmailNotifiche(), $istituto->getIntestazioneBreve()))
      ->to(new Address($utente->getEmail(), $utente->getNome().' '.$utente->getCognome()))
      ->subject($oggetto)
      ->html($testo);
    // invia email
    $this->mailer->send($msg);
    $this->logger->debug('NotificaMessage: evento notificato via email', [$message, $utente->getEmail()]);
  }

  /**
   * Utilizza l'email per inviare la notifica
   *
   * @param NotificaMessage $message Dati per l'invio della notifica
   * @param string $chat Identificativo della chat Telegram
   */
  private function notificaTelegram(NotificaMessage $message, string $chat): void {
    // legge dati
    $istituto = $this->em->getRepository(Istituto::class)->findOneBy([]);
    // imposta messaggio
    switch ($message->getTipo()) {
      case 'circolare':
        // dati circolare
        $html = $this->tpl->render('chat/notifica_circolari.html.twig', [
          'circolari' => $message->getDati(),
          'url_registro' => $istituto->getUrlRegistro()]);
        break;
      case 'avviso':
      case 'verifica':
      case 'compito':
        // dati avviso/verifica/compito
        $html = $this->tpl->render('chat/notifica_avvisi.html.twig', [
          'dati' => $message->getDati(),
          'url_registro' => $istituto->getUrlRegistro()]);
        break;
      default:
        // errore
        $this->logger->warning('NotificaMessage: evento non previsto per le notifiche via Telegram', [$message->getTipo()]);
        return;
    }
    // invia messaggio
    $ris = $this->telegram->sendMessage($chat, $html);
    if (isset($ris['error'])) {
      // errore invio
      throw new Exception($ris['error']);
    }
    $this->logger->debug('NotificaMessage: evento notificato via Telegram', [$message, $chat]);
  }

}
