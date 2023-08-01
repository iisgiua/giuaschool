<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use App\Entity\Log;


/**
 * LogHandler - classe di utilità per l'inserimento di log nel database
 *
 * @author Antonello Dessì
 */
class LogHandler {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var RequestStack $request Coda delle pagine richieste
   */
  private $request;

  /**
   * @var TokenStorageInterface $token Gestore dei dati di autenticazione
   */
  private $token;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $request Coda delle pagine richieste
   * @param TokenStorageInterface $token Gestore dei dati di autenticazione
   */
  public function __construct(EntityManagerInterface $em, RequestStack $request,
                              TokenStorageInterface $token) {
    $this->em = $em;
    $this->request = $request;
    $this->token = $token;
  }

  /**
   * Scrive sul database le informazioni di log di un'azione dell'utente e le rende permanenti (flush).
   *
   * @param string $categoria Categoria dell'azione dell'utente
   * @param string $azione Azione dell'utente
   * @param array $dati Lista di dati che descrivono l'azione
   */
  public function logAzione($categoria, $azione, $dati) {
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

  /**
   * Scrive sul database le informazioni di log della creazione di un'istanza e le rende permanenti (flush).
   *
   * @param string $categoria Categoria dell'azione dell'utente
   * @param string $azione Azione dell'utente
   * @param object $oggetto Istanza creata
   */
  public function logCreazione($categoria, $azione, $oggetto) {
    // inizializza
    $req = $this->request->getCurrentRequest();
    $tok = $this->token->getToken();
    $conn = $this->em->getConnection();
    $dati = [];
    // inizia transazione
    $conn->beginTransaction();
    $this->em->flush();
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
    // dati oggetto
    $dati['classe'] = get_class($oggetto);
    $dati['id'] = $oggetto->getId();
    $dati['dati'] = $oggetto->datiVersione();
    // scrive su db
    try{
      $log = (new Log())
        ->setUtente($utente)
        ->setUsername($username)
        ->setRuolo($ruolo)
        ->setAlias($alias)
        ->setIp($ip)
        ->setOrigine($origine)
        ->setTipo('C')
        ->setAzione($azione)
        ->setCategoria($categoria)
        ->setDati($dati);
      $this->em->persist($log);
      $this->em->flush();
      $conn->commit();
    } catch (\Exception $e) {
      // errore: evita scrittura di tutto quanto
      $conn->rollBack();
      throw $e;
    }
  }

  /**
   * Scrive sul database le informazioni di log della rimozione di un'istanza e le rende permanenti (flush).
   *
   * @param string $categoria Categoria dell'azione dell'utente
   * @param string $azione Azione dell'utente
   * @param object $oggetto Istanza rimossa
   */
  public function logRimozione($categoria, $azione, $oggetto) {
    // inizializza
    $req = $this->request->getCurrentRequest();
    $tok = $this->token->getToken();
    $dati = [];
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
    // dati oggetto
    $dati['classe'] = get_class($oggetto);
    $dati['id'] = $oggetto->getId();
    $dati['vecchi_dati'] = $oggetto->datiVersione();
    // scrive su db
    $log = (new Log())
      ->setUtente($utente)
      ->setUsername($username)
      ->setRuolo($ruolo)
      ->setAlias($alias)
      ->setIp($ip)
      ->setOrigine($origine)
      ->setTipo('D')
      ->setAzione($azione)
      ->setCategoria($categoria)
      ->setDati($dati);
    $this->em->persist($log);
    $this->em->flush();
  }

  /**
   * Scrive sul database le informazioni di log della modifica di un'istanza e le rende permanenti (flush).
   *
   * @param string $categoria Categoria dell'azione dell'utente
   * @param string $azione Azione dell'utente
   * @param object $oggIniziale Istanza iniziale
   * @param object $oggModificato Istanza modificata (deve essere la stessa istanza)
   */
  public function logModifica($categoria, $azione, $oggIniziale, $oggModificato) {
    // inizializza
    $req = $this->request->getCurrentRequest();
    $tok = $this->token->getToken();
    $dati = [];
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
    // dati oggetto
    $dati['classe'] = get_class($oggIniziale);
    $dati['id'] = $oggIniziale->getId();
    $dati['vecchi_dati'] = [];
    $dati['dati'] = [];
    $versioneIniziale = $oggIniziale->datiVersione();
    $versioneModificata = $oggModificato->datiVersione();
    foreach ($versioneIniziale as $key=>$val) {
      if($val !== $versioneModificata[$key]) {
        // campo modificato
        $dati['vecchi_dati'][$key] = $val;
        $dati['dati'][$key] = $versioneModificata[$key];
      }
    }
    // scrive su db
    $log = (new Log())
      ->setUtente($utente)
      ->setUsername($username)
      ->setRuolo($ruolo)
      ->setAlias($alias)
      ->setIp($ip)
      ->setOrigine($origine)
      ->setTipo('U')
      ->setAzione($azione)
      ->setCategoria($categoria)
      ->setDati($dati);
    $this->em->persist($log);
    $this->em->flush();
  }

}
