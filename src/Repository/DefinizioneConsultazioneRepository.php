<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\DefinizioneAutorizzazione;
use App\Entity\Genitore;
use App\Entity\Richiesta;
use App\Entity\Utente;
use DateTime;


/**
 * DefinizioneConsultazione - repository
 *
 * @author Antonello Dessì
 */
class DefinizioneConsultazioneRepository extends BaseRepository {

  /**
   * Restituisce la lista delle consultazioni accessibili all'utente indicato
   *
   * @param Utente $utente Utente che ha accesso alle consultazioni
   *
   * @return array Lista associativa con i dati
   */
  public function lista(Utente $utente): array {
    $ruolo = $utente->getCodiceRuolo();
    $funzioni = array_map(fn($f) => "FIND_IN_SET('".$ruolo.$f."', dc.richiedenti) > 0",
      $utente->getCodiceFunzioni());
    $sql = implode(' OR ', $funzioni);
    // opzione sede
    $classe = ($utente instanceOf Alunno) ? $utente->getClasse() :
      (($utente instanceOf Genitore) ? $utente->getAlunno()->getClasse() : null);
    $sedi = $classe ? [$classe->getSede()] : [];
    // legge consultazioni
    $consultazioni = $this->createQueryBuilder('dc')
      ->select('dc.id,dc.nome,dc.fine,r.id as risposta_id,r.inviata,r.documento,r.allegati')
      ->leftJoin(Richiesta::class, 'r', 'WITH', 'r.definizioneRichiesta=dc.id AND r.utente=:utente AND r.stato IN (:stati)')
      ->where('NOT (dc INSTANCE OF '.DefinizioneAutorizzazione::class.') AND dc.abilitata=1 AND :adesso BETWEEN dc.inizio AND dc.fine AND (dc.sede IS NULL OR dc.sede IN (:sedi)) AND (dc.classi IS NULL OR FIND_IN_SET(:classe, dc.classi) > 0)')
      ->andWhere($sql)
      ->setParameter('utente', $utente)
      ->setParameter('stati', ['I', 'G'])
      ->setParameter('adesso', new DateTime('now'))
      ->setParameter('sedi', $sedi)
      ->setParameter('classe', $classe)
      ->orderBy('dc.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // formatta dati
    $dati = [];
    foreach ($consultazioni as $consultazione) {
      $modulo = $consultazione['id'];
      $dati[$modulo] = [
        'nome' => $consultazione['nome'],
        'fine' => $consultazione['fine'],
        'id' => $consultazione['risposta_id'],
        'inviata' => $consultazione['inviata'],
        'documento' => $consultazione['documento'],
        'allegati' => $consultazione['allegati']];
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la statistica delle consultazioni
   *
   * @param Utente $utente Utente che gestisce le consultazioni
   *
   * @return array Lista associativa con i dati
   */
  public function statistica(Utente $utente): array {
    // controllo destinatario
    $ruolo = $utente->getCodiceRuolo();
    $funzioni = array_map(fn($f) => "FIND_IN_SET('".$ruolo.$f."', dc.destinatari) > 0",
      $utente->getCodiceFunzioni());
    $sql = implode(' OR ', $funzioni);
    // legge consultazioni
    $consultazioni = $this->createQueryBuilder('dc')
      ->select('dc.id,dc.nome,dc.inizio,dc.fine,dc.classi,s.nomeBreve AS sede,dc.richiedenti,COUNT(r.id) AS risposte')
      ->leftJoin('dc.sede', 's')
      ->leftJoin(Richiesta::class, 'r', 'WITH', 'r.definizioneRichiesta=dc.id AND r.stato IN (:stati)')
      ->where('NOT (dc INSTANCE OF '.DefinizioneAutorizzazione::class.') AND dc.abilitata=1')
      ->andWhere($sql)
      ->setParameter('stati', ['I', 'G'])
      ->groupBy('dc.id,dc.nome,dc.inizio,dc.fine,dc.classi,sede,dc.richiedenti')
      ->orderBy('dc.inizio', 'DESC')
      ->addOrderBy('dc.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // formatta dati
    $dati = [];
    $adesso = new DateTime('now');
    foreach ($consultazioni as $consultazione) {
      $dati[$consultazione['id']] = $consultazione;
      if ($adesso < $consultazione['inizio']) {
        $dati[$consultazione['id']]['stato'] = 'W'; // waiting
      } elseif ($adesso <= $consultazione['fine']) {
        $dati[$consultazione['id']]['stato'] = 'O'; // open
      } else {
        $dati[$consultazione['id']]['stato'] = 'C'; // closed
      }
      $destinatari = [];
      foreach (explode(',', $consultazione['richiedenti']) as $destinatario) {
        $destinatari[] = ($destinatario == 'AN' ? 'label.alunni' : ($destinatario == 'GN' ? 'label.genitori' : ''));
      }
      $dati[$consultazione['id']]['destinatari'] = $destinatari;
      $classi = [];
      if (!empty($consultazione['classi'])) {
        foreach ($consultazione['classi'] as $classe) {
          $classi[] = $this->getEntityManager()->getRepository(Classe::class)->find($classe);
        }
      }
      $dati[$consultazione['id']]['classi'] = $classi;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista dei moduli di consultazioni per la pagina di gestione
   *
   * @return array Lista degli oggetti di tipo DefinizioneConsultazione
   */
  public function gestione(): array {
    $richieste = $this->createQueryBuilder('dc')
      ->where('NOT (dc INSTANCE OF '.DefinizioneAutorizzazione::class.')')
      ->orderBy('dc.nome', 'ASC')
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $richieste;
  }

}
