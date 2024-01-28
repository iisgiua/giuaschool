<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Ata;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\Utente;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * Utente - repository
 *
 * @author Antonello Dessì
 */
class UtenteRepository extends EntityRepository {

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

  /**
   * Restituisce gli utenti relativi ai rappresentanti indicati
   *
   * @param array $destinatari Lista dei destinatari
   *
   * @return array Lista di ID degli utenti
   */
  public function getIdRappresentanti($destinatari) {
    // seleziona destinatari
    $test = [];
    $param = [];
    foreach ($destinatari as $dest) {
      if (in_array($dest, ['R', 'I', 'P'])) {
        $test[] = 'FIND_IN_SET(:'.$dest.', u.rappresentante)>0';
        $param[] = $dest;
      }
    }
    if (empty($test)) {
      // nessun destinatario tra i rappresentanti
      return [];
    }
    // crea query
    $utenti = $this->createQueryBuilder('u')
      ->select('DISTINCT u.id')
      ->where('u.abilitato=:abilitato')
      ->andWhere(implode(' OR ', $test))
      ->setParameter('abilitato', 1);
    foreach ($param as $p) {
      $utenti->setParameter(':'.$p, $p);
    }
    // esegue query
    $utenti = $utenti
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($utenti, 'id');
  }

  /**
   * Restituisce gli utenti relativi ai rappresentanti di classe indicati
   *
   * @param array $filtro Lista dei destinatari
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   * @param string $tipo Tipo di filtro [T=tutti, C=filtro classe]
   * @param array $filtro Lista di ID per il filtro indicato
   *
   * @return array Lista di ID degli utenti
   */
  public function getIdRappresentantiClasse(array $destinatari, array $sedi, string $tipo, array $filtro) {
    $alunni = [];
    $genitori = [];
    // rappresentanti alunni
    if (in_array('S', $destinatari)) {
      $alunni = $this->_em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('DISTINCT a.id')
        ->join('a.classe', 'cl')
        ->where('a.abilitato=:abilitato AND FIND_IN_SET(:classe, a.rappresentante)>0 AND cl.sede IN (:sedi)')
        ->setParameters(['abilitato' => 1, 'classe' => 'S', 'sedi' => $sedi]);
      if ($tipo == 'C') {
        // filtro classi
        $alunni->andWhere('cl.id IN (:classi)')->setParameter('classi', $filtro);
      }
      // esegue query
      $alunni = $alunni
        ->getQuery()
        ->getArrayResult();
    }
    // rappresentanti genitori
    if (in_array('L', $destinatari)) {
      $genitori = $this->_em->getRepository('App\Entity\Genitore')->createQueryBuilder('g')
        ->select('DISTINCT g.id')
        ->join('g.alunno', 'a')
        ->join('a.classe', 'cl')
        ->where('g.abilitato=:abilitato AND FIND_IN_SET(:classe, g.rappresentante)>0 AND a.abilitato=:abilitato AND cl.sede IN (:sedi)')
        ->setParameters(['abilitato' => 1, 'classe' => 'L', 'sedi' => $sedi]);
      if ($tipo == 'C') {
        // filtro classi
        $genitori->andWhere('cl.id IN (:classi)')->setParameter('classi', $filtro);
      }
      // esegue query
      $genitori = $genitori
        ->getQuery()
        ->getArrayResult();
    }
    // restituisce la lista degli ID
    return array_merge(array_column($alunni, 'id'), array_column($genitori, 'id'));
  }

}
