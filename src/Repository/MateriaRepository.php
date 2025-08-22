<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Classe;
use App\Entity\Cattedra;
use Doctrine\ORM\EntityRepository;


/**
 * Materia - repository
 *
 * @author Antonello Dessì
 */
class MateriaRepository extends EntityRepository {

  /**
   * Trova una materia in base al nome normalizzato
   *
   * @param string $nome Nome normalizzato della materia (maiuscolo, senza spazi)
   *
   * @return array Lista di materie trovata
   */
  public function findByNomeNormalizzato($nome) {
    $query = $this->createQueryBuilder('m')
      ->where("UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(m.nome,' ',''),'''',''),',',''),'(',''),')','')) = :nome")
      ->setParameter(':nome', $nome)
      ->getQuery();
    return $query->getResult();
  }

  /**
   * Restituisce la lista degli ID di materia corretti o l'errore nell'apposito parametro.
   * Sono escluse la condotta e la sostituzione.
   *
   * @param array $lista Lista di ID delle materie, separata da virgole
   * @param bool $errore Viene impostato a vero se è presente un errore
   *
   * @return array Lista degli ID delle materie che risultano corretti
   */
  public function controllaMaterie($lista, &$errore) {
    // legge materie valide
    $materie = $this->createQueryBuilder('m')
      ->select('m.id')
      ->where('m.id IN (:lista) AND m.tipo!=:sostituzione AND m.tipo!=:condotta')
      ->setParameter('lista', $lista)
      ->setParameter('sostituzione', 'U')
      ->setParameter('condotta', 'C')
      ->getQuery()
      ->getArrayResult();
    $lista_materie = array_column($materie, 'id');
    $errore = (count($lista) != count($lista_materie));
    // restituisce materie valide
    return $lista_materie;
  }

  /**
   * Restituisce la rappresentazione testuale della lista delle materie.
   * Sono escluse la condotta e la sostituzione.
   *
   * @param array $lista Lista di ID delle materie
   *
   * @return string Lista delle materie
   */
  public function listaMaterie($lista) {
    // legge materie valide
    $materie = $this->createQueryBuilder('m')
      ->select('m.nome')
      ->where('m.id IN (:lista) AND m.tipo!=:sostituzione AND m.tipo!=:condotta')
      ->setParameter('lista', $lista)
      ->setParameter('sostituzione', 'U')
      ->setParameter('condotta', 'C')
      ->orderBy('m.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    $lista_materie = array_column($materie, 'nome');
    // restituisce lista
    return '&quot;'.implode('&quot;, &quot;', $lista_materie).'&quot;';
  }

  /**
   * Restituisce la lista delle materie, predisposta per le opzioni dei form
   *
   * @param bool|null $cattedra Usato per filtrare le materie utilizzabili in una cattedra; se nullo non filtra i dati
   * @param bool $breve Usato per utilizzare il nome breve delle materie
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioni(?bool $cattedra = true, $breve = true): array {
    // inizializza
    $dati = [];
    // legge dati
    $materie = $this->createQueryBuilder('m');
    if ($cattedra === true) {
      $materie = $materie->where("m.tipo IN ('N','R','S','E')");
    } elseif ($cattedra === false) {
      $materie = $materie->where("m.tipo NOT IN ('N','R','S','E')");
    }
    $materie = $materie
      ->orderBy('m.nome')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($materie as $materia) {
      $dati[$breve ? $materia->getNomeBreve() : $materia->getNome()] = $materia;
    }
    // restituisce lista opzioni
    return $dati;
  }

  /**
   * Restituisce la lista delle materie per la classe indicata
   *
   * @param Classe $classe Classe di cui recuperare le cattedre
   * @param bool $curricolari Se vero verranno restituite solo le materie curricolari (escluso sostegno e potenziamento)
   * @param bool $civica Se vero verrà restituita anche Educazione Civica
   * @param string $tipo Tipo di formattazione dei dati desiderata [Q=risultato query,C=form ChoiceType,A=array associativo,V=vettore di dati]
   *
   * @return array Dati formattati in un array associativo
   */
  public function materieClasse(Classe $classe, bool $curricolari=true, bool $civica=true, string $tipo='A'): array {
    $dati = [];
    // lista materie
    $materie = $this->createQueryBuilder('m')
      ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
      ->join('c.classe', 'cl')
      ->join('c.docente', 'd')
      ->where("c.attiva=1 AND d.abilitato=1 AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
      ->orderBy('m.nome', 'ASC')
      ->setParameter('anno', $classe->getAnno())
      ->setParameter('sezione', $classe->getSezione())
      ->setParameter('gruppo', $classe->getGruppo());
    if ($curricolari) {
      // esclude potenziamento e sostegno
      $materie->andWhere("m.tipo!='S' AND c.tipo!='P'");
    }
    if (!$civica) {
      // esclude civica
      $materie->andWhere("m.tipo!='E'");
    }
    $materie = $materie
      ->getQuery()
      ->getResult();
    // formato dati
    if ($tipo == 'Q') {
      // risultato query (vettore di oggetti)
      $dati = $materie;
    } elseif ($tipo == 'C') {
      // form ChoiceType
      foreach ($materie as $mat) {
        $dati[$mat->getNome()] = $mat;
      }
    } elseif ($tipo == 'V') {
      // vettore di dati
      $dati['lista'] = [];
      $dati['label'] = [];
      foreach ($materie as $idx => $mat) {
        $dati['lista'][$idx] = ['id' => $mat->getId(), 'tipo' => $mat->getTipo(),
          'nome' => $mat->getNome(), 'nomeBreve' => $mat->getNomeBreve(), 'valutazione' => $mat->getValutazione(),
          'media' => $mat->getMedia(), 'ordinamento' => $mat->getOrdinamento()];
        $dati['label'][$idx] = $mat->getNome();
      }
    } else {
      // array associativo
      $dati['choice'] = [];
      $dati['lista'] = [];
      foreach ($materie as $mat) {
        $dati['choice'][$mat->getNome()] = $mat;
        $dati['lista'][$mat->getId()]['object'] = $mat;
        $dati['lista'][$mat->getId()]['label'] = $mat->getNome();
      }
    }
    // restituisce dati
    return $dati;
  }

}
