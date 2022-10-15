<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Sede;
use App\Entity\Classe;
use App\Entity\Assenza;
use App\Entity\Configurazione;
use App\Entity\Entrata;
use App\Entity\Lezione;
use App\Entity\Uscita;


/**
 * Festivita - repository
 *
 * @author Antonello Dessì
 */
class FestivitaRepository extends BaseRepository {

  /**
   * Indica se il giorno indicato è festivo (per tutta la scuola).
   * Include come festivo anche il periodo precedente all'inizio o successivo alla fine dell'anno scolastico.
   * Sono indicati come festivi i riposi settimanali (domenica ed eventuali altri) configurati nei parametri.
   *
   * @param DateTime $data Giorno da controllare
   *
   * @return bool Vero il giorno è festivo, falso altrimenti
   */
  public function giornoFestivo(\DateTime $data) {
    // controlla festività su tutta la scuola
    $festivo = $this->createQueryBuilder('f')
      ->select('COUNT(f.id)')
      ->where('f.data=:data AND f.tipo=:festivo AND f.sede IS NULL')
      ->setParameters(['data' => $data, 'festivo' => 'F'])
      ->getQuery()
      ->getSingleScalarResult();
    if ($festivo) {
      // giorno festivo
      return true;
    }
    // controlla giorni festivi settimanali
    $giorni = explode(',',
      $this->_em->getRepository('App\Entity\Configurazione')->getParametro('giorni_festivi_istituto', '0'));
    if (in_array($data->format('w'), $giorni, true)) {
      // giorno festivo
      return true;
    }
    // controlla se la data è al di fuori dell'anno scolastico
    $inizio = $this->_em->getRepository('App\Entity\Configurazione')->getParametro('anno_inizio', '0000-00-00');
    $fine = $this->_em->getRepository('App\Entity\Configurazione')->getParametro('anno_fine', '9999-99-99');
    $dataStr = $data->format('Y-m-d');
    if ($dataStr < $inizio || $dataStr > $fine) {
      // giorno festivo
      return true;
    }
    // giorno non festivo
    return false;
  }

  /**
   * Restituisce il successivo giorno di lezione, a partire dalla data indicata.
   *
   * @param \DateTime $data Data da controllare
   * @param Sede $sede Sede da controllare (se nullo, festività di entrambe le sedi)
   * @param Classe $classe Se indicata controlla giorni senza lezioni (chiusura scuola o situazioni anomale)
   *
   * @return \DateTime|null Giorno di lezione successivo, o nullo se non esiste
   */
  public function giornoSuccessivo(\DateTime $data, Sede $sede=null, Classe $classe=null) {
    // fine anno
    $fine = $this->_em->getRepository('App\Entity\Configurazione')->findOneByParametro('anno_fine');
    // controlla successivo
    $succ = clone $data;
    while ($fine && $succ->format('Y-m-d') < $fine->getValore()) {
      // giorno successivo
      $succ->modify('+1 day');
      // controllo riposo settimanale (domenica e altri)
      $weekdays = $this->_em->getRepository('App\Entity\Configurazione')->findOneByParametro('giorni_festivi_istituto');
      if ($weekdays && in_array($succ->format('w'), explode(',', $weekdays->getValore()))) {
        // festivo
        continue;
      }
      // controllo altre festività
      $cond = array('data' => $succ, 'sede' => $sede);
      if (count($this->findBy($cond))) {
        // festivo
        continue;
      }
      // controllo situazioni anomali
      if ($classe) {
        // controllo se giorno senza lezioni
        $lezioni = $this->_em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
          ->select('COUNT(l.id)')
          ->where('l.data=:data AND l.classe=:classe')
          ->setParameters(['data' => $succ, 'classe' => $classe])
          ->getQuery()
          ->getSingleScalarResult();
        if ($lezioni) {
          // ok, trovato
          return $succ;
        }
        // verifica assenze/ritardi/uscite non registrati
        $assenze = $this->_em->getRepository('App\Entity\Assenza')->createQueryBuilder('ass')
          ->select('COUNT(ass.id)')
          ->join('ass.alunno', 'a')
          ->where('ass.data=:data AND a.classe=:classe')
          ->setParameters(['data' => $succ, 'classe' => $classe])
          ->getQuery()
          ->getSingleScalarResult();
        $ritardi = $this->_em->getRepository('App\Entity\Entrata')->createQueryBuilder('e')
          ->select('COUNT(e.id)')
          ->join('e.alunno', 'a')
          ->where('e.data=:data AND a.classe=:classe')
          ->setParameters(['data' => $succ, 'classe' => $classe])
          ->getQuery()
          ->getSingleScalarResult();
        $uscite = $this->_em->getRepository('App\Entity\Uscita')->createQueryBuilder('u')
          ->select('COUNT(u.id)')
          ->join('u.alunno', 'a')
          ->where('u.data=:data AND a.classe=:classe')
          ->setParameters(['data' => $succ, 'classe' => $classe])
          ->getQuery()
          ->getSingleScalarResult();
        if (!$assenze && !$ritardi && !$uscite) {
          // giorno senza lezioni
          continue;
        }
      }
      // ok, trovato
      return $succ;
    }
    // errore, dopo fine a.s.
    return null;
  }

  /**
   * Restituisce il precedente giorno di lezione, a partire dalla data indicata.
   *
   * @param \DateTime $data Data da controllare
   * @param Sede $sede Sede da controllare (se nullo, festività di entrambe le sedi)
   * @param Classe $classe Se indicata controlla giorni senza lezioni (chiusura scuola o situazioni anomale)
   *
   * @return \DateTime|null Giorno di lezione precedente, o nullo se non esiste
   */
  public function giornoPrecedente(\DateTime $data, Sede $sede=null, Classe $classe=null) {
    // inizio anno
    $inizio = $this->_em->getRepository('App\Entity\Configurazione')->findOneByParametro('anno_inizio');
    // controlla precedente
    $prec = clone $data;
    while ($inizio && $prec->format('Y-m-d') > $inizio->getValore()) {
      // giorno successivo
      $prec->modify('-1 day');
      // controllo riposo settimanale (domenica e altri)
      $weekdays = $this->_em->getRepository('App\Entity\Configurazione')->findOneByParametro('giorni_festivi_istituto');
      if ($weekdays && in_array($prec->format('w'), explode(',', $weekdays->getValore()))) {
        // festivo
        continue;
      }
      // controllo altre festività
      $cond = array('data' => $prec, 'sede' => $sede, 'tipo' => 'F');
      if (count($this->findBy($cond))) {
        // festivo
        continue;
      }
      // controllo situazioni anomali
      if ($classe) {
        // controllo se giorno senza lezioni
        $lezioni = $this->_em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
          ->select('COUNT(l.id)')
          ->where('l.data=:data AND l.classe=:classe')
          ->setParameters(['data' => $prec, 'classe' => $classe])
          ->getQuery()
          ->getSingleScalarResult();
        if ($lezioni) {
          // ok, trovato
          return $prec;
        }
        // verifica assenze/ritardi/uscite non registrati
        $assenze = $this->_em->getRepository('App\Entity\Assenza')->createQueryBuilder('ass')
          ->select('COUNT(ass.id)')
          ->join('ass.alunno', 'a')
          ->where('ass.data=:data AND a.classe=:classe')
          ->setParameters(['data' => $prec, 'classe' => $classe])
          ->getQuery()
          ->getSingleScalarResult();
        $ritardi = $this->_em->getRepository('App\Entity\Entrata')->createQueryBuilder('e')
          ->select('COUNT(e.id)')
          ->join('e.alunno', 'a')
          ->where('e.data=:data AND a.classe=:classe')
          ->setParameters(['data' => $prec, 'classe' => $classe])
          ->getQuery()
          ->getSingleScalarResult();
        $uscite = $this->_em->getRepository('App\Entity\Uscita')->createQueryBuilder('u')
          ->select('COUNT(u.id)')
          ->join('u.alunno', 'a')
          ->where('u.data=:data AND a.classe=:classe')
          ->setParameters(['data' => $prec, 'classe' => $classe])
          ->getQuery()
          ->getSingleScalarResult();
        if (!$assenze && !$ritardi && !$uscite) {
          // giorno senza lezioni
          continue;
        }
      }
      // ok, trovato
      return $prec;
    }
    // errore, prima inizio a.s.
    return null;
  }

  /**
   * Restituisce la lista delle date dei giorni festivi.
   * Non sono considerate le assemblee di istituto (non sono giorni festivi).
   * Sono esclusi i giorni che precedono o seguono il periodo dell'anno scolastico.
   * Non sono indicati i riposi settimanali (domenica ed eventuali altri).
   *
   * @param string $format Formato delle date
   *
   * @return string Lista di giorni festivi come stringhe di date
   */
  public function listaFestivi($format='d/m/Y') {
    // legge date
    $lista = $this->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo')
      ->setParameters(['tipo' => 'F'])
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    // crea lista
    $lista_date = '';
    foreach ($lista as $f) {
      $lista_date .= ',"'.$f->getData()->format($format).'"';
    }
    return '['.substr($lista_date, 1).']';
  }

  /**
   * Restituisce la lista ordinata delle festività
   *
   * @param int $pagina Pagina corrente
   *
   * @return array Array associativo con la lista dei dati
   */
  public function cerca($pagina=1) {
    // crea query base
    $query = $this->createQueryBuilder('f')
      ->orderBy('f.data', 'ASC');
    // crea lista con pagine
    return $this->paginazione($query->getQuery(), $pagina);
  }

}
