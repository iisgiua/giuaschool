<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Richiesta;
use App\Entity\Staff;
use App\Entity\Utente;


/**
 * Richiesta - repository
 *
 * @author Antonello Dessì
 */
class RichiestaRepository extends BaseRepository {

  /**
   * Restituisce una nuova richiesta (multipla) del tipo indicato relativa all'alunno e alla data specificata
   *
   * @param string $tipo Codifica del tipo di richiesta
   * @param int $idAlunno Identificativo alunno che ha fatto richiesta
   * @param \DateTime $data Data di riferimento della richiesta
   *
   * @return Richiesta|null Richiesta, se esiste
   */
  public function richiestaAlunno(string $tipo, int $idAlunno, \DateTime $data): ?Richiesta {
    $richiesta = $this->createQueryBuilder('r')
      ->join('r.definizioneRichiesta', 'dr')
      ->where('dr.abilitata=:si AND dr.unica=:no AND dr.tipo=:tipo AND r.utente=:utente AND r.stato IN (:stati) AND r.data=:data')
      ->setParameters(['si' => 1, 'no' => 0, 'tipo' => $tipo, 'utente' => $idAlunno, 'stati' => ['I', 'G'],
        'data' => $data->format('Y-m-d')])
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce risultato
    return $richiesta;
  }

  /**
   * Restituisce la lista dei moduli di richiesta per la gestione da parte del destinatario
   *
   * @param Utente $utente Utente che gestisce i moduli di richiesta
   * @param array $criteri Criteri di ricerca dei moduli di richiesta
   * @param int $pagina Numero di pagina da visualizzare
   *
   * @return array Lista associativa con i risultati
   */
  public function lista(Utente $utente, array $criteri, int $pagina): array {
    // controllo destinatario
    $ruolo = $utente->getCodiceRuolo();
    $funzioni = array_map(fn($f) => "FIND_IN_SET('".$ruolo.$f."', dr.destinatari) > 0",
      $utente->getCodiceFunzioni());
    $sql = implode(' OR ', $funzioni);
    // query base
    $richieste = $this->createQueryBuilder('r')
      ->join('r.definizioneRichiesta', 'dr')
      ->join('App\Entity\Alunno', 'a', 'WITH', 'a.id=r.utente')
      ->join('r.classe', 'c')
      ->where('dr.abilitata=:abilitata AND dr.gestione=1 AND c.sede=:sede')
      ->andWhere($sql)
      ->setParameters(['abilitata' => 1, 'sede' => $criteri['sede']])
      ->orderBy('dr.nome,r.data,r.inviata', 'ASC');
    // controllo tipo
    if ($criteri['tipo'] == 'E' || $criteri['tipo'] == 'D') {
      // tipo indicato
      $richieste
        ->andWhere('dr.tipo=:tipo')
        ->setParameter('tipo', $criteri['tipo']);
    } elseif ($criteri['tipo'] == '*') {
      // altri tipi non definiti
      $richieste
        ->andWhere('dr.tipo NOT IN (:tipi)')
        ->setParameter('tipi', ['E', 'D', 'U']);
    } else {
      // tutte (escluso quelli gestiti altrove)
      $richieste
        ->andWhere('dr.tipo!=:tipo')
        ->setParameter('tipo', 'U');
    }
    // controllo stato
    if ($criteri['stato']) {
      // stato definito
      $richieste
        ->andWhere('r.stato=:stato')
        ->setParameter('stato', $criteri['stato']);
    }
    // controllo classe
    if ($criteri['classe']) {
      // classe definita
      $richieste
        ->andWhere('c.id=:classe')
        ->setParameter('classe', $criteri['classe']);
    }
    // controllo residenza
    if ($criteri['residenza']) {
      // residenza definita
      $richieste
        ->andWhere('a.citta LIKE :citta')
        ->setParameter('citta', $criteri['residenza'].'%');
    }
    // controllo cognome
    if ($criteri['cognome']) {
      // cognome definito
      $richieste
        ->andWhere('a.cognome LIKE :cognome')
        ->setParameter('cognome', $criteri['cognome'].'%');
    }
    // controllo nome
    if ($criteri['nome']) {
      // nome definito
      $richieste
        ->andWhere('a.nome LIKE :nome')
        ->setParameter('nome', $criteri['nome'].'%');
    }
    // paginazione
    $dati = $this->paginazione($richieste->getQuery(), $pagina);
    // per evitare errori di paginazione
    $dati['lista']->setUseOutputWalkers(false);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce il numero di nuove richieste per sede
   *
   * @param Staff $staff Docnete dello staff che gestisce i moduli di richiesta
   *
   * @return array Lista associativa con i risultati
   */
  public function contaNuove(Staff $staff): array {
    // controllo destinatario
    $ruolo = $staff->getCodiceRuolo();
    $funzioni = array_map(fn($f) => "FIND_IN_SET('".$ruolo.$f."', dr.destinatari) > 0",
      $staff->getCodiceFunzioni());
    $sql = implode(' OR ', $funzioni);
    // query base
    $richieste = $this->createQueryBuilder('r')
      ->select('COUNT(r.id) AS totale, s.nomeBreve')
      ->join('r.definizioneRichiesta', 'dr')
      ->join('App\Entity\Alunno', 'a', 'WITH', 'a.id=r.utente')
      ->join('a.classe', 'c')
      ->join('c.sede', 's')
      ->where('dr.abilitata=:abilitata AND dr.gestione=1 AND dr.tipo!=:tipo AND r.stato=:stato')
      ->andWhere($sql)
      ->groupBy('s.nomeBreve')
      ->orderBy('s.ordinamento', 'ASC')
      ->setParameters(['abilitata' => 1, 'tipo' => 'U', 'stato' => 'I']);
    // controlla sede
    if ($staff->getSede()) {
      // imposta sede
      $richieste
        ->andWhere('c.sede=:sede')
        ->setParameter('sede', $staff->getSede());
    }
    // esegue query
    $richieste = $richieste
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $richieste;
  }

  /**
   * Restituisce la lista dei moduli della classe
   *
   * @param Utente $utente Utente che gestisce i moduli
   * @param array $criteri Criteri di ricerca dei moduli
   * @param int $pagina Numero di pagina da visualizzare; se -1 nessuna paginazione
   *
   * @return array Lista associativa con i risultati
   */
  public function listaClasse(Utente $utente, string $tipo, array $criteri, int $pagina): array {
    // controllo destinatario
    $ruolo = $utente->getCodiceRuolo();
    $funzioni = array_map(fn($f) => "FIND_IN_SET('".$ruolo.$f."', dr.destinatari) > 0",
      $utente->getCodiceFunzioni());
    $sql = implode(' OR ', $funzioni);
    // query base
    $richieste = $this->createQueryBuilder('r')
      ->join('r.definizioneRichiesta', 'dr')
      ->join('r.classe', 'c')
      ->join('c.sede', 's')
      ->where("dr.abilitata=1 AND dr.tipo=:tipo AND r.stato='I'")
      ->andWhere($sql)
      ->setParameters(['tipo' => $tipo])
      ->orderBy('s.ordinamento,c.anno,c.sezione,r.data', 'ASC');
    // controllo sede
    if ($criteri['sede']) {
      // sede definita
      $richieste
        ->andWhere('s.id=:sede')
        ->setParameter('sede', $criteri['sede']);
    }
    // controllo classe
    if ($criteri['classe']) {
      // classe definita
      $richieste
        ->andWhere('c.id=:classe')
        ->setParameter('classe', $criteri['classe']);
    }
    if ($pagina == -1) {
      // tutti i dati senza paginazione
      $dati['lista'] = $richieste->getQuery()->getResult();
    } else {
      // paginazione
      $dati = $this->paginazione($richieste->getQuery(), $pagina);
      // per evitare errori di paginazione
      $dati['lista']->setUseOutputWalkers(false);
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista dei moduli inviati da alunni/genitori
   *
   * @param Utente $utente Utente che gestisce i moduli
   * @param array $criteri Criteri di ricerca dei moduli di richiesta
   * @param int $pagina Numero di pagina da visualizzare
   *
   * @return array Lista associativa con i risultati
   */
  public function listaModuliAlunni(Utente $utente, array $criteri, int $pagina): array {
    // controllo destinatario
    $ruolo = $utente->getCodiceRuolo();
    $funzioni = array_map(fn($f) => "FIND_IN_SET('".$ruolo.$f."', dr.destinatari) > 0",
      $utente->getCodiceFunzioni());
    $sql = implode(' OR ', $funzioni);
    // query base
    $moduli = $this->createQueryBuilder('r')
      ->join('r.definizioneRichiesta', 'dr')
      ->join('App\Entity\Alunno', 'a', 'WITH', 'a.id=r.utente')
      ->join('r.classe', 'c')
      ->join('c.sede', 's')
      ->where("dr.abilitata=1 AND dr.gestione=0 AND dr.tipo='#' AND dr.id=:modulo AND r.stato='I'")
      ->andWhere($sql)
      ->setParameters(['modulo' => $criteri['tipo']])
      ->orderBy('s.ordinamento,c.anno,c.sezione,a.cognome,a.nome,r.data', 'ASC');
    // controllo sede
    if ($criteri['sede']) {
      // sede definita
      $moduli
        ->andWhere('s.id=:sede')
        ->setParameter('sede', $criteri['sede']);
    }
    // controllo classe
    if ($criteri['classe']) {
      // classe definita
      $moduli
        ->andWhere('c.id=:classe')
        ->setParameter('classe', $criteri['classe']);
    }
    // controllo cognome
    if ($criteri['cognome']) {
      // cognome definito
      $moduli
        ->andWhere('a.cognome LIKE :cognome')
        ->setParameter('cognome', $criteri['cognome'].'%');
    }
    // controllo nome
    if ($criteri['nome']) {
      // nome definito
      $moduli
        ->andWhere('a.nome LIKE :nome')
        ->setParameter('nome', $criteri['nome'].'%');
    }
    if ($pagina == -1) {
      // tutti i dati senza paginazione
      $dati['lista'] = $moduli->getQuery()->getResult();
    } else {
      // paginazione
      $dati = $this->paginazione($moduli->getQuery(), $pagina);
      // per evitare errori di paginazione
      $dati['lista']->setUseOutputWalkers(false);
    }
    // restituisce dati
    return $dati;
  }

}
