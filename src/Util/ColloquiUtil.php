<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use DateTime;
use App\Entity\Festivita;
use App\Entity\Cattedra;
use App\Entity\RichiestaColloquio;
use IntlDateFormatter;
use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Colloquio;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Util\LogHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * ColloquiUtil - classe di utilità per la gestione dei colloqui
 *
 * @author Antonello Dessì
 */
class ColloquiUtil {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   */
  public function __construct(
      private readonly EntityManagerInterface $em,
      private readonly RequestStack $reqstack,
      private readonly TranslatorInterface $trans,
      private readonly LogHandler $dblogger)
  {
  }

  /**
   * Genera le date per i ricevimenti periodici. Esegue anche memorizzazione e log.
   *
   *  @param Docente $docente Docente che effettua il colloquio
   *  @param string $tipo Tipo di colloquio [P=in presenza, D=a distanza]
   *  @param string $frequenza Frequenza del ricevimento [S=settimanale, 1=prima settimana, 2=seconda settimana, 3=terza settimana, 4=ultima settimana]
   *  @param int $durata Durata di ogni colloquio
   *  @param int $giorno Giorno della settimana [0=domenica, 1=lunedì ... 6=sabato]
   *  @param DateTime $inizio Ora inizio ricevimento
   *  @param DateTime $fine Ora fine ricevimento
   *  @param string $luogo Luogo/link del colloquio
   *
   *  @return string|null Avviso su colloqui duplicati o null se tutto ok
   */
  public function generaDate(Docente $docente, string $tipo, string $frequenza, int $durata, int $giorno,
                             DateTime $inizio, DateTime $fine, string $luogo): ?string {
    // inizializza
    $week = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $avviso = null;
    // inizio e fine colloqui
    $dataInizio = new DateTime('tomorrow');
    $dataFine = (DateTime::createFromFormat('Y-m-d H:i:s',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/fine_colloqui').' 00:00:00'));
    if ($dataInizio > $dataFine) {
      // errore: date oltre il limite
      return 'exception.colloqui_sospesi';
    }
    // mesi colloqui bloccati
    $mesiBloccati = explode(',',
      (string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/mesi_colloqui'));
    // lista date possibili
    $lista = [];
    $successivo = 'next '.$week[$giorno];
    for ($giorno = (new DateTime('today'))->modify($successivo); $giorno <= $dataFine; $giorno->modify($successivo)) {
      if (in_array($giorno->format('n'), $mesiBloccati, true) ||
          $this->em->getRepository(Festivita::class)->giornoFestivo($giorno)) {
        // salta data
        continue;
      }
      // aggiunge data
      $lista[$giorno->format('n')][] = clone $giorno;
    }
    // seleziona date effettive
    $date = [];
    switch ($frequenza) {
      case '1': // prima settimana
        foreach ($lista as $mese => $val) {
          $date[$mese][] = $val[0];
        }
        break;
      case '2': // seconda settimana
        foreach ($lista as $mese => $val) {
          $i = 0;
          while (isset($val[$i]) && $val[$i]->format('j') <= 7) {
            $i++;
          }
          $date[$mese][] = $val[$i] ?? $val[$i - 1];
        }
        break;
      case '3': // terza settimana
        foreach ($lista as $mese => $val) {
          $i = 0;
          while (isset($val[$i]) && $val[$i]->format('j') <= 14) {
            $i++;
          }
          $date[$mese][] = $val[$i] ?? $val[$i - 1];
        }
        break;
      case '4': // ultima settimana
        foreach ($lista as $mese => $val) {
          $date[$mese][] = $val[count($val) - 1];
        }
        break;
      case 'S': // ogni settimana
        $date = $lista;
        break;
    }
    // crea ricevimenti
    foreach ($date as $mese => $val) {
      foreach ($val as $data) {
        $colloquio = (new Colloquio())
          ->setDocente($docente)
          ->setTipo($tipo)
          ->setData($data)
          ->setInizio($inizio)
          ->setFine($fine)
          ->setDurata($durata)
          ->setLuogo($luogo);
        $colloquio->setNumero($this->numeroColloqui($colloquio));
        $this->em->persist($colloquio);
        // controlla se esite già
        if ($this->em->getRepository(Colloquio::class)->sovrapposizione($docente, $data,
            $inizio, $fine)) {
          // avviso: sovrapposizione
          $this->em->remove($colloquio);
          $avviso = 'message.salta_colloqui_duplicati';
        } else {
          // memorizzazione
          $this->em->flush();
        }
      }
    }
    // restituisce eventuale avviso
    return $avviso;
  }

  /**
   * Calcola il numero di colloqui per il ricevimento indicato
   *
   *  @param Colloquio $colloquio Impostazioni del ricevimento
   *
   *  @return int Numero di colloqui possibili
   */
  public function numeroColloqui(Colloquio $colloquio): int {
    // calcola durata ricevimento
    $diff = $colloquio->getFine()->diff($colloquio->getInizio());
    $minuti = 60 * $diff->format('%h') + $diff->format('%i');
    // calcola numero colloqui
    $numero = (int) ($minuti / $colloquio->getDurata());
    // restituisce numero colloqui
    return $numero;
  }

  /**
   * Restituisce i dati dei docenti e le richieste per i colloqui individuali
   *
   * @param Classe $classe Classe dell'alunno
   * @param Alunno $alunno Alunno su cui fare i colloqui
   * @param Genitore $genitore Genitore che ha richiesto il colloquio
   *
   * @return array Dati restituiti come array associativo
   */
  public function colloquiGenitori(Classe $classe, Alunno $alunno, Genitore $genitore): array {
    $dati = [];
    // legge cattedre
    $dati['docenti'] = $this->em->getRepository(Cattedra::class)->cattedreClasse($classe, false);
    // legge richieste
    $dati['richieste'] = $this->em->getRepository(RichiestaColloquio::class)->richiesteAlunno($alunno,
      $genitore);
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce i dati dei ricevimenti (validi) del docente
   *
   * @param Docente $docente Docente di cui restituire le date di ricevimento
   *
   * @return array Dati restituiti come array associativo
   */
  public function dateRicevimento(Docente $docente): array {
    // legge date valide di ricevimento
    $inizio = new DateTime('tomorrow');
    $fine = (clone $inizio)->modify('last day of next month');
    $ricevimenti = $this->em->getRepository(Colloquio::class)->ricevimenti($docente, $inizio, $fine, true);
    $dati['validi'] = [];
    $dati['esauriti'] = [];
    foreach ($ricevimenti as $ricevimento) {
      if ($ricevimento['richieste'] < $ricevimento['ricevimento']->getNumero()) {
        $dati['validi'][$ricevimento['ricevimento']->getId()] = $ricevimento;
      } else {
        $dati['esauriti'][$ricevimento['ricevimento']->getId()] = $ricevimento;
      }
    }
    // legge date prossimi ricevimenti
    $fine->modify('+1 day');
    $dati['prossimi'] = $this->em->getRepository(Colloquio::class)->ricevimenti($docente, $fine, null, true);
    // crea lista per form
    $dati['lista'] = [];
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    foreach ($dati['validi'] as $colloquio) {
      $dataOra = $this->trans->trans('label.form_data_ricevimento', [
        'data' => ucwords($formatter->format($colloquio['ricevimento']->getData())),
        'inizio' => $colloquio['ricevimento']->getInizio()->format('G:i'),
        'fine' => $colloquio['ricevimento']->getFine()->format('G:i')]);
      $dati['lista'][$dataOra] = $colloquio['ricevimento']->getId();
    }
    // restuisce dati
    return $dati;
  }

}
