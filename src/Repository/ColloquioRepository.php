<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use DateTime;
use App\Entity\Configurazione;
use App\Entity\RichiestaColloquio;
use App\Entity\Colloquio;
use App\Entity\Docente;
use App\Entity\Sede;


/**
 * Colloquio - repository
 *
 * @author Antonello Dessì
 */
class ColloquioRepository extends BaseRepository {

  /**
   * Restituisce i dati dei ricevimento di un docente nel periodo specificato e nello stato indicato.
   * Viene restituito anche il numero di richieste valide (in attesa o confermate).
   *
   * @param Docente $docente Docente di cui cercare i ricevimenti
   * @param Sede|null $sede Sede della classe dell'alunno (oppure null per qualsiasi sede)
   * @param DateTime|null $inizio Data di inizio del periodo di ricerca
   * @param DateTime|null $fine Data di fine del periodo di ricerca
   * @param bool|null $abilitato Se vero cerca ricevimenti abilitati, se falso quelli disabilitati, se nullo tutti
   *
   * @return array Lista dati restituiti
   */
  public function ricevimenti(Docente $docente, ?Sede $sede=null, ?DateTime $inizio=null, ?DateTime $fine=null,
                              ?bool $abilitato=null): array {
    $dati = [];
    // imposta valori predefiniti
    if (!$inizio) {
      $inizio = new DateTime('today');
    }
    if (!$fine) {
      $fine = DateTime::createFromFormat('Y-m-d H:i:s',
        $this->getEntityManager()->getRepository(Configurazione::class)->getParametro('anno_fine').' 00:00:00');
    }
    // query base
    $colloqui = $this->createQueryBuilder('c')
      ->select('c AS ricevimento, COUNT(rc.id) AS richieste')
      ->leftJoin(RichiestaColloquio::class, 'rc', 'WITH', 'rc.colloquio=c.id AND rc.stato IN (:valide)')
      ->where('c.docente=:docente AND c.data BETWEEN :inizio AND :fine')
      ->groupBy('c.id,c.creato,c.modificato,c.tipo,c.luogo,c.data,c.inizio,c.fine,c.durata,c.numero,c.abilitato,c.docente,c.sede')
      ->orderBy('c.data,c.inizio', 'ASC')
      ->setParameter('valide', ['R', 'C'])
      ->setParameter('docente', $docente)
      ->setParameter('inizio', $inizio->format('Y-m-d'))
      ->setParameter('fine', $fine->format('Y-m-d'));
    // filtra per sede
    if ($sede) {
      // cerca colloquio della sede indicata o in tutte se non specificato
      $colloqui
        ->andWhere('c.sede=:sede OR c.sede IS NULL')
        ->setParameter('sede', $sede);
    }
    // cerca abilitati/disabilitati
    if ($abilitato !== null) {
      $colloqui
        ->andWhere('c.abilitato=:abilitato')
        ->setParameter('abilitato', $abilitato);
    }
    // legge dati
    $colloqui = $colloqui
      ->getQuery()
      ->getResult();
    foreach ($colloqui as $colloquio) {
      $dati[$colloquio['ricevimento']->getId()] = $colloquio;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce il numero di richieste valide (inviate o confermate) per un dato ricevimento.
   *
   * @param Colloquio $colloquio Colloquio di cui contare le richieste
   *
   * @return int Numero richieste
   */
  public function numeroRichieste(Colloquio $colloquio): int {
    // conta richieste
    $numero = $this->createQueryBuilder('c')
      ->select('COUNT(rc.id)')
      ->join(RichiestaColloquio::class, 'rc', 'WITH', 'rc.colloquio=c.id')
      ->where('c.id=:colloquio AND rc.stato IN (:valide)')
      ->setParameter('colloquio', $colloquio)
      ->setParameter('valide', ['R', 'C'])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce valore
    return $numero;
  }

  /**
   * Restituisce la lista dei colloqui secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca(array $criteri): array {
    // crea query
    $colloqui = $this->createQueryBuilder('c')
      ->where('c.docente=:docente AND c.abilitato=:abilitato')
      ->orderBy('c.data,c.inizio', 'ASC')
      ->setParameter('docente', $criteri['docente'])
      ->setParameter('abilitato', 1)
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $colloqui;
  }

  /**
   * Controlla se esiste già un'altra ricevimento che si sovrappone a quello indicato
   *
   * @param Docente $docente Docente che effettua il colloquio
   * @param DateTime $data Data del ricevimento
   * @param DateTime $inizio Ora inizio del ricevimento
   * @param DateTime $fine Ora fine del ricevimento
   * @param int $esistente ID della richiesta esistente (per le modifiche)
   *
   * @return bool Restituisce vero se c'è una sovrapposizione dei ricevimenti
   */
  public function sovrapposizione(Docente $docente, DateTime $data, DateTime $inizio, DateTime $fine,
                                  int $esistente=0): bool {
    $sovrapposto = $this->createQueryBuilder('c')
      ->select('COUNT(c.id)')
      ->where('c.abilitato=:abilitato AND c.docente=:docente AND c.data=:data AND c.id!=:esistente')
      ->andWhere('(c.inizio>=:inizio AND c.inizio<:fine) OR (c.fine>:inizio AND c.fine<=:fine) OR (:inizio>=c.inizio AND :inizio<c.fine)')
      ->setParameter('abilitato', 1)
      ->setParameter('docente', $docente)
      ->setParameter('data', $data->format('Y-m-d'))
      ->setParameter('esistente', $esistente)
      ->setParameter('inizio', $inizio->format('H:i:s'))
      ->setParameter('fine', $fine->format('H:i:s'))
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce vero se esiste sovrapposizione
    return ($sovrapposto > 0);
  }

  /**
   * Restituisce le richieste valide di appuntamento per i colloqui con il docente indicato.
   * Sono valide le richieste per le date tra quella odierna e quella dell'intero mese successivo.
   *
   * @param Docente $docente Docente a cui sono inviate le richieste di colloquio
   *
   * @return array Dati delle richieste
   */
  public function richiesteValide(Docente $docente): array {
    $dati = [];
    $oggi = new DateTime('today');
    $fine = (new DateTime('tomorrow'))->modify('last day of next month');
    // legge dati prenotazioni
    $prenotazioni = $this->createQueryBuilder('c')
      ->select('c.id,c.tipo,c.data,c.inizio,c.fine,c.luogo,c.numero,rc.id AS id_prenotazione,rc.appuntamento,rc.stato,rc.messaggio,a.nome,a.cognome,a.dataNascita,cl.anno,cl.sezione,cl.gruppo')
      ->leftJoin(RichiestaColloquio::class, 'rc', 'WITH', 'rc.colloquio=c.id')
      ->leftJoin('rc.alunno', 'a')
      ->leftJoin('a.classe', 'cl')
      ->where('c.docente=:docente AND c.abilitato=:abilitato AND c.data BETWEEN :oggi AND :fine')
      ->orderBy('c.data,rc.appuntamento', 'ASC')
      ->setParameter('docente', $docente)
      ->setParameter('abilitato', 1)
      ->setParameter('oggi', $oggi->format('Y-m-d'))
      ->setParameter('fine', $fine->format('Y-m-d'))
      ->getQuery()
      ->getArrayResult();
    // imposta dati da restituire
    $dati['ricevimenti'] = [];
    $dati['inAttesa'] = 0;
    $id = 0;
    foreach ($prenotazioni as $prenotazione) {
      if ($id != $prenotazione['id']) {
        $id = $prenotazione['id'];
        $dati['ricevimenti'][$id] = [
          'tipo' => $prenotazione['tipo'],
          'data' => $prenotazione['data'],
          'inizio' => $prenotazione['inizio'],
          'fine' => $prenotazione['fine'],
          'luogo' => $prenotazione['luogo'],
          'numero' => $prenotazione['numero'],
          'valide' => 0,
          'prenotazioni' => []];
      }
      if (!empty($prenotazione['stato'])) {
        $dati['ricevimenti'][$id]['prenotazioni'][] = [
          'id' => $prenotazione['id_prenotazione'],
          'appuntamento' => $prenotazione['appuntamento'],
          'stato' => $prenotazione['stato'],
          'messaggio' => $prenotazione['messaggio'],
          'alunno' => $prenotazione['cognome'].' '.$prenotazione['nome'].' ('.
            $prenotazione['dataNascita']->format('d/m/Y').')',
          'classe' => $prenotazione['anno'].'ª '.$prenotazione['sezione'].
            ($prenotazione['gruppo'] ? ('-'.$prenotazione['gruppo']) : '')];
        if (in_array($prenotazione['stato'], ['R', 'C'], true)) {
          // conta richieste valide
          $dati['ricevimenti'][$id]['valide']++;
          if ($prenotazione['stato'] == 'R') {
            // conta richieste in attesa
            $dati['inAttesa']++;
          }
        }
      }
    }
    // restituisce i dati
    return $dati;
  }

  /**
   * Restituisce l'ora del nuovo appuntamento per il ricevimento indicato.
   *
   * @param Colloquio $colloquio Ricevimento per cui impostare il nuovo appuntamento
   *
   * @return DateTime Ora dell'appuntamento
   */
  public function nuovoAppuntamento(Colloquio $colloquio): DateTime {
    // legge prenotazioni esistenti
    $prenotazioni = $this->createQueryBuilder('c')
      ->select('rc.appuntamento')
      ->leftJoin(RichiestaColloquio::class, 'rc', 'WITH', 'rc.colloquio=c.id')
      ->where('c.id=:colloquio AND c.abilitato=:abilitato AND rc.stato IN (:validi)')
      ->orderBy('rc.appuntamento', 'ASC')
      ->setParameter('colloquio', $colloquio)
      ->setParameter('abilitato', 1)
      ->setParameter('validi', ['R', 'C'])
      ->getQuery()
      ->getResult();
    $ora = clone $colloquio->getInizio();
    foreach ($prenotazioni as $prenotazione) {
      if ($prenotazione['appuntamento'] != $ora) {
        // spazio libero
        break;
      }
      $ora->modify('+'.$colloquio->getDurata().' minutes');
    }
    // restituisce l'ora
    return $ora;
  }

  /**
   * Restituisce i ricevimenti senza richieste di un docente.
   *
   * @param Docente $docente Docente di cui cercare i ricevimenti
   * @param bool $abilitato Se vero cerca ricevimenti abilitati, se falso quelli disabilitati, se nullo tutti
   *
   * @return array Lista dati restituiti
   */
  public function cancellabili(Docente $docente, bool $abilitato=null): array {
    // subquery richieste
    $subquery = $this->getEntityManager()->getRepository(RichiestaColloquio::class)->createQueryBuilder('rc')
      ->select('rc.id')
      ->where('rc.colloquio=c.id')
      ->getDQL();
    // query base
    $colloqui = $this->createQueryBuilder('c')
      ->where('c.docente=:docente AND NOT EXISTS ('.$subquery.')')
      ->orderBy('c.data,c.inizio', 'ASC')
      ->setParameter('docente', $docente);
    // cerca abilitati/disabilitati
    if ($abilitato !== null) {
      $colloqui
        ->andWhere('c.abilitato=:abilitato')
        ->setParameter('abilitato', $abilitato);
    }
    // legge dati
    $colloqui = $colloqui
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $colloqui;
  }

}
