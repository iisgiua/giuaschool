<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\Utente;
use App\Entity\Genitore;
use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\Staff;
use App\Entity\Ata;
use App\Entity\Assenza;
use App\Entity\Avviso;
use App\Entity\Circolare;
use App\Entity\RichiestaColloquio;


/**
 * NotificheUtil - classe di utilità per le funzioni sulle notifiche
 *
 * @author Antonello Dessì
 */
class NotificheUtil {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  public function __construct(
      private readonly RouterInterface $router,
      private readonly EntityManagerInterface $em,
      private readonly TranslatorInterface $trans,
      private readonly RequestStack $reqstack)
  {
  }

  /**
   * Restituisce le notifiche da mostrare in home
   *
   * @param Utente $utente Utente a cui sono destinate le notifiche
   *
   * @return array Dati restituiti come array associativo
   */
  public function notificheHome(Utente $utente) {
    $dati = [];
    $oggi = new \DateTime('today');
    $ora = new \DateTime('now');
    if ($utente instanceOf Genitore) {
      // notifiche per i genitori
      $dati['colloqui'] = null;
      $dati['avvisi'] = 0;
      $dati['circolari'] = 0;
      $dati['verifiche']['oggi'] = 0;
      $dati['verifiche']['prossime'] = 0;
      $dati['compiti']['oggi'] = 0;
      $dati['compiti']['domani'] = 0;
      $dati['giustificazioni'] = null;
      $alunno = $this->em->getRepository(\App\Entity\Alunno::class)->createQueryBuilder('a')
        ->join('a.classe', 'c')
        ->join(\App\Entity\Genitore::class, 'g', 'WITH', 'a.id=g.alunno')
        ->where('g.id=:genitore AND a.abilitato=:abilitato AND g.abilitato=:abilitato')
        ->setParameters(['genitore' => $utente, 'abilitato' => 1])
        ->getQuery()
        ->getOneOrNullResult();
      if ($alunno) {
        // legge colloqui da oggi a 3 giorni successivi
        $fine = (clone $oggi)->modify('+3 days');
        $dati['colloqui'] = $this->em->getRepository(\App\Entity\RichiestaColloquio::class)
          ->colloquiGenitore($oggi, $fine, $alunno, $utente);
        // legge avvisi
        $dati['avvisi'] = $this->numeroAvvisi($utente);
        // legge circolari
        $dati['circolari'] = $this->em->getRepository(\App\Entity\Circolare::class)->numeroCircolariUtente($utente);
        // legge verifiche
        $dati['verifiche'] = $this->numeroVerificheGenitori($alunno);
        // legge compiti
        $dati['compiti'] = $this->numeroCompitiGenitori($alunno);
        // legge assenze da giustificare
        $dati['giustificazioni'] = $this->em->getRepository(\App\Entity\Assenza::class)->assenzeIngiustificate($alunno);
      }
    } elseif ($utente instanceOf Alunno) {
      // legge avvisi
      $dati['avvisi'] = $this->numeroAvvisi($utente);
      // legge circolari
      $dati['circolari'] = $this->em->getRepository(\App\Entity\Circolare::class)->numeroCircolariUtente($utente);
      // legge verifiche
      $dati['verifiche'] = $this->numeroVerificheGenitori($utente);
      // legge compiti
      $dati['compiti'] = $this->numeroCompitiGenitori($utente);
      // legge assenze da giustificare
      $dati['giustificazioni'] = $this->em->getRepository(\App\Entity\Assenza::class)->assenzeIngiustificate($utente);
    } elseif ($utente instanceOf Docente) {
      // notifiche per i docenti
      $fine = (clone $oggi)->modify('+3 days');
      $dati['richieste'] = $this->em->getRepository(\App\Entity\RichiestaColloquio::class)->inAttesa($utente);
      $dati['colloqui'] = $this->em->getRepository(\App\Entity\RichiestaColloquio::class)
        ->colloquiDocente($oggi, $fine, $utente);
      // legge avvisi
      $dati['avvisi'] = $this->numeroAvvisi($utente);
      // legge circolari
      $dati['circolari'] = $this->em->getRepository(\App\Entity\Circolare::class)->numeroCircolariUtente($utente);
      // legge verifiche
      $dati['verifiche'] = $this->numeroVerifiche($utente);
      // legge compiti
      $dati['compiti'] = $this->numeroCompiti($utente);
      // legge moduli di richiesta per lo staff
      if ($utente instanceOf Staff) {
        $dati['moduli'] = $this->em->getRepository(\App\Entity\Richiesta::class)->contaNuove($utente);
      }
    } elseif ($utente instanceOf Ata) {
      // notifiche per gli ata
      $dati['avvisi'] = $this->numeroAvvisi($utente);
      $dati['circolari'] = $this->em->getRepository(\App\Entity\Circolare::class)->numeroCircolariUtente($utente);
    }
    return $dati;
  }

  /**
   * Controlla la presenza di nuovi avvisi destinati all'utente (escluse verifiche/compiti)
   *
   * @param Utente $utente Utente a cui sono indirizzati gli avvisi
   *
   * @return int Numero di avvisi da leggere
   */
  public function numeroAvvisi(Utente $utente) {
    // conta nuovi avvisi
    $avvisi = $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join(\App\Entity\AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
      ->where('au.utente=:utente AND au.letto is NULL')
      ->setParameters(['utente' => $utente])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce numero avvisi
    return $avvisi;
  }

  /**
   * Controlla la presenza di verifiche previste dal docente indicato
   *
   * @param Docente $docente Docente che ha inserito le verifiche
   *
   * @return array Lista verifiche previste
   */
  public function numeroVerifiche(Docente $docente) {
    // conta verifiche di oggi
    $ora = new \DateTime();
    $dati['oggi'] = 0;
    // verifiche per giorno di lezione
    $dati['oggi'] = $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->where('a.tipo=:tipo AND a.docente=:docente AND a.data=:oggi')
      ->setParameters(['tipo' => 'V', 'docente' => $docente, 'oggi' => $ora->format('Y-m-d')])
      ->getQuery()
      ->getSingleScalarResult();
    // aggiunge verifiche dell'alunno per cattedre di sostegno
    $dati['oggi'] += $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('a.cattedra', 'c')
      ->join(\App\Entity\AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
      ->join(\App\Entity\Cattedra::class, 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'data' => $ora->format('Y-m-d'), 'attiva' => 1])
      ->getQuery()
      ->getSingleScalarResult();
    // conta prossime verifiche
    $inizio = clone $ora;
    $inizio->modify('+1 day');
    $fine = clone $inizio;
    $fine->modify('+2 days');
    $dati['prossime'] = $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->where('a.tipo=:tipo AND a.docente=:docente AND a.data BETWEEN :inizio AND :fine')
      ->setParameters(['tipo' => 'V', 'docente' => $docente, 'inizio' => $inizio->format('Y-m-d'),
        'fine' => $fine->format('Y-m-d')])
      ->getQuery()
      ->getSingleScalarResult();
    // aggiunge verifiche dell'alunno per cattedre di sostegno
    $dati['prossime'] += $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('a.cattedra', 'c')
      ->join(\App\Entity\AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
      ->join(\App\Entity\Cattedra::class, 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data BETWEEN :inizio AND :fine AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'inizio' => $inizio->format('Y-m-d'),
        'fine' => $fine->format('Y-m-d'), 'attiva' => 1])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di verifiche previste per l'alunno
   *
   * @param Alunno $alunno Alunno del genitore a cui sono indirizzati gli avvisi
   *
   * @return array Lista verifiche previste
   */
  public function numeroVerificheGenitori(Alunno $alunno) {
    // conta verifiche di oggi
    $ora = new \DateTime();
    $dati['oggi'] = 0;
    // verifiche per giorno di lezione
    $dati['oggi'] = $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join(\App\Entity\AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND a.data=:oggi AND au.utente=:alunno')
      ->setParameters(['tipo' => 'V', 'oggi' => $ora->format('Y-m-d'), 'alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    // conta prossime verifiche
    $inizio = clone $ora;
    $inizio->modify('+1 day');
    $fine = clone $inizio;
    $fine->modify('+2 days');
    $dati['prossime'] = $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join(\App\Entity\AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND a.data BETWEEN :inizio AND :fine AND au.utente=:alunno')
      ->setParameters(['tipo' => 'V', 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d'),
        'alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di compiti assegati all'alunno
   *
   * @param Alunno $alunno Alunno del genitore a cui sono indirizzati gli avvisi
   *
   * @return array Lista compiti assegnati
   */
  public function numeroCompitiGenitori(Alunno $alunno) {
    // conta compiti di oggi
    $ora = new \DateTime();
    $dati['oggi'] = 0;
    // compiti per giorno di lezione
    $dati['oggi'] = $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join(\App\Entity\AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND a.data=:oggi AND au.utente=:alunno')
      ->setParameters(['tipo' => 'P', 'oggi' => $ora->format('Y-m-d'), 'alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    // conta compiti per il giorno dopo
    $domani = clone $ora;
    $domani->modify('+1 day');
    $dati['domani'] = $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join(\App\Entity\AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND a.data=:domani AND au.utente=:alunno')
      ->setParameters(['tipo' => 'P', 'domani' => $domani->format('Y-m-d'), 'alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di compiti previsti dal docente indicato
   *
   * @param Docente $docente Docente che ha inserito i compiti
   *
   * @return array Lista compiti previsti
   */
  public function numeroCompiti(Docente $docente) {
    // conta verifiche di oggi
    $ora = new \DateTime();
    $dati['oggi'] = 0;
    // compiti per giorno di lezione
    $dati['oggi'] = $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->where('a.tipo=:tipo AND a.docente=:docente AND a.data=:oggi')
      ->setParameters(['tipo' => 'P', 'docente' => $docente, 'oggi' => $ora->format('Y-m-d')])
      ->getQuery()
      ->getSingleScalarResult();
    // aggiunge compiti dell'alunno per cattedre di sostegno
    $dati['oggi'] += $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('a.cattedra', 'c')
      ->join(\App\Entity\AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
      ->join(\App\Entity\Cattedra::class, 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'data' => $ora->format('Y-m-d'), 'attiva' => 1])
      ->getQuery()
      ->getSingleScalarResult();
    // conta compiti per il giorno dopo
    $domani = clone $ora;
    $domani->modify('+1 day');
    $dati['domani'] = $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->where('a.tipo=:tipo AND a.docente=:docente AND a.data=:domani')
      ->setParameters(['tipo' => 'P', 'docente' => $docente, 'domani' => $domani->format('Y-m-d')])
      ->getQuery()
      ->getSingleScalarResult();
    // aggiunge compiti dell'alunno per cattedre di sostegno
    $dati['domani'] += $this->em->getRepository(\App\Entity\Avviso::class)->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('a.cattedra', 'c')
      ->join(\App\Entity\AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
      ->join(\App\Entity\Cattedra::class, 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:domani AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'domani' => $domani->format('Y-m-d'),
        'attiva' => 1])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

}
