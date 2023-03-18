<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use App\Entity\Utente;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


/**
 * TelegramManager - classe di utilità per la gestione delle comunicazioni tramite Telegram
 *
 * @author Antonello Dessì
 */
class TelegramManager {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var UrlGeneratorInterface $url Generatore delle URL
   */
  private UrlGeneratorInterface $url;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private EntityManagerInterface $em;

  /**
   * @var Client $client Client HTTP per la gestione delle comunicazioni
   */
  private Client $client;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param UrlGeneratorInterface $url Generatore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(UrlGeneratorInterface $url, EntityManagerInterface $em) {
    $this->url = $url;
    $this->em = $em;
    $token = $this->em->getRepository('App\Entity\Configurazione')->getParametro('telegram_token');
    $this->client = new Client(['base_uri' => 'https://api.telegram.org/bot'.$token.'/',
      'timeout' => 60]);
  }

  /**
   * Installa un webhook per il bot Telegram
   *
   * @return array Informazioni su eventuali errori e lista dei dati ricevuti
   */
  public function setWebhook(): array {
    // crea client con parametri aggiornati
    $token = $this->em->getRepository('App\Entity\Configurazione')->getParametro('telegram_token');
    if (empty($token)) {
      // token vuoto: ignora
      return ['result' => 'ok'];
    }
    $this->client = new Client(['base_uri' => 'https://api.telegram.org/bot'.$token.'/',
      'timeout' => 60]);
    // configura
    $url = $this->url->generate('notifica_telegram', [], UrlGeneratorInterface::ABSOLUTE_URL);
    $connections = 5;
    $allowed = ['message', 'my_chat_member'];
    $secret = 'BOT-'.bin2hex(openssl_random_pseudo_bytes(8)).'-'.bin2hex(openssl_random_pseudo_bytes(8));
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('telegram_secret', $secret);
    // installa webhook
    return $this->request('setWebhook', ['url' => $url, 'max_connections' => $connections,
      'allowed_updates' => $allowed, 'drop_pending_updates' => true, 'secret_token' => $secret]);
  }

  /**
   * Restituisce le informazioni sul webhook installato per il bot Telegram
   *
   * @return array Informazioni su eventuali errori e lista dei dati ricevuti
   */
  public function getWebhook(): array {
    return $this->request('getWebhookInfo');
  }

  /**
   * Rimuove il webhook installato per il bot Telegram
   *
   * @return array Informazioni su eventuali errori e lista dei dati ricevuti
   */
  public function deleteWebhook(): array {
    // crea client con parametri aggiornati
    $token = $this->em->getRepository('App\Entity\Configurazione')->getParametro('telegram_token');
    if (empty($token)) {
      // token vuoto: ignora
      return ['result' => 'ok'];
    }
    $this->client = new Client(['base_uri' => 'https://api.telegram.org/bot'.$token.'/',
      'timeout' => 60]);
    return $this->request('deleteWebhook', ['drop_pending_updates' => true]);
  }

  /**
   * Invia un messaggio alla chat Telegram
   *
   * @param string $chat Identificativo della chat Telegram
   * @param string $html Testo del messaggio da inviare alla chat
   *
   * @return array Informazioni su eventuali errori e lista dei dati ricevuti
   */
  public function sendMessage(string $chat, string $html): array {
    // invia messaggio
    $params = [
      'chat_id' => $chat,
      'text' => $html,
      'parse_mode' => 'HTML'];
    return $this->request('sendMessage', $params);
  }

  //==================== METODI PRIVATI ====================

  /**
   * Esegue una chiamata alle API Telegram
   *
   * @param string $action Definizione dell'API da eseguire
   * @param array $params Lista dei parametri per le API
   *
   * @return array Informazioni su eventuali errori e lista dei dati ricevuti
   */
  private function request(string $action, array $params=[]): array {
    // init
    $data = [];
    // invia richiesta
    try {
      $response = $this->client->post($action, ['form_params' => $params]);
    } catch (\Exception $e) {
      // errore di connessione
      $data['error'] = $e->getMessage();
      return $data;
    }
    // legge risposta
    $msg = json_decode($response->getBody());
    if (!$msg->ok) {
      // errore nella risposta
      $data['error'] = 'Response error: '.($msg->description ?? 'invalid answer');
      return $data;
    }
    // risposta ok
    $data['result'] = $msg->result;
    return $data;
  }

}
