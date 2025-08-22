<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Ata;
use App\Entity\Circolare;
use App\Entity\Classe;
use App\Entity\Comunicazione;
use App\Entity\ComunicazioneClasse;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\Utente;
use DateTime;


/**
 * ComunicazioneUtente - repository
 *
 * @author Antonello Dessì
 */
class ComunicazioneUtenteRepository extends BaseRepository {

  /**
   * Restituisce le statistiche sulla lettura della comunicazione
   *
   * @param Comunicazione $comunicazione Comunicazione di cui fornire le statistiche
   *
   * @return array Dati formattati come array associativo
   */
  public function statistiche(Comunicazione $comunicazione): array {
    $dati = [];
    // speciali
    if (str_contains($comunicazione->getSpeciali(), 'D')) {
      // dati DSGA
      $utente = $this->createQueryBuilder('cu')
        ->select('ata.cognome,ata.nome,ata.username,cu.letto')
        ->join(Ata::class, 'ata', 'WITH', 'ata.id=cu.utente')
        ->where("cu.comunicazione=:comunicazione AND ata.tipo='D'")
			  ->setParameter('comunicazione', $comunicazione)
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      if ($utente) {
        $dati['dsga'] = ['totali' => 1,
          'letti' => $utente['letto'] ? 1 : 0,
          'percentuale' => $utente['letto'] ? 100 : 0,
          'elenco' => $utente['letto'] ? [$utente] : []];
      }
    }
    if (str_contains($comunicazione->getSpeciali(), 'S')) {
      // dati RSPP
      $utente = $this->createQueryBuilder('cu')
        ->select('d.cognome,d.nome,d.username,cu.letto')
        ->join(Docente::class, 'd', 'WITH', 'd.id=cu.utente')
        ->where("cu.comunicazione=:comunicazione AND d.rspp=1")
			  ->setParameter('comunicazione', $comunicazione)
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      if ($utente) {
        $dati['rspp'] = ['totali' => 1,
          'letti' => $utente['letto'] ? 1 : 0,
          'percentuale' => $utente['letto'] ? 100 : 0,
          'elenco' => $utente['letto'] ? [$utente] : []];
      }
    }
    if (str_contains($comunicazione->getSpeciali(), 'R')) {
      // dati RSU
      $utenti = $this->createQueryBuilder('cu')
        ->select('u.cognome,u.nome,u.username,cu.letto')
        ->join('cu.utente', 'u')
        ->where("cu.comunicazione=:comunicazione AND FIND_IN_SET('R', u.rappresentante)>0")
			  ->setParameter('comunicazione', $comunicazione)
        ->orderBy('u.cognome,u.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $totali = count($utenti);
      if ($totali > 0) {
        $elenco = array_filter($utenti, fn($u) => $u['letto']);
        $letti = count($elenco);
        $dati['rappresentanti_R'] = ['totali' => $totali,
          'letti' => $letti,
          'percentuale' => $letti / $totali * 100,
          'elenco' => $elenco];
      }
    }
    if (str_contains($comunicazione->getSpeciali(), 'I')) {
      // dati rappresentanti di Istituto
      $utenti = $this->createQueryBuilder('cu')
        ->select('u.cognome,u.nome,u.username,cu.letto')
        ->join('cu.utente', 'u')
        ->where("cu.comunicazione=:comunicazione AND FIND_IN_SET('I', u.rappresentante)>0")
			  ->setParameter('comunicazione', $comunicazione)
        ->orderBy('u.cognome,u.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $totali = count($utenti);
      if ($totali > 0) {
        $elenco = array_filter($utenti, fn($u) => $u['letto']);
        $letti = count($elenco);
        $dati['rappresentanti_I'] = ['totali' => $totali,
          'letti' => $letti,
          'percentuale' => $letti / $totali * 100,
          'elenco' => $elenco];
      }
    }
    if (str_contains($comunicazione->getSpeciali(), 'P')) {
      // dati rappresentanti Consulta Provinciale
      $utenti = $this->createQueryBuilder('cu')
        ->select('u.cognome,u.nome,u.username,cu.letto')
        ->join('cu.utente', 'u')
        ->where("cu.comunicazione=:comunicazione AND FIND_IN_SET('P', u.rappresentante)>0")
			  ->setParameter('comunicazione', $comunicazione)
        ->orderBy('u.cognome,u.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $totali = count($utenti);
      if ($totali > 0) {
        $elenco = array_filter($utenti, fn($u) => $u['letto']);
        $letti = count($elenco);
        $dati['rappresentanti_P'] = ['totali' => $totali,
          'letti' => $letti,
          'percentuale' => $letti / $totali * 100,
          'elenco' => $elenco];
      }
    }
    // ata
    if (!empty(array_intersect(str_split($comunicazione->getAta()), ['A', 'T', 'C']))) {
      $utenti = $this->createQueryBuilder('cu')
        ->select('ata.cognome,ata.nome,ata.username,ata.tipo,cu.letto')
        ->join(Ata::class, 'ata', 'WITH', 'ata.id=cu.utente')
        ->where("cu.comunicazione=:comunicazione AND ata.tipo IN (:tipi)")
			  ->setParameter('comunicazione', $comunicazione)
			  ->setParameter('tipi', str_split($comunicazione->getAta()))
        ->orderBy('ata.cognome,ata.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach (str_split($comunicazione->getAta()) as $tipo) {
        $elenco = array_filter($utenti, fn($u) => $u['tipo'] == $tipo && $u['letto']);
        $letti = count($elenco);
        $totali = count(array_filter($utenti, fn($u) => $u['tipo'] == $tipo));
        if ($totali > 0) {
          $dati['ata_'.$tipo] = ['totali' => $totali,
            'letti' => $letti,
            'percentuale' => $letti / $totali * 100,
            'elenco' => $elenco];
        }
      }
    }
    // coordinatori
    if ($comunicazione->getCoordinatori() != 'N') {
      $utenti = $this->createQueryBuilder('cu')
        ->select('d.cognome,d.nome,d.username,cl.anno,cl.sezione,cl.gruppo,cu.letto')
        ->join(Docente::class, 'd', 'WITH', 'd.id=cu.utente')
        ->join(Classe::class, 'cl', 'WITH', 'cl.coordinatore=d.id')
        ->where("cu.comunicazione=:comunicazione")
			  ->setParameter('comunicazione', $comunicazione)
        ->orderBy('cl.anno,cl.sezione,cl.gruppo', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $totali = count($utenti);
      if ($totali > 0) {
        $elenco = array_filter($utenti, fn($u) => $u['letto']);
        $letti = count($elenco);
        $dati['coordinatori'] = ['totali' => $totali,
          'letti' => $letti,
          'percentuale' => $letti / $totali * 100,
          'elenco' => $elenco];
      }
    }
    // docenti
    if ($comunicazione->getDocenti() != 'N') {
      $statistica = $this->createQueryBuilder('cu')
        ->select('COUNT(cu.id) AS totali,COUNT(cu.letto) AS letti')
        ->join(Docente::class, 'd', 'WITH', 'd.id=cu.utente')
        ->where('cu.comunicazione=:comunicazione')
			  ->setParameter('comunicazione', $comunicazione)
        ->getQuery()
        ->getOneOrNullResult();
      if ($statistica['totali'] > 0) {
        $elenco = $this->createQueryBuilder('cu')
          ->select('d.cognome,d.nome,d.username,cu.letto')
          ->join(Docente::class, 'd', 'WITH', 'd.id=cu.utente')
          ->where('cu.comunicazione=:comunicazione AND cu.letto IS NOT NULL')
          ->setParameter('comunicazione', $comunicazione)
          ->orderBy('d.cognome,d.nome', 'ASC')
          ->getQuery()
          ->getArrayResult();
        $dati['docenti'] = ['totali' => $statistica['totali'],
          'letti' => $statistica['letti'],
          'percentuale' => $statistica['letti'] / $statistica['totali'] * 100,
          'elenco' => $elenco];
      }
    }
    // genitori
    if ($comunicazione->getGenitori() != 'N') {
      $statistica = $this->createQueryBuilder('cu')
        ->select('COUNT(cu.id) AS totali,COUNT(cu.letto) AS letti')
        ->join(Genitore::class, 'g', 'WITH', 'g.id=cu.utente')
        ->where('cu.comunicazione=:comunicazione')
			  ->setParameter('comunicazione', $comunicazione)
        ->getQuery()
        ->getOneOrNullResult();
      if ($statistica['totali'] > 0) {
        $elenco = $this->createQueryBuilder('cu')
          ->select('a.cognome,a.nome,cl.anno,cl.sezione,cl.gruppo,g.cognome AS cognome_gen,g.nome AS nome_gen,cu.letto')
          ->join(Genitore::class, 'g', 'WITH', 'g.id=cu.utente')
          ->join('g.alunno', 'a')
          ->join('a.classe', 'cl')
          ->where('cu.comunicazione=:comunicazione AND cu.letto IS NOT NULL')
          ->setParameter('comunicazione', $comunicazione)
          ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome', 'ASC')
          ->getQuery()
          ->getArrayResult();
        $dati['genitori'] = ['totali' => $statistica['totali'],
          'letti' => $statistica['letti'],
          'percentuale' => $statistica['letti'] / $statistica['totali'] * 100,
          'elenco' => $elenco];
      }
    }
    // alunni
    if ($comunicazione->getAlunni() != 'N') {
      $statistica = $this->createQueryBuilder('cu')
        ->select('COUNT(cu.id) AS totali,COUNT(cu.letto) AS letti')
        ->join(Alunno::class, 'a', 'WITH', 'a.id=cu.utente')
        ->where('cu.comunicazione=:comunicazione')
			  ->setParameter('comunicazione', $comunicazione)
        ->getQuery()
        ->getOneOrNullResult();
      if ($statistica['totali'] > 0) {
        $elenco = $this->createQueryBuilder('cu')
          ->select('a.cognome,a.nome,cl.anno,cl.sezione,cl.gruppo,cu.letto')
          ->join(Alunno::class, 'a', 'WITH', 'a.id=cu.utente')
          ->join('a.classe', 'cl')
          ->where('cu.comunicazione=:comunicazione AND cu.letto IS NOT NULL')
          ->setParameter('comunicazione', $comunicazione)
          ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome', 'ASC')
          ->getQuery()
          ->getArrayResult();
        $dati['alunni'] = ['totali' => $statistica['totali'],
          'letti' => $statistica['letti'],
          'percentuale' => $statistica['letti'] / $statistica['totali'] * 100,
          'elenco' => $elenco];
      }
    }
    // rappresentanti genitori
    if ($comunicazione->getRappresentantiGenitori() != 'N') {
      $utenti = $this->createQueryBuilder('cu')
        ->select('a.cognome,a.nome,cl.anno,cl.sezione,cl.gruppo,g.cognome AS cognome_gen,g.nome AS nome_gen,cu.letto')
        ->join(Genitore::class, 'g', 'WITH', 'g.id=cu.utente')
        ->join('g.alunno', 'a')
        ->join('a.classe', 'cl')
        ->where("cu.comunicazione=:comunicazione AND FIND_IN_SET('L', g.rappresentante)>0")
			  ->setParameter('comunicazione', $comunicazione)
        ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $totali = count($utenti);
      if ($totali > 0) {
        $elenco = array_filter($utenti, fn($u) => $u['letto']);
        $letti = count($elenco);
        $dati['rappresentanti_L'] = ['totali' => $totali,
          'letti' => $letti,
          'percentuale' => $letti / $totali * 100,
          'elenco' => $elenco];
      }
    }
    // rappresentanti alunni
    if ($comunicazione->getRappresentantiAlunni() != 'N') {
      $utenti = $this->createQueryBuilder('cu')
        ->select('a.cognome,a.nome,cl.anno,cl.sezione,cl.gruppo,cu.letto')
        ->join(Alunno::class, 'a', 'WITH', 'a.id=cu.utente')
        ->join('a.classe', 'cl')
        ->where("cu.comunicazione=:comunicazione AND FIND_IN_SET('S', a.rappresentante)>0")
			  ->setParameter('comunicazione', $comunicazione)
        ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      $totali = count($utenti);
      if ($totali > 0) {
        $elenco = array_filter($utenti, fn($u) => $u['letto']);
        $letti = count($elenco);
        $dati['rappresentanti_S'] = ['totali' => $totali,
          'letti' => $letti,
          'percentuale' => $letti / $totali * 100,
          'elenco' => $elenco];
      }
    }
    // lettura classi
    $classi = $this->getEntityManager()->getRepository(ComunicazioneClasse::class)->createQueryBuilder('cc')
      ->select('cl.anno,cl.sezione,cl.gruppo,cc.letto')
      ->join('cc.classe', 'cl')
      ->where("cc.comunicazione=:comunicazione")
      ->setParameter('comunicazione', $comunicazione)
      ->orderBy('cl.anno,cl.sezione,cl.gruppo', 'ASC')
      ->getQuery()
      ->getArrayResult();
    $totali = count($classi);
    if ($totali > 0) {
      $elenco = array_filter($classi, fn($c) => !$c['letto']);
      $letti = $totali - count($elenco);
      $dati['classi'] = ['totali' => $totali,
        'letti' => $letti,
        'percentuale' => $letti / $totali * 100,
        'elenco' => $elenco];
    }
    // restituisce i dati
    return $dati;
  }

  /**
   * Conferma la lettura della comunicazione da parte dell'utente
   *
   * @param Comunicazione $comunicazione Comunicazione da firmare
   * @param Utente $utente Destinatario della comunicazione
   *
   * @return bool Vero se inserita conferma di lettura, falso altrimenti
   */
  public function firma(Comunicazione $comunicazione, Utente $utente): bool {
    // dati destinatario
    $cu = $this->findOneBy(['comunicazione' => $comunicazione, 'utente' => $utente]);
    if ($cu && !$cu->getFirmato()) {
      // imposta conferma di lettura
      $ora = new DateTime();
      $cu
        ->setFirmato($ora)
        ->setLetto($ora);
      // memorizza dati
      $this->getEntityManager()->flush();
      // conferma inserita
      return true;
    }
    // conferma non inserita
    return false;
  }

  /**
   * Conferma la lettura implicita della comunicazione da parte dell'utente
   *
   * @param Comunicazione $comunicazione Comunicazione in lettura
   * @param Utente $utente Destinatario della comunicazione
   *
   * @return bool Vero se inserita conferma di lettura, falso altrimenti
   */
  public function legge(Comunicazione $comunicazione, Utente $utente): bool {
    // dati destinatario
    $cu = $this->findOneBy(['comunicazione' => $comunicazione, 'utente' => $utente]);
    if ($cu && !$cu->getLetto()) {
      // imposta conferma di lettura
      $ora = new DateTime();
      $cu->setLetto($ora);
      // memorizza dati
      $this->getEntityManager()->flush();
      // conferma inserita
      return true;
    }
    // conferma non inserita
    return false;
  }

  /**
   * Restituisce la lista degli utenti a cui deve essere inviata una notifica per la comunicazione indicata
   *
   * @param Comunicazione $comunicazione Comunicazione da notificare
   *
   * @return array Lista degli ID degli utenti
   */
  public function notifica(Comunicazione $comunicazione): array {
    // legge destinatari
    $destinatari = $this->createQueryBuilder('cu')
      ->select('(cu.utente) AS utente')
      ->join('cu.comunicazione', 'c')
      ->where("cu.comunicazione=:comunicazione AND cu.letto IS NULL AND c.stato='P'")
			->setParameter('comunicazione', $comunicazione)
      ->getQuery()
      ->getArrayResult();
    // restituisce lista utenti

    return array_column($destinatari, 'utente');
  }

  /**
   * Controlla la presenza di circolari non lette e destinate all'utente
   *
   * @param Utente $utente Utente a cui sono indirizzate le circolari
   *
   * @return int Numero di circolari da leggere
   */
  public function numeroCircolariUtente(Utente $utente): int {
  // lista circolari
    $numCircolari = $this->createQueryBuilder('cu')
      ->select('COUNT(cu)')
      ->join(Circolare::class, 'c', 'WITH', 'cu.comunicazione=c.id AND cu.utente=:utente')
      ->where("cu.letto IS NULL AND c.stato='P'")
			->setParameter('utente', $utente)
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $numCircolari;
  }

}
