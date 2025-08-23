<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Docente;


/**
 * Classe - repository
 *
 * @author Antonello Dessì
 */
class ClasseRepository extends BaseRepository {

  /**
   * Restituisce la lista degli ID di classe corretti o l'errore nell'apposito parametro.
   *
   * @param array $sedi Lista di ID delle sedi
   * @param array $lista Lista di ID delle classi
   * @param bool $errore Viene impostato a vero se è presente un errore
   *
   * @return array Lista degli ID delle classi che risultano corretti
   */
  public function controllaClassi($sedi, $lista, &$errore) {
    // legge classi valide
    $classi = $this->createQueryBuilder('c')
      ->select('c.id')
      ->where('c.id IN (:lista) AND c.sede IN (:sedi)')
			->setParameter('lista', $lista)
			->setParameter('sedi', $sedi)
      ->getQuery()
      ->getArrayResult();
    $listaClassi = array_column($classi, 'id');
    $errore = (count($lista) != count($listaClassi));
    // legge gruppi
    $classi = $this->createQueryBuilder('c')
      ->select('c.id AS classe,c2.id AS gruppo')
      ->leftJoin(Classe::class, 'c2', 'WITH', 'c2.id!=c.id AND c2.anno=c.anno AND c2.sezione=c.sezione')
      ->where("c.id IN (:lista) AND (c.gruppo IS NULL OR c.gruppo='') AND c2.id IS NOT NULL AND c2 NOT IN (:lista)")
			->setParameter('lista', $lista)
      ->getQuery()
      ->getArrayResult();
    // restituisce classi valide
    return array_merge($listaClassi, array_column($classi, 'gruppo'));;
  }

  /**
   * Restituisce la rappresentazione testuale della lista delle classi.
   *
   * @param array $lista Lista di ID delle classi
   *
   * @return string Lista delle classi
   */
  public function listaClassi($lista): string {
    // legge classi valide
    $classi = $this->createQueryBuilder('c')
      ->select("CONCAT(c.anno,'ª ',c.sezione) AS nome,c.gruppo")
      ->where('c.id IN (:lista)')
			->setParameter('lista', $lista)
      ->orderBy('c.sezione,c.anno,c.gruppo')
      ->getQuery()
      ->getArrayResult();
    $lista_classi = array_map(
      fn($c) => $c['nome'].($c['gruppo'] ? ('-'.$c['gruppo']) : ''), $classi);
    // restituisce lista
    return implode(', ', $lista_classi);
  }

  /**
   * Restituisce le classi per le sedi e il filtro indicato
   *
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   * @param array|null $filtro Lista di ID per il filtro classi o null se nessun filtro
   *
   * @return array Lista di ID delle classi
   */
  public function getIdClasse($sedi, $filtro) {
    // subquery per identificare classi articolate (cioè con stesso anno/sezione ma almeno un gruppo NON NULL)
    $subquery = $this->createQueryBuilder('c2')
      ->select('c2.id')
      ->where('c2.anno=c.anno AND c2.sezione=c.sezione AND c2.gruppo IS NOT NULL');
    // query base
    $classi = $this->createQueryBuilder('c')
      ->select('c.id')
      ->where('c.sede IN (:sedi) AND (c.gruppo IS NOT NULL OR (c.gruppo IS NULL AND NOT EXISTS('.$subquery->getDQL().')))')
			->setParameter('sedi', $sedi);
    if ($filtro) {
      // filtro classi
      $classi
        ->andWhere('c.id IN (:classi)')->setParameter('classi', $filtro);
    }
    $classi = $classi
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($classi, 'id');
  }

  /**
   * Restituisce la lista delle classi con coordinatori secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cercaCoordinatori($criteri, $pagina=1) {
    // crea query
    $query = $this->createQueryBuilder('c')
      ->join('c.coordinatore', 'd')
      ->join('c.sede', 's')
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND d.abilitato=:abilitato')
      ->orderBy('s.ordinamento,c.anno,c.sezione,c.gruppo', 'ASC')
      ->setParameter('nome', $criteri['nome'].'%')
      ->setParameter('cognome', $criteri['cognome'].'%')
      ->setParameter('abilitato', 1);
    if ($criteri['classe'] > 0) {
      $query
        ->andWhere('c.id=:classe')
        ->setParameter('classe', $criteri['classe']);
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce la lista delle classi con segretari secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cercaSegretari($criteri, $pagina=1) {
    // crea query
    $query = $this->createQueryBuilder('c')
      ->join('c.segretario', 'd')
      ->join('c.sede', 's')
      ->where('d.nome LIKE :nome AND d.cognome LIKE :cognome AND d.abilitato=:abilitato')
      ->orderBy('s.ordinamento,c.anno,c.sezione,c.gruppo', 'ASC')
      ->setParameter('nome', $criteri['nome'].'%')
      ->setParameter('cognome', $criteri['cognome'].'%')
      ->setParameter('abilitato', 1);
    if ($criteri['classe'] > 0) {
      $query
        ->andWhere('c.id=:classe')
        ->setParameter('classe', $criteri['classe']);
    }
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce le classi per le sedi e il filtro indicato relativo agli utenti alunni
   *
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   * @param array|null $filtro Lista di ID per il filtro classi o null se nessun filtro
   *
   * @return array Lista di ID delle classi
   */
  public function getIdClasseAlunni($sedi, $filtro) {
    $classi = $this->createQueryBuilder('c')
      ->select('DISTINCT c.id')
      ->where('c.sede IN (:sedi)')
			->setParameter('sedi', $sedi);
    if ($filtro) {
      // filtro alunni
      $classi
        ->join(Alunno::class, 'a', 'WITH', 'a.classe=c.id AND a.abilitato=:abilitato')
        ->andWhere('a.id IN (:lista)')
        ->setParameter('lista', $filtro)
        ->setParameter('abilitato', 1);
    }
    $classi = $classi
      ->getQuery()
      ->getArrayResult();
    // restituisce la lista degli ID
    return array_column($classi, 'id');
  }

  /**
   * Restituisce la lista ordinata delle classi
   *
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con la lista dei dati
   */
  public function cerca($pagina=1) {
    // crea query base
    $query = $this->createQueryBuilder('c')
      ->join('c.sede', 's')
      ->orderBy('s.ordinamento,c.sezione,c.anno,c.gruppo');
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

  /**
   * Restituisce la lista delle classi/gruppi, predisposta per le opzioni dei form
   *
   * @param int|null $sede Identificativo della sede, usato per filtrare le classi della sede indicata; se nullo non filtra i dati
   * @param bool $breve Se vero riporta solo la classe senza il corso, altrimenti riporta tutto
   * @param bool $ordAnno Se vero le classi sono ordinate per anno-sezione, altrimenti per sezione-anno
   * @param bool $articolate Se vero nelle classi articolate considera solo quelle con gruppi
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
public function opzioni(?int $sede = null, bool $breve=true, $ordAnno=true, bool $articolate=false): array {
    // inizializza
    $dati = [];
    // query base
    $classi = $this->createQueryBuilder('c')
      ->join('c.sede', 's');
    if ($sede) {
      $classi = $classi->where('c.sede = :sede')->setParameter('sede', $sede);
    }
    if ($articolate) {
      // subquery per identificare classi articolate (cioè con stesso anno/sezione ma almeno un gruppo NON NULL)
      $subquery = $this->createQueryBuilder('c2')
        ->select('c2.id')
        ->where('c2.anno=c.anno AND c2.sezione=c.sezione AND c2.gruppo IS NOT NULL');
      $classi
        ->andWhere('c.gruppo IS NOT NULL OR (c.gruppo IS NULL AND NOT EXISTS('.$subquery->getDQL().'))');
    }
    $classi = $classi
      ->orderBy('s.ordinamento,'.($ordAnno ? 'c.anno,c.sezione,c.gruppo' : 'c.sezione,c.gruppo,c.anno').',c.gruppo')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($classi as $classe) {
      $nome = $classe->getAnno().$classe->getSezione().
        ($classe->getGruppo() ? ('-'.$classe->getGruppo()) : '').
        ($breve ? '' : (' - '.$classe->getCorso()->getNomeBreve()));
      $dati[$classe->getSede()->getNomeBreve()][$nome] = $classe;
    }
    // restituisce lista opzioni
    return $dati;
  }

  /**
   * Restituisce la lista dei gruppi esistenti per la classe
   *
   * @param Classe $classe Classe da controllare
   * @param bool $oggetti Se vero restituisce lista di oggetti, altrimenti lista dei nomi dei gruppi
   *
   * @return array Lista dei gruppi classe esistenti
   */
  public function gruppi(Classe $classe, bool $oggetti = true): array {
    // legge gruppi
    $gruppi = $this->createQueryBuilder('c')
      ->where("c.anno=:anno AND c.sezione=:sezione AND c.gruppo != ''")
			->setParameter('anno', $classe->getAnno())
			->setParameter('sezione', $classe->getSezione())
      ->orderBy('c.gruppo')
      ->getQuery()
      ->getResult();
    // restituisce lista gruppi
    if ($oggetti) {
      // restituisce oggetti gruppo classe
      return $gruppi;
    }
    $dati = [];
    foreach ($gruppi as $gruppo) {
      $dati[] = $gruppo->getGruppo();
    }
    // restituisce lista di nomi
    return $dati;
  }

  /**
   * Restituisce la lista delle classi articolate presenti, con informazioni sui gruppi
   *
   * @param array $lista Lista di identificatori delle classi
   *
   * @return array Array associativo delle classi articolate trovate
   */
  public function classiArticolate(array $lista): array {
    $dati = [];
    // legge gruppi
    $classi = $this->createQueryBuilder('c')
      ->select('c.id AS classe,cl1.id as comune,cl2.id AS gruppo')
      ->leftJoin(Classe::class, 'cl1', 'WITH', 'cl1.id!=c.id AND cl1.anno=c.anno AND cl1.sezione=c.sezione AND cl1.gruppo IS NULL')
      ->leftJoin(Classe::class, 'cl2', 'WITH', 'cl1.id IS NULL AND cl2.id!=c.id AND cl2.anno=c.anno AND cl2.sezione=c.sezione AND cl2.gruppo IS NOT NULL')
      ->where('c.id IN (:lista) AND (cl1.id IS NOT NULL OR cl2.id IS NOT NULL)')
			->setParameter('lista', $lista)
      ->getQuery()
      ->getArrayResult();
    foreach ($classi as $classe) {
      if ($classe['comune']) {
        // info classe comune
        $dati[$classe['classe']]['comune'] = $classe['comune'];
      } else {
        // info gruppi classe
        $dati[$classe['classe']]['gruppi'][] = $classe['gruppo'];
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista delle classi del docente indicato
   *
   * @param Docente $docente Docente di cui recuperare le classi
   * @param string $tipo Tipo di formattazione dei dati desiderata [Q=risultato query,C=form ChoiceType,A=array associativo,V=vettore di dati]
   *
   * @return array Dati formattati in un array associativo
   */
  public function classiDocente(Docente $docente, $tipo='A'): array {
    $dati = [];
    // lista classi
    $classi = $this->createQueryBuilder('cl')
      ->join(Cattedra::class, 'c', 'WITH', 'c.classe=cl.id')
      ->where('c.docente=:docente AND c.attiva=1')
      ->orderBy('cl.anno,cl.sezione,cl.gruppo')
      ->setParameter('docente', $docente)
      ->getQuery()
      ->getResult();
    // formato dati
    if ($tipo == 'Q') {
      // risultato query (vettore di oggetti)
      $dati = $classi;
    } elseif ($tipo == 'C') {
      // form ChoiceType
      foreach ($classi as $classe) {
        $label = ''.$classe;
        $dati[$label] = $classe;
      }
    } elseif ($tipo == 'V') {
      // vettore di dati
      $dati['lista'] = [];
      $dati['label'] = [];
      foreach ($classi as $idx => $classe) {
        $label = ''.$classe;
        $dati['lista'][$idx] = ['id' => $classe->getId(), 'anno' => $classe->getAnno(),
          'sezione' => $classe->getSezione(), 'gruppo' => $classe->getGruppo(),
          'oreSettimanali' => $classe->getOreSettimanali(), 'sede' => $classe->getSede()->getNomeBreve(),
          'corso' => $classe->getCorso()->getNomeBreve(),
          'coordinatore' => $classe->getCoordinatore() ? ''.$classe->getCoordinatore() : '',
          'segretario' => $classe->getSegretario() ? ''.$classe->getSegretario() : ''];
        $dati['label'][$idx] = $label;
      }
    } else {
      // array associativo
      $dati['choice'] = [];
      $dati['lista'] = [];
      foreach ($classi as $classe) {
        $label = ''.$classe;
        $dati['choice'][$label] = $classe;
        $dati['lista'][$classe->getId()]['object'] = $classe;
        $dati['lista'][$classe->getId()]['label'] = $label;
      }
    }
    // restituisce dati
    return $dati;
  }

}
