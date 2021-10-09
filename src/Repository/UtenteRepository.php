<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use App\Entity\Amministratore;
use App\Entity\Ata;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\Alunno;


/**
 * Utente - repository
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
   * @param string $codiceFiscale Codice fiscale dell'utente
   *
   * @return null|Utente Null se nessun profilo, il primo profilo attivo negli altri casi
   */
  public function profiliAttivi($codiceFiscale) {
    $profili = $this->findBy(['codiceFiscale' => $codiceFiscale, 'abilitato' => 1]);
    if (empty($profili)) {
      // nessun profilo attivo: restituisce null
      return null;
    }
    if (count($profili) == 1) {
      // solo un profilo attivo: restituisce istanza utente
      return $profili[0];
    }
    // crea un vettore con i dati dei profili e lo restituisce
    $dati = [];
    foreach ($profili as $profilo) {
      if ($profilo instanceOf Ata) {
        // può essercene solo uno
        $dati['ATA'][0] = $profilo->getId();
      } elseif ($profilo instanceOf Docente) {
        // può essercene solo uno
        $dati['DOCENTE'][0] = $profilo->getId();
      } elseif ($profilo instanceOf Genitore) {
        // ce ne possono essere più di uno (più figli nella stessa scuola)
        $dati['GENITORE'][] = $profilo->getId();
      } elseif ($profilo instanceOf Alunno) {
        // può essercene solo uno
        $dati['ALUNNO'][0] = $profilo->getId();
      }
    }
    // restituisce primo profilo utente e memorizza la lista di profili
    $utente = $profili[0];
    $utente->setListaProfili($dati);
    return $utente;
  }

}
