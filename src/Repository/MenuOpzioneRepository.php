<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\Utente;


/**
 * MenuOpzione - repository
 */
class MenuOpzioneRepository extends EntityRepository {

    /**
     * Restituisce il percorso nel menu dell'url indicata
     *
     * @param string $url Pagina da cercare
     * @param Utente $utente Utente per il quale restituire il menu
     * @param RequestStack $reqstack Gestore dello stack delle variabili globali
     *
     * @return array Array associativo con la struttura del menu
     */
    public function breadcrumb($url, Utente $utente=null, RequestStack $reqstack) {
      $dati = array();
      // imposta ruolo e funzione
      $ruolo = $utente ? $utente->getCodiceRuolo() : 'N';
      $funzione = $utente ? $utente->getCodiceFunzione() : 'N';
      // legge dati
      $dati = $this->createQueryBuilder('o')
        ->select('o.nome,o.descrizione,o.url,(o.sottoMenu) AS sottomenu,o2.nome AS nome2,o2.descrizione AS descrizione2,o2.url AS url2,o3.nome AS nome3,o3.descrizione AS descrizione3,o3.url AS url3')
        ->leftJoin('App\Entity\MenuOpzione', 'o2', 'WITH', 'o.menu=o2.sottoMenu AND INSTR(:ruolo, o2.ruolo) > 0 AND INSTR(:funzione, o2.funzione) > 0')
        ->leftJoin('App\Entity\MenuOpzione', 'o3', 'WITH', 'o2.menu=o3.sottoMenu AND INSTR(:ruolo, o3.ruolo) > 0 AND INSTR(:funzione, o3.funzione) > 0')
        ->where('o.url=:url AND INSTR(:ruolo, o.ruolo) > 0 AND INSTR(:funzione, o.funzione) > 0')
        ->setParameters(['url' => $url, 'ruolo' => $ruolo, 'funzione' => $funzione])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // restituisce dati
      return $dati;
    }

}
