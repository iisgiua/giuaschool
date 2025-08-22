<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Circolare;
use App\Entity\Classe;
use App\Entity\ComunicazioneClasse;
use App\Entity\ComunicazioneUtente;
use App\Entity\Staff;
use App\Entity\Utente;


/**
 * Circolare - repository
 *
 * @author Antonello Dessì
 */
class CircolareRepository extends BaseRepository {

  /**
   * Restituisce il numero per la prossima circolare
   *
   * @return int Il numero per la prossima circolare
   */
  public function prossimoNumero(): int {
    // legge l'ultima circolare dell'A.S. in corso (comprese quelle in bozza)
    $numero = $this->createQueryBuilder('c')
      ->select('MAX(c.numero)')
      ->where("c.anno=0")
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce prossimo numero
    return ($numero + 1);
  }

  /**
   * Restituisce vero se il numero non è già in uso, falso altrimenti.
   *
   * @param Circolare $circolare Circolare esistente o da inserire
   *
   * @return bool Vero se il numero non è già in uso, falso altrimenti
   */
  public function controllaNumero(Circolare $circolare): bool {
    // legge la circolare dell'A.S. in corso
    $trovato = $this->createQueryBuilder('c')
      ->where('c.numero=:numero AND c.anno=0')
			->setParameter('numero', $circolare->getNumero());
    if ($circolare->getId() > 0) {
      // circolare in modifica, esclude suo id
      $trovato
        ->andWhere('c.id!=:id')
        ->setParameter('id', $circolare->getId());
    }
    $trovato = $trovato
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce vero se non esiste
    return ($trovato === null);
  }

  /**
   * Restituisce la lista delle circolari pubblicate nell'A.S. corrente, secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Dati formattati come array associativo
   */
  public function pubblicate(array $criteri, int $pagina): array {
    // crea query base
    $circolari = $this->createQueryBuilder('c')
      ->where("c.data BETWEEN :inizio AND :fine AND c.titolo LIKE :oggetto AND c.stato='P' AND c.anno=0")
      ->orderBy('c.data', 'DESC')
      ->addOrderBy('c.numero', 'DESC')
			->setParameter('inizio', $criteri['inizio'])
			->setParameter('fine', $criteri['fine'])
			->setParameter('oggetto', '%'.$criteri['oggetto'].'%');
    // paginazione
    $dati = $this->paginazione($circolari->getQuery(), (int) $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista delle circolari in bozza
   *
   * @return array Lista di circolari
   */
  public function bozza(): array {
    // crea query base
    $circolari = $this->createQueryBuilder('c')
      ->where("c.stato='B' AND c.anno=0")
      ->orderBy('c.data', 'DESC')
      ->addOrderBy('c.numero', 'DESC')
      ->getQuery()
      ->getResult();
    // restituisce lista
    return $circolari;
  }

  /**
   * Restituisce la lista delle circolari rispondenti alle condizioni di ricerca
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   * @param Utente $utente Destinatario delle circolari
   *
   * @return array Dati formattati come array associativo
   */
  public function lista(array $criteri, int $pagina, Utente $utente): array {
    $dati = [];
    // controllo A.S., stato e visualizzazione
    $anno = (isset($criteri['anno']) && $criteri['anno'] > 0) ? (int) $criteri['anno'] : 0;
    $stato = $anno > 0 ? 'A' : 'P';
    $visualizza = ($criteri['visualizza'] == 'T' && !($utente instanceOf Staff)) ? 'P' : $criteri['visualizza'];
    // query base
    $circolari = $this->createQueryBuilder('c')
      ->select('c as circolare,cu.id as destinatario,cu.letto')
      ->leftJoin(ComunicazioneUtente::class, 'cu', 'WITH', 'cu.comunicazione=c.id AND cu.utente=:utente')
      ->where('c.stato=:stato AND c.anno=:anno')
      ->setParameter('utente', $utente)
      ->setParameter('stato', $stato)
      ->setParameter('anno', $anno)
      ->orderBy('c.data', 'DESC')
      ->addOrderBy('c.numero', 'DESC');
    // filtra visualizzazione
    if ($visualizza != 'T') {
      // solo circolari destinate all'utente
      $circolari
        ->andWhere('cu.id IS NOT NULL'.($visualizza == 'D' ? ' AND cu.letto IS NULL' : ''));
    }
    // filtro mese
    if ($criteri['mese']) {
      $circolari
        ->andWhere('MONTH(c.data)=:mese')
        ->setParameter('mese', (int) $criteri['mese']);
    }
    // filtra per oggetto
    if ($criteri['oggetto']) {
      $circolari
        ->andWhere('c.titolo LIKE :oggetto')
        ->setParameter('oggetto', '%'.$criteri['oggetto'].'%');
    }
    // paginazione
    $dati = $this->paginazione($circolari->getQuery(), $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce le circolari non lette e destinate agli alunni della classe indicata
   *
   * @param Classe $classe Classe a cui sono indirizzate le circolari
   *
   * @return array Lista di circolari da leggere
   */
  public function listaCircolariClasse(Classe $classe): array {
    // lista circolari
    $circolari = $this->createQueryBuilder('c')
      ->join(ComunicazioneClasse::class, 'cc', 'WITH', 'cc.comunicazione=c.id AND cc.classe=:classe')
      ->where("c.stato='P' AND c.anno=0 AND cc.letto IS NULL")
			->setParameter('classe', $classe)
      ->orderBy('c.data', 'ASC')
      ->addOrderBy('c.numero', 'ASC')
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $circolari;
  }

  /**
   * Restituisce la lista degli anni scolastici presenti nell'archivio delle circolari (escluso l'anno corrente)
   *
   * @return array Dati formattati come array associativo
   */
  public function anniScolastici(): array {
    // inizializza
    $dati = [];
    // legge anni
    $anni = $this->createQueryBuilder('c')
      ->select('DISTINCT c.anno')
      ->where("c.stato='A'")
      ->orderBy('c.anno', 'DESC')
      ->getQuery()
      ->getArrayResult();
    foreach ($anni as $val) {
      $dati['A.S. '.$val['anno'].'/'.($val['anno'] + 1)] = $val['anno'];
    }
    // restituisce dati formattati
    return $dati;
  }

}
