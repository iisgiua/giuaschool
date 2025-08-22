<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;


/**
 * LogHandler - classe di utilità per l'inserimento dei log delle azioni nel database
 *
 * @author Antonello Dessì
 */
class LogHandler {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $request Coda delle pagine richieste
   * @param TokenStorageInterface $token Gestore dei dati di autenticazione
   */
  public function __construct(
      private readonly EntityManagerInterface $em,
      private readonly RequestStack $request,
      private readonly TokenStorageInterface $token) {
  }

  /**
   * Scrive sul database le informazioni di log di un'azione dell'utente e le rende permanenti (flush).
   *
   * @param string $categoria Categoria dell'azione dell'utente
   * @param string $azione Azione dell'utente
   * @param array $dati Lista di dati che descrivono l'azione
   */
  public function logAzione(string $categoria, string $azione, array $dati = []): void {
    // inizializza
    $req = $this->request->getCurrentRequest();
    $tok = $this->token->getToken();
    // dati utente (si presuppone che un utente sia necessariamente connesso)
    $utente = $tok->getUser();
    $username = $utente->getUserIdentifier();
    $ruolo = $utente->getRoles()[0];
    $alias = null;
    if ($tok instanceOf SwitchUserToken) {
      $alias = $tok->getOriginalToken()->getUser()->getUserIdentifier();
    }
    // dati di navigazione
    $ip = $req->getClientIp();
    $origine = $req->attributes->get('_controller');
    if (empty($origine) && $req->getPathInfo() === '/logout/') {
      $origine = 'App\Controller\LoginController::logout';
    }
    // scrive su db
    $log = (new Log())
      ->setUtente($utente)
      ->setUsername($username)
      ->setRuolo($ruolo)
      ->setAlias($alias)
      ->setIp($ip)
      ->setOrigine($origine)
      ->setTipo('A')
      ->setCategoria($categoria)
      ->setAzione($azione)
      ->setDati($dati);
    $this->em->persist($log);
    // scrive su db i dati
    $this->em->flush();
  }

}
