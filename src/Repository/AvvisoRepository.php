<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Avviso;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\ComunicazioneClasse;
use App\Entity\ComunicazioneUtente;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\RichiestaColloquio;
use App\Entity\Utente;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;


/**
 * Avviso - repository
 *
 * @author Antonello Dessì
 */
class AvvisoRepository extends BaseRepository {

  /**
   * Restituisce gli avvisi per la pagina di gestione secondo i criteri di ricerca inseriti
   *
   * @param array $criteri Criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Dati formattati come array associativo
   */
  public function lista(array $criteri, int $pagina): array {
    // query base
    $avvisi = $this->createQueryBuilder('a')
      ->where("a.stato='P' AND a.tipo IN ('C', 'E', 'U', 'A', 'I')")
      ->orderBy('a.data', 'DESC')
      ->addOrderBy('a.titolo', 'ASC');
    // filtro autore
    if ($criteri['autore']) {
      $avvisi
        ->andWhere('a.autore=:autore')
        ->setParameter('autore', $criteri['autore']);
    }
    // filtro tipo
    if ($criteri['tipo']) {
      $avvisi
        ->andWhere('a.tipo=:tipo')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // filtro mese
    if ($criteri['mese']) {
      $avvisi
        ->andWhere('MONTH(a.data)=:mese')
        ->setParameter('mese', $criteri['mese']);
    }
    // filtro oggetto
    if ($criteri['oggetto']) {
      $avvisi
        ->andWhere('a.titolo LIKE :oggetto')
        ->setParameter('oggetto', '%'.$criteri['oggetto'].'%');
    }
    // paginazione
    $dati = $this->paginazione($avvisi->getQuery(), $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista degli anni scolastici presenti nell'archivio degli avvisi
   *
   * @return array Dati formattati come array associativo
   */
  public function anniScolastici(): array {
    // inizializza
    $dati = [];
    // legge anni
    $anni = $this->createQueryBuilder('a')
      ->select('DISTINCT a.anno')
      ->where("a.stato='A'")
      ->orderBy('a.anno', 'DESC')
      ->getQuery()
      ->getArrayResult();
    foreach ($anni as $val) {
      $dati['A.S. '.$val['anno'].'/'.($val['anno'] + 1)] = $val['anno'];
    }
    // restituisce dati formattati
    return $dati;
  }

  /**
   * Restituisce la lista degli avvisi in archivio che rispondono ai criteri di ricerca impostati
   *
   * @param array $criteri Criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Dati formattati come array associativo
   */
  public function listaArchivio(array $criteri, int $pagina): array {
    // query base
    $avvisi = $this->createQueryBuilder('a')
      ->where("a.stato='A' AND a.tipo IN ('C', 'E', 'U', 'A', 'I')")
      ->orderBy('a.data', 'DESC')
      ->addOrderBy('a.titolo', 'ASC');
    // filtro anno
    if ($criteri['anno'] > 0) {
      $avvisi
        ->andWhere('a.anno=:anno')
        ->setParameter('anno', $criteri['anno']);
    }
    // filtro autore
    if ($criteri['autore']) {
      $avvisi
        ->andWhere('a.autore=:autore')
        ->setParameter('autore', $criteri['autore']);
    }
    // filtro tipo
    if ($criteri['tipo']) {
      $avvisi
        ->andWhere('a.tipo=:tipo')
        ->setParameter('tipo', $criteri['tipo']);
    }
    // filtro mese
    if ($criteri['mese']) {
      $avvisi
        ->andWhere('MONTH(a.data)=:mese')
        ->setParameter('mese', $criteri['mese']);
    }
    // filtro oggetto
    if ($criteri['oggetto']) {
      $avvisi
        ->andWhere('a.titolo LIKE :oggetto')
        ->setParameter('oggetto', '%'.$criteri['oggetto'].'%');
    }
    // paginazione
    $dati = $this->paginazione($avvisi->getQuery(), $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce il numero di verifiche della classe per il mese indicato
   *
   * @param Classe $classe Classe di cui restituire le verifiche
   * @param DateTime $mese Mese di riferemento delle verifiche
   *
   * @return array Dati formattati come array associativo
   */
  public function numeroVerificheClasse(Classe $classe, DateTime $mese): array {
    $dati = [];
    // legge le verifiche
    $parametri = [new Parameter('mese', $mese->format('n')), new Parameter('anno', $mese->format('Y')),
      new Parameter('annoclasse', $classe->getAnno()), new Parameter('sezione', $classe->getSezione())];
    $sql = '';
    if (!empty($classe->getGruppo())) {
      $sql = " AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)";
      $parametri[] = new Parameter('gruppo', $classe->getGruppo());
    }
    $verifiche = $this->createQueryBuilder('a')
      ->select('COUNT(a.id) as num,a.data')
      ->join('a.cattedra', 'c')
      ->join('c.classe', 'cl')
      ->where("a.stato='P' AND a.tipo='V' AND MONTH(a.data)=:mese AND YEAR(a.data)=:anno AND cl.anno=:annoclasse AND cl.sezione=:sezione".$sql)
      ->groupBy('a.data')
      ->setParameters(new ArrayCollection($parametri))
      ->getQuery()
      ->getResult();
    foreach ($verifiche as $v) {
      $dati[$v['data']->format('j')] = $v['num'];
    }
    return $dati;
  }

  /**
   * Recupera gli avvisi destinati all'utente indicato
   *
   * @param array $criteri Criteri di ricerca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   * @param Utente $utente Utente a cui sono indirizzati gli avvisi
   *
   * @return array Dati formattati come array associativo
   */
  public function listaBacheca(array $criteri, int $pagina, Utente $utente): array {
    // query base
    $avvisi = $this->createQueryBuilder('a')
      ->select('a avviso,cu.letto')
      ->join(ComunicazioneUtente::class, 'cu', 'WITH', 'cu.comunicazione=a.id')
      ->where("a.stato='P' AND cu.utente=:utente")
      ->orderBy('a.data', 'DESC')
      ->addOrderBy('a.titolo', 'ASC')
			->setParameter('utente', $utente);
    // filtro visualizzazione
    if ($criteri['visualizza'] == 'D') {
      $avvisi->andWhere('cu.letto IS NULL');
    }
    // filtro mese
    if ($criteri['mese']) {
      $avvisi
        ->andWhere('MONTH(a.data)=:mese')
        ->setParameter('mese', $criteri['mese']);
    }
    // filtro oggetto
    if ($criteri['oggetto']) {
      $avvisi
        ->andWhere('a.titolo LIKE :oggetto')
        ->setParameter('oggetto', '%'.$criteri['oggetto'].'%');
    }
    // paginazione
    $dati = $this->paginazione($avvisi->getQuery(), $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera gli avvisi (non letti) destinati alla classe indicata
   *
   * @param Classe $classe Classe a cui sono indirizzati gli avvisi
   *
   * @return array Dati formattati come array associativo
   */
  public function classe(Classe $classe): array {
    // legge avvisi non letti
    $avvisi = $this->createQueryBuilder('a')
      ->join(ComunicazioneClasse::class, 'cc', 'WITH', 'cc.comunicazione=a.id')
      ->where("a.stato='P' AND cc.classe=:classe AND cc.letto IS NULL")
      ->orderBy('a.data', 'ASC')
      ->addOrderBy('a.titolo', 'ASC')
			->setParameter('classe', $classe)
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $avvisi;
  }

  /**
   * Controlla la presenza di avvisi non letti destinati agli alunni della classe indicata
   *
   * @param Classe $classe Classe a cui sono indirizzati gli avvisi
   *
   * @return int Numero di avvisi da leggere
   */
  public function numeroClasse(Classe $classe): int {
    // legge avvisi non letti
    $numero = $this->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join(ComunicazioneClasse::class, 'cc', 'WITH', 'cc.comunicazione=a.id')
      ->where("a.stato='P' AND cc.classe=:classe AND cc.letto IS NULL")
      ->orderBy('a.data', 'ASC')
			->setParameter('classe', $classe)
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce numero avvisi
    return $numero;
  }

  /**
   * Restituisce gli avvisi inseriti dal coordinatore per la classe indicata
   *
   * @param Classe $classe Classe di riferimento
   * @param int $pagina Pagina corrente
   *
   * @return array Dati formattati come array associativo
   */
  public function listaCoordinatore(Classe $classe, int $pagina): array {
    // legge avvisi
    $avvisi = $this->createQueryBuilder('a')
      ->join('a.classe', 'c')
      ->where("a.stato='P' AND a.tipo='O' AND c.id=:classe")
      ->orderBy('a.data', 'DESC')
      ->addOrderBy('a.titolo', 'ASC')
			->setParameter('classe', $classe);
    // paginazione
    $dati = $this->paginazione($avvisi->getQuery(), $pagina);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce verifiche o compiti previsti per lo stesso giorno di quello indicato nell'avviso.
   *
   * @param Avviso $avviso Avviso di riferimento, con l'indicazione del tipo di attività prevista
   *
   * @return array Dati formattati come array associativo
   */
  public function previsti(Avviso $avviso): array {
    // legge altre attività in stessa classe e stessa data
    $previsti = $this->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('c.classe', 'cl')
      ->where("a.stato='P' AND a.tipo=:tipo AND a.data=:data AND cl.anno=:anno AND cl.sezione=:sezione")
			->setParameter('tipo', $avviso->getTipo())
			->setParameter('data', $avviso->getData()->format('Y-m-d'))
			->setParameter('anno', $avviso->getCattedra()->getClasse()->getAnno())
			->setParameter('sezione', $avviso->getCattedra()->getClasse()->getSezione())
      ->orderBy('cl.anno,cl.sezione,cl.gruppo', 'ASC');
    if ($avviso->getId()) {
      // modifica di avviso esistente
      $previsti
        ->andWhere('a.id!=:avviso')
        ->setParameter('avviso', $avviso->getId());
    }
    $previsti = $previsti
      ->getQuery()
      ->getResult();
    // aggiunge info
    $dati = [];
    foreach ($previsti as $k => $attivita) {
      $dati['avvisi'][$k] = $attivita;
      $dati['destinatari'][$k] = '';
      if ($attivita->getAlunni() == 'U') {
        $dati['destinatari'][$k] = $this->getEntityManager()->getRepository(Alunno::class)
          ->listaAlunni($attivita->getFiltroAlunni(), 'gs-filtroAlunni-');
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera gli eventi per l'utente indicato relativamente al mese indicato
   *
   * @param Utente $utente Utente a cui sono indirizzati gli eventi
   * @param DateTime $mese Mese di riferemento degli eventi da recuperare
   *
   * @return array Dati formattati come array associativo
   */
  public function agendaEventi(Utente $utente, $mese) {
    $dati = [];
    // eventi per utente destinatario (attività/verifiche/compiti)
    $eventi = $this->createQueryBuilder('a')
      ->select('DISTINCT DAY(a.data) AS giorno,a.tipo')
      ->join(ComunicazioneUtente::class, 'cu', 'WITH', 'cu.comunicazione=a.id')
      ->where("a.stato='P' AND a.tipo IN ('A', 'V', 'P') AND MONTH(a.data)=:mese AND YEAR(a.data)=:anno AND cu.utente=:utente")
			->setParameter('mese', $mese->format('n'))
			->setParameter('anno', $mese->format('Y'))
			->setParameter('utente', $utente)
      ->getQuery()
      ->getArrayResult();
    foreach ($eventi as $evento) {
      $dati[(int) $evento['giorno']][$evento['tipo']] = 1;
    }
    // colloqui
    if ($utente instanceOf Docente || $utente instanceOf Genitore) {
      // query base
      $colloqui = $this->getEntityManager()->getRepository(RichiestaColloquio::class)->createQueryBuilder('rc')
        ->select('DISTINCT DAY(c.data) AS giorno')
        ->join('rc.colloquio', 'c')
        ->where("rc.stato='C' AND c.abilitato=1 AND MONTH(c.data)=:mese AND YEAR(c.data)=:anno")
        ->orderBy('c.data', 'ASC')
        ->setParameter('mese', $mese->format('n'))
        ->setParameter('anno', $mese->format('Y'));
      if ($utente instanceOf Docente) {
        // colloqui per il docente
        $colloqui
          ->andWhere('c.docente=:docente')
          ->setParameter('docente', $utente);
      } else {
        // colloqui per il genitore
        $colloqui
          ->andWhere('rc.alunno=:alunno AND rc.genitore=:genitore')
          ->setParameter('genitore', $utente)
          ->setParameter('alunno', $utente->getAlunno());
      }
      // legge dati
      $colloqui = $colloqui
        ->getQuery()
        ->getArrayResult();
      foreach ($colloqui as $colloquio) {
        $dati[(int) $colloquio['giorno']]['Q'] = 1;
      }
    }
    // verifiche/compiti per docente
    if ($utente instanceOf Docente) {
      // verifiche/compiti inseriti sulla cattedra del docente curricolare (da docente/compresenza/sostegno)
      $eventi1 = $this->createQueryBuilder('a')
        ->select('DISTINCT DAY(a.data) AS giorno,a.tipo')
        ->join('a.cattedra', 'c')
        ->join('c.materia', 'm')
        ->leftJoin(Cattedra::class, 'c2', 'WITH', "m.tipo!='S' AND c2.attiva=1 AND c2.classe=c.classe AND c2.materia=c.materia AND c2.docente=:docente")
        ->leftJoin(Cattedra::class, 'c3', 'WITH', "m.tipo='S' AND c3.attiva=1 AND c3.classe=c.classe AND c3.materia=a.materia AND c3.docente=:docente")
        ->where("a.stato='P' AND a.tipo IN ('V', 'P') AND MONTH(a.data)=:mese AND YEAR(a.data)=:anno AND (c2.id IS NOT NULL OR c3.id IS NOT NULL)")
        ->setParameter('mese', $mese->format('n'))
        ->setParameter('anno', $mese->format('Y'))
        ->setParameter('docente', $utente)
        ->getQuery()
        ->getArrayResult();
      // verifiche/compiti inseriti per l'alunno del docente di sostegno
      $eventi2 = $this->createQueryBuilder('a')
        ->select('DISTINCT DAY(a.data) AS giorno,a.tipo')
        ->join(ComunicazioneUtente::class, 'cu', 'WITH', 'cu.comunicazione=a.id')
        ->join('a.cattedra', 'c')
        ->join(Cattedra::class, 'c2', 'WITH', "c2.attiva=1 AND c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=cu.utente")
        ->where("a.stato='P' AND a.tipo IN ('V', 'P') AND MONTH(a.data)=:mese AND YEAR(a.data)=:anno")
        ->setParameter('mese', $mese->format('n'))
        ->setParameter('anno', $mese->format('Y'))
        ->setParameter('docente', $utente)
        ->getQuery()
        ->getArrayResult();
      foreach (array_merge($eventi1, $eventi2) as $evento) {
        $dati[(int) $evento['giorno']][$evento['tipo']] = 1;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i dettagli degli eventi per l'utente indicato relativamente alla data e tipo indicati
   *
   * @param DateTime $data Data di riferemento degli eventi da recuperare
   * @param string $tipo Tipo dell'evento [V=verifiche proprie, S=verifiche classe, P=compiti, A=attività, Q=colloqui]
   * @param Utente|null $utente Utente a cui sono indirizzati gli eventi (solo per alcuni tipi)
   * @param Classe|null $classe Classe di riferimento per gli eventi (solo per alcuni tipi)
   *
   * @return array Dati formattati come array associativo
   */
  public function listaAgendaEventi(DateTime $data, string $tipo, ?Utente $utente=null, ?Classe $classe=null): array {
    $dati = [];
    $dati['eventi'] = [];
    if (in_array($tipo, ['A', 'V', 'P'])) {
      // eventi per utente destinatario (attività/verifiche/compiti)
      $dati['eventi'] = $this->createQueryBuilder('a')
        ->join(ComunicazioneUtente::class, 'cu', 'WITH', 'cu.comunicazione=a.id')
        ->where("a.stato='P' AND a.tipo=:tipo AND a.data=:data AND cu.utente=:utente")
        ->setParameter('tipo', $tipo)
        ->setParameter('data', $data->format('Y-m-d'))
        ->setParameter('utente', $utente)
        ->getQuery()
        ->getResult();
      // verifiche/compiti per docente
      if (in_array($tipo, ['V', 'P']) && $utente instanceOf Docente) {
        // verifiche/compiti inseriti sulla cattedra del docente curricolare (da docente/compresenza/sostegno)
        $dati['eventi'] += $this->createQueryBuilder('a')
          ->join('a.cattedra', 'c')
          ->join('c.materia', 'm')
          ->leftJoin(Cattedra::class, 'c2', 'WITH', "m.tipo!='S' AND c2.attiva=1 AND c2.classe=c.classe AND c2.materia=c.materia AND c2.docente=:docente")
          ->leftJoin(Cattedra::class, 'c3', 'WITH', "m.tipo='S' AND c3.attiva=1 AND c3.classe=c.classe AND c3.materia=a.materia AND c3.docente=:docente")
          ->where("a.stato='P' AND a.tipo=:tipo AND a.data=:data AND (c2.id IS NOT NULL OR c3.id IS NOT NULL)")
          ->setParameter('tipo', $tipo)
          ->setParameter('data', $data->format('Y-m-d'))
          ->setParameter('docente', $utente)
          ->getQuery()
          ->getResult();
        // verifiche/compiti inseriti per l'alunno del docente di sostegno
        $dati['eventi'] += $this->createQueryBuilder('a')
          ->join(ComunicazioneUtente::class, 'cu', 'WITH', 'cu.comunicazione=a.id')
          ->join('a.cattedra', 'c')
          ->join(Cattedra::class, 'c2', 'WITH', "c2.attiva=1 AND c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=cu.utente")
          ->where("a.stato='P' AND a.tipo=:tipo AND a.data=:data")
          ->setParameter('tipo', $tipo)
          ->setParameter('data', $data->format('Y-m-d'))
          ->setParameter('docente', $utente)
          ->getQuery()
          ->getResult();
      }
    } elseif ($tipo == 'Q' && ($utente instanceOf Docente || $utente instanceOf Genitore)) {
      // colloqui
      $colloqui = $this->getEntityManager()->getRepository(RichiestaColloquio::class)->createQueryBuilder('rc')
        ->select('rc.id,rc.messaggio,rc.appuntamento,c.tipo,c.luogo,a.cognome,a.nome,a.sesso,a.dataNascita,cl.anno,cl.sezione,cl.gruppo,d.cognome AS cognomeDocente,d.nome AS nomeDocente,d.sesso AS sessoDocente')
        ->join('rc.colloquio', 'c')
        ->join('rc.alunno', 'a')
        ->join('a.classe', 'cl')
        ->join('c.docente', 'd')
        ->where("rc.stato='C' AND c.abilitato=1 AND c.data=:data")
        ->orderBy('rc.appuntamento,cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome,a.dataNascita')
        ->setParameter('data', $data->format('Y-m-d'));
      if ($utente instanceOf Docente) {
        // colloqui per il docente
        $colloqui
          ->andWhere('c.docente=:docente')
          ->setParameter('docente', $utente);
      } else {
        // colloqui per il genitore
        $colloqui
          ->andWhere('rc.alunno=:alunno AND rc.genitore=:genitore')
          ->setParameter('genitore', $utente)
          ->setParameter('alunno', $utente->getAlunno());
      }
      // legge dati
      $dati['eventi'] = $colloqui
        ->getQuery()
        ->getArrayResult();
    } elseif ($tipo == 'S') {
      // legge le verifiche di classe
      $parametri = [new Parameter('data', $data->format('Y-m-d')), new Parameter('anno', $classe->getAnno()),
        new Parameter('sezione', $classe->getSezione())];
      $sql = '';
      if (!empty($classe->getGruppo())) {
        $sql = " AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)";
        $parametri[] = new Parameter('gruppo', $classe->getGruppo());
      }
      $dati['eventi'] = $this->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->join('c.classe', 'cl')
        ->where("a.stato='P' AND a.tipo='V' AND a.data=:data AND cl.anno=:anno AND cl.sezione=:sezione".$sql)
        ->setParameters(new ArrayCollection($parametri))
        ->getQuery()
        ->getResult();
    }
    if ($tipo != 'Q') {
      foreach ($dati['eventi'] as $k => $evento) {
        $dati['destinatari'][$k] = '';
        $dati['classi'][$k] = '';
        if ($evento->getAlunni() == 'U') {
          $dati['destinatari'][$k] = $this->getEntityManager()->getRepository(Alunno::class)
            ->listaAlunni($evento->getFiltroAlunni(), '');
        } elseif ($evento->getAlunni() == 'C') {
          $dati['classi'][$k] = $this->getEntityManager()->getRepository(Classe::class)
            ->listaClassi($evento->getFiltroAlunni());
        }
      }
    }
    // restituisce dati
    return $dati;
  }

}
