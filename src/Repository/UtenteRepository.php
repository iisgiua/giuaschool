<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Amministratore;
use App\Entity\Ata;
use App\Entity\Docente;
use App\Entity\Genitore;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;


/**
 * Utente - repository
 *
 * @author Antonello Dessì
 */
class UtenteRepository extends EntityRepository {

  /**
   * Paginatore dei risultati della query
   *
   * @param Query $dql Query da mostrare
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function paginate($dql, $page=1, $limit=10) {
    $paginator = new Paginator($dql);
    $paginator->getQuery()
      ->setFirstResult($limit * ($page - 1))
      ->setMaxResults($limit);
    return $paginator;
  }

  /**
   * Trova un utente in base al nome normalizzato
   *
   * @param string $nome Nome normalizzato dell'utente (cognome e nome, maiuscolo, senza spazi)
   *
   * @return array Lista di utenti trovata
   */
  public function findByNomeNormalizzato($nome) {
    $query = $this->createQueryBuilder('u')
      ->where("UPPER(REPLACE(REPLACE(REPLACE(CONCAT(u.cognome,u.nome),' ',''),'''',''),'`','')) = :nome")
      ->setParameter(':nome', $nome)
      ->getQuery();
    return $query->getResult();
  }

  /**
   * Trova i profili attivi per l'utente indicato tramite codice fiscale.
   * NB: non si considera il profilo AMMINISTRATORE per ragioni di sicurezza (si dovrà accedere con apposito login)
   *
   * @param string $nome Nome dell'utente
   * @param string $cognome Cognome dell'utente
   * @param string $codiceFiscale Codice fiscale dell'utente
   * @param boolean $spid Vero per l'accesso tramite SPID
   *
   * @return null|Utente Null se nessun profilo, il primo profilo attivo negli altri casi
   */
  public function profiliAttivi($nome, $cognome, $codiceFiscale, $spid=false) {
    // trova profili
    $param = ['nome' => $nome, 'cognome' => $cognome, 'codiceFiscale' => $codiceFiscale, 'abilitato' => 1];
    if ($spid) {
      // accesso SPID: controlla che utente sia abilitato
      $param['spid'] = 1;
    }
    $profili = $this->findBy($param);
    if (empty($profili) || empty($codiceFiscale)) {
      // nessun profilo attivo: restituisce null
      return null;
    }
    // controlla se solo un profilo
    if (count($profili) == 1) {
      // solo un profilo: lo restituisce
      $profili[0]->setListaProfili([]);
      return $profili[0];
    }
    // crea un vettore con i dati dei profili e lo restituisce
    $dati = [];
    $numDati = 0;
    $utente = null;
    foreach ($profili as $profilo) {
      if (($profilo instanceOf Ata) && !isset($dati['ATA'])) {
        // può essercene solo uno
        $dati['ATA'][] = $profilo->getId();
        $numDati++;
        $utente = (!$utente ? $profilo : $utente);
      } elseif (($profilo instanceOf Docente) && !isset($dati['DOCENTE'])) {
        // può essercene solo uno
        $dati['DOCENTE'][] = $profilo->getId();
        $numDati++;
        $utente = (!$utente ? $profilo : $utente);
      } elseif ($profilo instanceOf Genitore) {
        // ce ne possono essere più di uno (più figli nella stessa scuola)
        $dati['GENITORE'][] = $profilo->getId();
        $numDati++;
        $utente = (!$utente ? $profilo : $utente);
      } elseif (($profilo instanceOf Alunno) && !isset($dati['ALUNNO'])) {
        // può essercene solo uno
        $dati['ALUNNO'][] = $profilo->getId();
        $numDati++;
        $utente = (!$utente ? $profilo : $utente);
      }
    }
    // restituisce primo profilo utente e memorizza la lista di profili
    if ($utente) {
      $utente->setListaProfili($numDati > 1 ? $dati : []);
    }
    return $utente;
  }

  /**
   * Implementazione dell'interfaccia PasswordUpgraderInterface per l'aggiornamento del codice hash della password
   *
   * @param UserInterface $user Utente che deve aggiornare il codice hash della password
   * @param string $newHashedPassword Nuovo codice hash della password
   */
  public function upgradePassword(UserInterface $user, string $newHashedPassword): void {
     // imposta il nuovo codice hash per la password
     $user->setPassword($newHashedPassword);
     // memorizza su db
     $this->getEntityManager()->flush();
  }

}
