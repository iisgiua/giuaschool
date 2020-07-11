<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
     * @param SessionInterface $session Gestore delle sessioni
     *
     * @return array Array associativo con la struttura del menu
     */
    public function breadcrumb($url, Utente $utente=null, SessionInterface $session) {
      $dati = array();
      // imposta ruolo e funzione
      $ruolo = $utente ? $utente->getRoles()[0] : 'NESSUNO';
      if ($ruolo == 'ROLE_ATA' && $utente->getSegreteria()) {
        // abilita funzioni di segreteria per gli ATA
        $funzione = array('SEGRETERIA', 'NESSUNA');
      } elseif ($ruolo == 'ROLE_DOCENTE' && $session->get('/APP/DOCENTE/coordinatore')) {
        // abilita funzioni di coordinatore per i docenti
        $funzione = array('COORDINATORE', 'NESSUNA');
      } else {
        // nessuna funzione aggiuntiva
        $funzione = array('NESSUNA');
      }
      // legge dati
      $dati = $this->createQueryBuilder('m')
        ->select('o.nome,o.descrizione,o.url,(o.sottoMenu) AS sottomenu,o2.nome AS nome2,o2.descrizione AS descrizione2,o2.url AS url2,o3.nome AS nome3,o3.descrizione AS descrizione3,o3.url AS url3')
        ->join('App:MenuOpzione', 'o', 'WITH', 'o.menu=m.id')
        ->leftJoin('App:MenuOpzione', 'o2', 'WITH', 'o.menu=o2.sottoMenu AND o2.ruolo=:ruolo AND o2.funzione IN (:funzione)')
        ->leftJoin('App:MenuOpzione', 'o3', 'WITH', 'o2.menu=o3.sottoMenu AND o3.ruolo=:ruolo AND o3.funzione IN (:funzione)')
        ->where('o.url=:url AND o.ruolo=:ruolo AND o.funzione IN (:funzione)')
        ->setParameters(['url' => $url, 'ruolo' => $ruolo, 'funzione' => $funzione])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      // restituisce dati
      return $dati;
    }

}
