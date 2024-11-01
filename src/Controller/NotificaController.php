<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use DateTime;
use App\Entity\Configurazione;
use App\Entity\Utente;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * NotificaController - gestione configurazione canali di notifica
 *
 * @author Antonello Dessì
 */
class NotificaController extends BaseController {

  /**
   * WebHook per Telegram: gestisce risposte utente
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LoggerInterface $logger Gestore dei log su file
   *
   * @return JsonResponse Dati di risposta
   */
  #[Route(path: '/notifica/telegram/', name: 'notifica_telegram', methods: ['POST'])]
  public function telegram(Request $request, TranslatorInterface $trans,
                           LoggerInterface $logger): JsonResponse {
    // init
    $risposta = [];
    // assicura che lo script non sia interrotto
    ignore_user_abort(true);
    ini_set('max_execution_time', 0);
    // controlla modalità manutenzione
    $ora = (new DateTime())->format('Y-m-d H:i');
    $inizio = $this->em->getRepository(Configurazione::class)->getParametro('manutenzione_inizio', '9999-99-99 99:99');
    $fine = $this->em->getRepository(Configurazione::class)->getParametro('manutenzione_fine', '0000-00-00 00:00');
    if ($ora >= $inizio && $ora <= $fine) {
      // errore: modalità manutenzione (evita che messaggio sia processato)
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge richiesta
    $richiesta = [];
    if ($contenuto = $request->getContent()) {
      // decodifica
      $richiesta = json_decode($contenuto, true);
    }
    $secretHeader = $request->headers->get('X-Telegram-Bot-Api-Secret-Token');
    $secret = $this->em->getRepository(Configurazione::class)->getParametro('telegram_secret');
    if ($secretHeader != $secret) {
      // errore: violazione di sicurezza
      $logger->error('Telegram webhook: violazione di sicurezza su codice X-Telegram-Bot-Api-Secret-Token.',
        [$secretHeader, $richiesta]);
      return new JsonResponse($risposta);
    }
    if (isset($richiesta['message']) && str_starts_with((string) $richiesta['message']['text'], '/start ')) {
      // registrazione utente
      $scadenza = (new DateTime())->modify('-5 minute');
      $data = DateTime::createFromFormat('U', $richiesta['message']['date']);
      if ($data < $scadenza) {
        // errore: messaggio scaduto
        $logger->error('Telegram webhook: scarta messaggio scaduto.', [$richiesta]);
        return new JsonResponse($risposta);
      }
      $token = base64_decode(trim(substr((string) $richiesta['message']['text'], 7)));
      $tokenData = explode('#', $token);
      $utente = $this->em->getRepository(Utente::class)->findOneBy(['username' => $tokenData[1] ?? '',
        'abilitato' => 1, 'token' => $tokenData[0]]);
      if (!$utente || $utente->getTokenCreato() < $scadenza) {
        // errore: token invalido o scaduto
        $logger->error('Telegram webhook: registrazione con token invalido o scaduto.', [$richiesta]);
        return new JsonResponse($risposta);
      }
      $notifica = $utente->getNotifica();
      if ($notifica['tipo'] != 'telegram') {
        // errore: configurazione notifica
        $logger->error('Telegram webhook: registrazione per notifiche con configurazione invalida.',
          [$notifica, $richiesta]);
        return new JsonResponse($risposta);
      }
      // dati chat
      $utente->cancellaToken();
      $notifica['telegram_chat'] = $richiesta['message']['chat']['id'];
      $utente->setNotifica($notifica);
      // memorizzazione
      $this->em->flush();
      // log
      $logger->warning('Telegram webhook: registrazione utente.', [$utente->getUsername(), $richiesta]);
      // messaggio di risposta
      $risposta['method'] = 'sendMessage';
      $risposta['chat_id'] = $richiesta['message']['chat']['id'];
      $risposta['text'] = $trans->trans('message.telegram_chat_start', ['nome' => $utente->getNome()]);
      $risposta['parse_mode'] = 'HTML';
    } elseif (isset($richiesta['my_chat_member']) &&
              $richiesta['my_chat_member']['new_chat_member']['status'] == 'kicked') {
      // elimina chat utente
      $chat = $richiesta['my_chat_member']['chat']['id'];
      $utente = $this->em->getRepository(Utente::class)->createQueryBuilder('u')
        ->where('u.notifica LIKE :chat')
        ->setParameter('chat', '%s:13:"telegram\_chat";s:'.strlen((string) $chat).':"'.$chat.'";%')
        ->getQuery()
        ->setMaxResults(1)
        ->getOneOrNullResult();
      if (!$utente) {
        // errore
        $logger->error('Telegram webhook: errore cancellazione chat utente.', [$richiesta]);
        return new JsonResponse($risposta);
      }
      // rimuove chat
      $notifica = $utente->getNotifica();
      unset($notifica['telegram_chat']);
      $utente->setNotifica($notifica);
      $this->em->flush();
      // log
      $logger->warning('Telegram webhook: cancellazione chat utente.', [$utente->getUsername(),
        $richiesta]);
    }
    // restituisce risposta
    return new JsonResponse($risposta);
  }

}
