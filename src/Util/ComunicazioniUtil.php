<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use App\Entity\Allegato;
use App\Entity\Annotazione;
use App\Entity\Alunno;
use App\Entity\Ata;
use App\Entity\Avviso;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Circolare;
use App\Entity\Comunicazione;
use App\Entity\ComunicazioneClasse;
use App\Entity\ComunicazioneUtente;
use App\Entity\Docente;
use App\Entity\Documento;
use App\Entity\Genitore;
use App\Entity\Materia;
use App\Entity\Sede;
use App\Entity\Staff;
use App\Entity\Utente;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Form\FormInterface;


/**
 * ComunicazioniUtil - classe di utilità per la gestione delle comunicazioni
 *
 * @author Antonello Dessì
 */
class ComunicazioniUtil {

  /**
   * @var string $dirClassi Percorso della directory per l'archivio delle classi
   */
  private $dirClassi;


  //==================== METODI GENERICI PER LE COMUNICAZIONI ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param string $dirTemp Percorso della directory per i file temporanei
   * @param string $dirArchivio Percorso della directory per l'archivio dei documenti
   * @param string $dirUpload Percorso della directory per i file di upload
   */
  public function __construct(
      private readonly EntityManagerInterface $em,
      private readonly PdfManager $pdf,
      private $dirTemp,
      $dirArchivio,
      private $dirUpload) {
    $this->dirClassi = $dirArchivio.'/classi';
  }

  /**
   * Esegue la conversione in formato PDF del file indicato (presente nella dir temporanea)
   *
   * @param string $nomefile File da convertire
   *
   * @return array Restituisce una lista con il nome del file e l'estensione
   */
  public function convertePdf($nomefile): array {
    $info = pathinfo($nomefile);
    $file = $info['filename'];
    $estensione = $info['extension'];
    if (strtolower($estensione) != 'pdf') {
      // conversione
      try {
        $proc = new Process(['/usr/bin/unoconv', '-f', 'pdf', '-d', 'document', $file.'.'.$estensione],
          $this->dirTemp);
        $proc->setTimeout(0);
        $proc->run();
        if ($proc->isSuccessful() && file_exists($this->dirTemp.'/'.$file.'.pdf')) {
          // conversione ok
          unlink($this->dirTemp.'/'.$file.'.'.$estensione);
          $estensione = 'pdf';
        }
      } catch (Exception) {
        // errore: non fa niente
      }
    }
    // restituisce file ed estensione di nuovo file
    return [$file, $estensione];
  }

  /**
   * Restituisce il nome di file normalizzato
   *
   * @param string $nome Nome di file da normalizzare
   *
   * @return string Nome di file normalizzato
   */
  public function normalizzaNome(string $nome): string {
    $testo = mb_strtoupper($nome, 'UTF-8');
    $testo = str_replace(['À', 'È', 'É', 'Ì', 'Ò', 'Ù'], ['A', 'E', 'E', 'I', 'O', 'U'], $testo);
    $testo = preg_replace('/\W+/','-', $testo);
    if (str_starts_with($testo, '-')) {
      $testo = substr($testo, 1);
    }
    if (str_ends_with($testo, '-')) {
      $testo = substr($testo, 0, -1);
    }
    return $testo;
  }

  /**
   * Controlla se l'utente è autorizzato alla lettura della comunicazione
   *
   * @param Utente $utente Utente da controllare
   * @param Comunicazione $comunicazione Comunicazione da controllare
   *
   * @return bool Restituisce vero se l'utente è autorizzato alla lettura, falso altrimenti
   */
  public function permessoLettura(Utente $utente, Comunicazione $comunicazione): bool  {
    if ($utente->getId() == $comunicazione->getAutore()->getId() ||
        ($utente instanceOf Staff)) {
      // utente è autore di documento o fa parte di staff: ok
      return true;
    }
    if (!empty($this->em->getRepository(ComunicazioneUtente::class)->findOneBy([
               'comunicazione' => $comunicazione, 'utente' => $utente]))) {
      // utente è tra i destinatari: ok
      return true;
    }
    // controllo specifico per documenti
    if ($comunicazione instanceOf Documento) {
      if (in_array($comunicazione->getTipo(), ['B', 'H', 'D', 'C']) && ($utente instanceOf Docente) &&
          $utente->getResponsabileBes() &&
          (!$utente->getResponsabileBesSede() || !$comunicazione->getAlunno()->getClasse() ||
          $utente->getResponsabileBesSede()->getId() == $comunicazione->getAlunno()->getClasse()->getSede()->getId())) {
        // documento PEI/PDP/diagnosi/altro e responsabile BES di scuola o di stessa sede di alunno: ok
        return true;
      }
    }
    // controllo specifico per circolari/avvisi
    if (($comunicazione instanceOf Circolare) || ($comunicazione instanceOf Avviso)) {
      if ($utente instanceOf Docente &&
          $this->em->getRepository(ComunicazioneClasse::class)->findOneByComunicazione($comunicazione)) {
        // utente è docente e circolare/avviso è destinata a classe: ok
        return true;
      }
    }
    // non autorizzato
    return false;
  }

  /**
   * Segna la lettura della comunicazione da parte di un utente (non memorizza in modo persistente)
   *
   * @param Utente $utente Utente che esegue la lettura
   * @param Comunicazione $comunicazione Comunicazione letta
   */
  public function leggeUtente(Utente $utente, Comunicazione $comunicazione): void {
    // dati lettura utente
    $cu = $this->em->getRepository(ComunicazioneUtente::class)->findOneBy([
      'comunicazione' => $comunicazione, 'utente' => $utente]);
    if ($cu && !$cu->getLetto()) {
      // imposta lettura
      $cu->setLetto(new DateTime());
    }
  }

  /**
   * Cancella i destinatari di una comunicazione
   *
   * @param Comunicazione $comunicazione Documento di cui cancellare i destinatari
   */
  public function cancellaDestinatari(Comunicazione $comunicazione): void {
    // cancella utenti in lista
    $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->delete()
      ->where('cu.comunicazione=:comunicazione')
			->setParameter('comunicazione', $comunicazione)
      ->getQuery()
      ->execute();
    // cancella classi in lista
    $this->em->getRepository(ComunicazioneClasse::class)->createQueryBuilder('cc')
      ->delete()
      ->where('cc.comunicazione=:comunicazione')
			->setParameter('comunicazione', $comunicazione)
      ->getQuery()
      ->execute();
  }

  /**
   * Imposta i destinatari di una comunicazione
   *
   * @param Comunicazione $comunicazione Comunicazione di cui impostare i destinatari
   */
  public function impostaDestinatari(Comunicazione $comunicazione): void {
    // inizializzazione
    $utenti = [];
    $classi = [];
    $sedi = array_map(fn($o) => $o->getId(), $comunicazione->getSedi()->toArray());
    // speciali
    if (str_contains($comunicazione->getSpeciali(), 'D')) {
      // aggiunge DSGA
      $utenti = array_merge($utenti, $this->em->getRepository(Ata::class)->getIdDsga());
    }
    if (str_contains($comunicazione->getSpeciali(), 'S')) {
      // aggiunge RSPP
      $utenti = array_merge($utenti, $this->em->getRepository(Docente::class)->getIdRspp());
    }
    $rappresentanti = array_intersect(['R', 'I', 'P'], str_split($comunicazione->getSpeciali()));
    if (!empty($rappresentanti)) {
      // aggiunge rappresentanti
      $utenti = array_merge($utenti, $this->em->getRepository(Utente::class)->getIdRappresentanti($rappresentanti));
    }
    // ATA
    $ata = array_intersect(['A', 'T', 'C'], str_split($comunicazione->getAta()));
    if (!empty($ata)) {
      // aggiunge ATA
      $utenti = array_merge($utenti, $this->em->getRepository(Ata::class)->getIdCategorieAta($ata, $sedi));
    }
    // coordinatori
    if ($comunicazione->getCoordinatori() != 'N') {
      // aggiunge coordinatori
      $utenti = array_merge($utenti, $this->em->getRepository(Docente::class)
        ->getIdCoordinatore($sedi, $comunicazione->getCoordinatori() == 'C' ?
          $comunicazione->getFiltroCoordinatori() : null));
    }
    // docenti
    if ($comunicazione->getDocenti() != 'N') {
      // controllo classi
      $filtroClassi = [];
      if ($comunicazione->getDocenti() == 'C') {
        $filtroClassi = $comunicazione->getFiltroDocenti();
        $articolate = $this->em->getRepository(Classe::class)->classiArticolate($filtroClassi);
        foreach ($articolate as $articolata) {
          if (!empty($articolata['comune'])) {
            // se gruppo classe aggiunge docenti comuni
            $filtroClassi[] = $articolata['comune'];
          } else {
            // se classe comune aggiunge docenti di tutti i gruppi
            $filtroClassi = array_merge($filtroClassi, $articolata['gruppi']);
          }
        }
      }
      // aggiunge docenti
      $utenti = array_merge($utenti, $this->em->getRepository(Docente::class)
        ->getIdDocente($sedi, $comunicazione->getDocenti(),
          $comunicazione->getDocenti() == 'C' ? $filtroClassi : $comunicazione->getFiltroDocenti()));
    }
    // genitori
    if ($comunicazione->getGenitori() != 'N') {
      // aggiunge genitori
      $utenti = array_merge($utenti, $this->em->getRepository(Genitore::class)
        ->getIdGenitore($sedi, $comunicazione->getGenitori(), $comunicazione->getFiltroGenitori()));
      if ($comunicazione->getGenitori() != 'U') {
        // aggiunge classi
        $classi = array_merge($classi, $this->em->getRepository(Classe::class)
          ->getIdClasse($sedi, $comunicazione->getGenitori() == 'C' ? $comunicazione->getFiltroGenitori() : null));
      }
    }
    // rappresentanti dei genitori
    if ($comunicazione->getRappresentantiGenitori() != 'N') {
      // aggiunge rappresentanti genitori
      $utenti = array_merge($utenti, $this->em->getRepository(Utente::class)
        ->getIdRappresentantiClasse(['L'], $sedi, $comunicazione->getRappresentantiGenitori(),
        $comunicazione->getFiltroRappresentantiGenitori()));
    }
    // alunni
    if ($comunicazione->getAlunni() != 'N') {
      // aggiunge alunni
      $utenti = array_merge($utenti, $this->em->getRepository(Alunno::class)
        ->getIdAlunno($sedi, $comunicazione->getAlunni(), $comunicazione->getFiltroAlunni()));
      if ($comunicazione->getAlunni() != 'U') {
        // aggiunge classi
        $classi = array_merge($classi, $this->em->getRepository(Classe::class)
          ->getIdClasse($sedi, $comunicazione->getAlunni() == 'C' ? $comunicazione->getFiltroAlunni() : null));
      }
    }
    // rappresentanti degli alunni
    if ($comunicazione->getRappresentantiAlunni() != 'N') {
      // aggiunge rappresentanti alunni
      $utenti = array_merge($utenti, $this->em->getRepository(Utente::class)
        ->getIdRappresentantiClasse(['S'], $sedi, $comunicazione->getRappresentantiAlunni(),
        $comunicazione->getFiltroRappresentantiAlunni()));
    }
    // destinatari univoci
    $utenti = array_unique($utenti);
    $classi = array_unique($classi);
    // imposta utenti destinatari
    foreach ($utenti as $utente) {
      $obj = (new ComunicazioneUtente())
        ->setComunicazione($comunicazione)
        ->setUtente($this->em->getReference(Utente::class, $utente));
      $this->em->persist($obj);
    }
    // imposta classi destinatarie
    foreach ($classi as $classe) {
      $obj = (new ComunicazioneClasse())
        ->setComunicazione($comunicazione)
        ->setClasse($this->em->getReference(Classe::class, $classe));
      $this->em->persist($obj);
    }
  }

  /**
   * Codifica i file allegati alla comunicazione
   * NB: si presuppone che tutti i file allegati siano in formato PDF
   *
   * @param Comunicazione $comunicazione Comunicazione da codificare
   * @param string $dir Percorso del file da cifrare
   *
   * @return boolean Vero se la codifica è avvenuta correttamente, falso altrimenti
   */
  public function codificaPdf(Comunicazione $comunicazione, $dir): bool {
    // crea password
    $minuscolo = "abcdefghijklmnopqrstuvwxyz";
    $maiuscolo = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $cifre = "1234567890";
    $password = substr(str_shuffle($minuscolo), 0, 5).substr(str_shuffle($maiuscolo), 0, 5).
      substr(str_shuffle($cifre), 0, 5);
    $password = substr(str_shuffle($password), 0, 10);
    // imposta password per i file del documento
    $comunicazione->setCifrato($password);
    foreach ($comunicazione->getAllegati() as $file) {
      $percorso = $dir.'/'.$file->getFile().'.'.$file->getEstensione();
      if ($this->pdf->import($percorso)) {
        $this->pdf->protect($password);
        $this->pdf->save($percorso);
        $file->setDimensione(filesize($percorso));
      } else {
        // errore di codifica
        return false;
      }
    }
    // tutto ok
    return true;
  }

  /**
   * Restituisce le informazioni da visualizzare sui destinatari scelti
   *
   * @param Comunicazione $comunicazione Comunicazione da controllare
   *
   * @return array Dati formattati come array associativo
   */
  public function infoDestinatari(Comunicazione $comunicazione): array {
    // init
    $dati = [];
    // visualizzazione filtro coordinatori
    $dati['coordinatori'] = ($comunicazione->getCoordinatori() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($comunicazione->getFiltroCoordinatori()) :
      '');
    // visualizzazione filtro docenti
    $dati['docenti'] = ($comunicazione->getDocenti() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($comunicazione->getFiltroDocenti()) :
      ($comunicazione->getDocenti() == 'M' ?
      $this->em->getRepository(Materia::class)->listaMaterie($comunicazione->getFiltroDocenti()) :
      ($comunicazione->getDocenti() == 'U' ?
      $this->em->getRepository(Docente::class)->listaDocenti($comunicazione->getFiltroDocenti(), 'gs-filtroDocenti-') :
      '')));
    // visualizzazione filtro genitori
    $dati['genitori'] = ($comunicazione->getGenitori() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($comunicazione->getFiltroGenitori()) :
      ($comunicazione->getGenitori() == 'U' ?
      $this->em->getRepository(Alunno::class)->listaAlunni($comunicazione->getFiltroGenitori(), 'gs-filtroGenitori-') :
      ''));
    // visualizzazione filtro alunni
    $dati['alunni'] = ($comunicazione->getAlunni() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($comunicazione->getFiltroAlunni()) :
      ($comunicazione->getAlunni() == 'U' ?
      $this->em->getRepository(Alunno::class)->listaAlunni($comunicazione->getFiltroAlunni(), 'gs-filtroAlunni-') :
      ''));
    // visualizzazione filtro rappresentanti dei genitori
    $dati['rappresentantiGenitori'] = ($comunicazione->getRappresentantiGenitori() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($comunicazione->getFiltroRappresentantiGenitori()) :
      ($comunicazione->getRappresentantiGenitori() == 'U' ?
      $this->em->getRepository(Alunno::class)->listaAlunni($comunicazione->getFiltroRappresentantiGenitori(), 'gs-filtroRappresentantiGenitori-') :
      ''));
    // visualizzazione filtro rappresentanti degli alunni
    $dati['rappresentantiAlunni'] = ($comunicazione->getRappresentantiAlunni() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($comunicazione->getFiltroRappresentantiAlunni()) :
      ($comunicazione->getRappresentantiAlunni() == 'U' ?
      $this->em->getRepository(Alunno::class)->listaAlunni($comunicazione->getFiltroRappresentantiAlunni(), 'gs-filtroRappresentantiAlunni-') :
      ''));
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la coerenza dei dati inseriti ed eventualmente restituisce il codice di errore
   *
   * @param Comunicazione $comunicazione Comunicazione da controllare
   * @param Docente $docente docente che inserisce la comunicazione
   *
   * @return int Codice di errore o 0 se tutto ok
   */
  public function validaComunicazione(Comunicazione $comunicazione, Docente $docente): int {
    // controllo data
    if (!$comunicazione->getData()) {
      // data non presente
      return 1;
    }
    // controllo titolo
    if (!$comunicazione->getTitolo()) {
      // titolo non presente
      return 2;
    }
    // controllo sedi
    if (count($comunicazione->getSedi()) == 0) {
      // sede non definita
      return 3;
    }
    if ($docente instanceOf Staff && $docente->getSede() &&
        (count($comunicazione->getSedi()) > 1 ||
        $comunicazione->getSedi()[0]->getId() != $docente->getSede()->getId())) {
      // sede non ammessa
      return 4;
    }
    // controllo destinatari
    if (empty($comunicazione->getSpeciali()) && empty($comunicazione->getAta()) &&
        $comunicazione->getCoordinatori() == 'N' && $comunicazione->getDocenti() == 'N' &&
        $comunicazione->getGenitori() == 'N' && $comunicazione->getAlunni() == 'N' &&
        $comunicazione->getRappresentantiGenitori() == 'N' && $comunicazione->getRappresentantiAlunni() == 'N' &&
        empty($comunicazione->getEsterni())) {
      // destinatari non definiti
      return 5;
    }
    // controllo filtro coordinatori
    $sedi = $comunicazione->getSedi();
    $errore = false;
    if ($comunicazione->getCoordinatori() == 'C') {
      // controllo classi
      $this->em->getRepository(Classe::class)
        ->controllaClassi($sedi, $comunicazione->getFiltroCoordinatori(), $errore);
      if ($errore) {
        // classe non valida per i coordinatori
        return 6;
      }
    }
    // controllo filtro docenti
    if ($comunicazione->getDocenti() == 'C') {
      // controllo classi
      $this->em->getRepository(Classe::class)
        ->controllaClassi($sedi, $comunicazione->getFiltroDocenti(), $errore);
      if ($errore) {
        // classe non valida per i docenti
        return 7;
      }
    } elseif ($comunicazione->getDocenti() == 'M') {
      // controllo materie
      $this->em->getRepository(Materia::class)->controllaMaterie($comunicazione->getFiltroDocenti(), $errore);
      if ($errore) {
        // materia non valida per i docenti
        return 8;
      }
    } elseif ($comunicazione->getDocenti() == 'U') {
      // controllo utenti
      $this->em->getRepository(Docente::class)
        ->controllaDocenti($sedi, $comunicazione->getFiltroDocenti(), $errore);
      if ($errore) {
        // utente non valido per i docenti
        return 9;
      }
    }
    // controllo filtro genitori
    if ($comunicazione->getGenitori() == 'C') {
      // controllo classi
      $this->em->getRepository(Classe::class)
        ->controllaClassi($sedi, $comunicazione->getFiltroGenitori(), $errore);
      if ($errore) {
        // classe non valida per i genitori
        return 10;
      }
    } elseif ($comunicazione->getGenitori() == 'U') {
      // controllo utenti
      $this->em->getRepository(Alunno::class)
        ->controllaAlunni($sedi, $comunicazione->getFiltroGenitori(), $errore);
      if ($errore) {
        // utente non valido per i genitori
        return 11;
      }
    }
    // controllo filtro alunni
    if ($comunicazione->getAlunni() == 'C') {
      // controllo classi
      $this->em->getRepository(Classe::class)
        ->controllaClassi($sedi, $comunicazione->getFiltroAlunni(), $errore);
      if ($errore) {
        // classe non valida per gli alunni
        return 12;
      }
    } elseif ($comunicazione->getAlunni() == 'U') {
      // controllo utenti
      $this->em->getRepository(Alunno::class)
        ->controllaAlunni($sedi, $comunicazione->getFiltroAlunni(), $errore);
      if ($errore) {
        // utente non valido per gli alunni
        return 13;
      }
    }
    // controllo filtro rappresentanti dei genitori
    if ($comunicazione->getRappresentantiGenitori() == 'C') {
      // controllo classi
      $this->em->getRepository(Classe::class)
        ->controllaClassi($sedi, $comunicazione->getFiltroRappresentantiGenitori(), $errore);
      if ($errore) {
        // classe non valida per i rappresentanti dei genitori
        return 14;
      }
    }
    // controllo filtro rappresentanti degli alunni
    if ($comunicazione->getRappresentantiAlunni() == 'C') {
      // controllo classi
      $this->em->getRepository(Classe::class)
        ->controllaClassi($sedi, $comunicazione->getFiltroRappresentantiAlunni(), $errore);
      if ($errore) {
        // classe non valida per i rappresentanti degli alunni
        return 15;
      }
    }
    // controlla esterni
    $esterni = [];
    foreach ($comunicazione->getEsterni() as $val) {
      $val = strtoupper(trim((string) $val));
      if (!empty($val)) {
        $esterni[] = $val;
      }
    }
    if (count($esterni) != count($comunicazione->getEsterni())) {
      // lista altri non valida
      return 16;
    }
    $comunicazione->setEsterni($esterni);
    // nessun errore
    return 0;
  }

  /**
   * Aggiunge allegati alla comunicazione
   *
   * @param Comunicazione $comunicazione Comunicazione a cui aggiungere gli allegati
   * @param array $file Lista dei file allegati da aggiungere
   */
  public function aggiungiAllegati(Comunicazione $comunicazione, string $dir, array $file): void {
    $fs = new Filesystem();
    // cancella dati allegati esistenti
    $comunicazione->setAllegati(new ArrayCollection());
    // aggiunge allegati (esistenti e nuovi) mantenendo l'ordine di inserimento
    foreach ($file as $f) {
      switch ($f['type']) {
        case 'removed':
          // rimuove documento
          $fs->remove($dir.'/'.$f['temp'].'.'.$f['ext']);
          break;
        case 'existent':
          // aggiunge allegato esistente
          $nome = $this->normalizzaNome($f['name']);
          $allegato = (new Allegato)
            ->setTitolo($f['name'])
            ->setNome($nome)
            ->setFile($f['temp'])
            ->setEstensione($f['ext'])
            ->setDimensione($f['size']);
          $this->em->persist($allegato);
          $comunicazione->addAllegato($allegato);
          break;
        case 'uploaded':
          // aggiunge nuovo allegato
          $nome = $this->normalizzaNome($f['name']);
          $allegato = (new Allegato)
            ->setTitolo($f['name'])
            ->setNome($nome)
            ->setFile($f['temp'])
            ->setEstensione($f['ext'])
            ->setDimensione($f['size']);
          $this->em->persist($allegato);
          $comunicazione->addAllegato($allegato);
          $fs->rename($this->dirTemp.'/'.$f['temp'].'.'.$f['ext'], $dir.'/'.$allegato->getFile().'.'.$f['ext']);
          break;
      }
    }
  }

 /**
   * Restituisce i dettagli della comunicazione
   *
   * @param Comunicazione $comunicazione Comunicazione da esaminare
   *
   * @return array Dati formattati come array associativo
   */
  public function dettagli(Comunicazione $comunicazione): array {
    $dati = [];
    $dati['coordinatori'] = '';
    $dati['docenti'] = '';
    $dati['genitori'] = '';
    $dati['alunni'] = '';
    $dati['rappresentantiGenitori'] = '';
    $dati['rappresentantiAlunni'] = '';
    // coordinatori
    if ($comunicazione->getCoordinatori() == 'C') {
      $dati['coordinatori'] = $this->em->getRepository(Classe::class)
        ->listaClassi($comunicazione->getFiltroCoordinatori());
    }
    // docenti
    if ($comunicazione->getDocenti() == 'C') {
      $dati['docenti'] = $this->em->getRepository(Classe::class)
        ->listaClassi($comunicazione->getFiltroDocenti());
    } elseif ($comunicazione->getDocenti() == 'M') {
      $dati['docenti'] = $this->em->getRepository(Materia::class)
        ->listaMaterie($comunicazione->getFiltroDocenti());
    } elseif ($comunicazione->getDocenti() == 'U') {
      $dati['docenti'] = $this->em->getRepository(Docente::class)
        ->listaDocenti($comunicazione->getFiltroDocenti(), 'gs-docenti-');
    }
    // genitori
    if ($comunicazione->getGenitori() == 'C') {
      $dati['genitori'] = $this->em->getRepository(Classe::class)
        ->listaClassi($comunicazione->getFiltroGenitori());
    } elseif ($comunicazione->getGenitori() == 'U') {
      $dati['genitori'] = $this->em->getRepository(Alunno::class)
        ->listaAlunni($comunicazione->getFiltroGenitori(), 'gs-genitori-');
    }
    // alunni
    if ($comunicazione->getAlunni() == 'C') {
      $dati['alunni'] = $this->em->getRepository(Classe::class)
        ->listaClassi($comunicazione->getFiltroAlunni());
    } elseif ($comunicazione->getAlunni() == 'U') {
      $dati['alunni'] = $this->em->getRepository(Alunno::class)
        ->listaAlunni($comunicazione->getFiltroAlunni(), 'gs-alunni-');
    }
    // rappresentanti genitori
    if ($comunicazione->getRappresentantiGenitori() == 'C') {
      $dati['rappresentantiGenitori'] = $this->em->getRepository(Classe::class)
        ->listaClassi($comunicazione->getFiltroRappresentantiGenitori());
    }
    // rappresentanti alunni
    if ($comunicazione->getRappresentantiAlunni() == 'C') {
      $dati['rappresentantiAlunni'] = $this->em->getRepository(Classe::class)
        ->listaClassi($comunicazione->getFiltroRappresentantiAlunni());
    }
    // statistiche di lettura
    if ($comunicazione->getStato() == 'P') {
      $dati['statistiche'] = $this->em->getRepository(ComunicazioneUtente::class)->statistiche($comunicazione);
    }
    // restituisce dati
    return $dati;
  }


  //==================== METODI SPECIFICI PER DOCUMENTI/CIRCOLARI/AVVISI ====================

  /**
   * Recupera i piani di lavoro del docente indicato
   *
   * @param Docente $docente Docente selezionato
   *
   * @return array Dati formattati come array associativo
   */
  public function pianiDocente(Docente $docente): array {
    $dati = [];
    $cattedre = $this->em->getRepository(Documento::class)->piani($docente);
    foreach ($cattedre as $cattedra) {
      $id = $cattedra['cattedra_id'];
      $dati[$id] = $cattedra;
      if (empty($cattedra['documento'])) {
        // documento non presente
        $dati[$id]['documento'] = null;
        // genera documento fittizio
        $documento = (new Documento)
          ->setTipo('L')
          ->setClasse($this->em->getReference(Classe::class, $cattedra['classe_id']))
          ->setMateria($this->em->getReference(Materia::class, $cattedra['materia_id']));
        // controlla azioni
        if ($this->azioneDocumento('add', $docente, $documento)) {
          $dati[$id]['add'] = 1;
        }
      } else {
        // documento presente, controlla azioni
        if ($this->azioneDocumento('delete', $docente, $dati[$id]['documento'])) {
          $dati[$id]['delete'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i programmi svolti del docente indicato
   *
   * @param Docente $docente Docente selezionato
   * @param bool $programmiQuinte Vero se è consentito caricare programmi per le quinte
   *
   * @return array Dati formattati come array associativo
   */
  public function programmiDocente(Docente $docente, bool $programmiQuinte): array {
    $dati = [];
    $cattedre = $this->em->getRepository(Documento::class)->programmi($docente, $programmiQuinte);
    foreach ($cattedre as $cattedra) {
      $id = $cattedra['cattedra_id'];
      $dati[$id] = $cattedra;
      if (empty($cattedra['documento'])) {
        // documento non presente
        $dati[$id]['documento'] = null;
        // genera documento fittizio
        $documento = (new Documento)
          ->setTipo('P')
          ->setClasse($this->em->getReference(Classe::class, $cattedra['classe_id']))
          ->setMateria($this->em->getReference(Materia::class, $cattedra['materia_id']));
        // controlla azioni
        if ($this->azioneDocumento('add', $docente, $documento, $programmiQuinte)) {
          $dati[$id]['add'] = 1;
        }
      } else {
        // documento presente, controlla azioni
        if ($this->azioneDocumento('delete', $docente, $dati[$id]['documento'], $programmiQuinte)) {
          $dati[$id]['delete'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera le relazioni finali del docente indicato
   *
   * @param Docente $docente Docente selezionato
   *
   * @return array Dati formattati come array associativo
   */
  public function relazioniDocente(Docente $docente): array {
    $dati = [];
    $cattedre = $this->em->getRepository(Documento::class)->relazioni($docente);
    foreach ($cattedre as $cattedra) {
      $id = $cattedra['cattedra_id'];
      $dati[$id] = $cattedra;
      if (empty($cattedra['documento'])) {
        // documento non presente
        $dati[$id]['documento'] = null;
        // genera documento fittizio
        $documento = (new Documento)
          ->setTipo('R')
          ->setClasse($this->em->getReference(Classe::class, $cattedra['classe_id']))
          ->setMateria($this->em->getReference(Materia::class, $cattedra['materia_id']))
          ->setAlunno($cattedra['alunno_id'] ?
            $this->em->getReference(Alunno::class, $cattedra['alunno_id']) : null);
        // controlla azioni
        if ($this->azioneDocumento('add', $docente, $documento)) {
          $dati[$id]['add'] = 1;
        }
      } else {
        // documento presente, controlla azioni
        if ($this->azioneDocumento('delete', $docente, $dati[$id]['documento'])) {
          $dati[$id]['delete'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i documenti del 15 maggio del docente indicato
   *
   * @param Docente $docente Docente selezionato
   *
   * @return array Dati formattati come array associativo
   */
  public function maggioDocente(Docente $docente): array {
    $dati = [];
    $classi = $this->em->getRepository(Documento::class)->maggio($docente);
    foreach ($classi as $classe) {
      $id = $classe['classe_id'];
      $dati[$id] = $classe;
      if (empty($classe['documento'])) {
        // documento non presente
        $dati[$id]['documento'] = null;
        // genera documento fittizio
        $documento = (new Documento)
          ->setTipo('M')
          ->setClasse($this->em->getReference(Classe::class, $classe['classe_id']));
        // controlla azioni
        if ($this->azioneDocumento('add', $docente, $documento)) {
          $dati[$id]['add'] = 1;
        }
      } else {
        // documento presente, controlla azioni
        if ($this->azioneDocumento('delete', $docente, $dati[$id]['documento'])) {
          $dati[$id]['delete'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente a un documento
   *
   * @param string $azione Azione da controllare
   * @param Docente $docente Docente che esegue l'azione
   * @param Documento $documento Documento su cui eseguire l'azione
   * @param bool $programmiQuinte Vero se è consentito caricare programmi per le quinte
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneDocumento(string $azione, Docente $docente, Documento $documento,
                                  bool $programmiQuinte = false): bool {
    switch ($azione) {
      case 'add':     // crea
        if (!$documento->getId()) {
          // documento non esiste su db
          switch ($documento->getTipo()) {
            case 'L':   // piano di lavoro
            case 'P':   // programma finale
            case 'R':   // relazione finale
              $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['attiva' => 1,
                'docente' => $docente, 'classe' => $documento->getClasse(), 'materia' => $documento->getMateria(),
                'alunno' => $documento->getAlunno()]);
              if ($cattedra && $cattedra->getTipo() != 'P' && $documento->getMateria()->getTipo() != 'E') {
                // cattedra docente esiste (escluso potenziamento e Ed.Civica)
                if ($documento->getMateria()->getTipo() == 'S' && $documento->getTipo() == 'R') {
                  // relazione finale di sostegno: ok
                  return true;
                }
                if ($documento->getMateria()->getTipo() != 'S' &&
                    ($documento->getClasse()->getAnno() != 5 || $documento->getTipo() == 'L' ||
                    ($documento->getTipo() == 'P' && $programmiQuinte))) {
                  // cattedra curricolare, escluso quinte per relazioni o programmi: ok
                  return true;
                }
              }
              break;
            case 'M':   // documento 15 maggio
              if ($documento->getClasse()->getAnno() == 5 && $documento->getClasse()->getCoordinatore() &&
                  $docente->getId() == $documento->getClasse()->getCoordinatore()->getId()) {
                // docente coordinatore di quinta: ok
                return true;
              }
              break;
            case 'B':   // diagnosi BES
            case 'H':   // PEI
            case 'D':   // PDP
            case 'C':   // certificazione BES
              if ($docente->getResponsabileBes()) {
                // utente responsabile BES: ok
                return true;
              }
              break;
            default:    // documenti generici
              if ($docente instanceOf Staff) {
                // utente staff: ok
                return true;
              }
          }
        }
        break;
      case 'edit':    // modifica/archivia
      case 'delete':  // cancella
        if ($documento->getId()) {
          // documento esiste su db
          if ($docente->getId() == $documento->getAutore()->getId()) {
            // utente è autore di documento: ok
            return true;
          }
          switch ($documento->getTipo()) {
            case 'L':   // piano di lavoro
            case 'P':   // programma finale
            case 'R':   // relazione finale
              $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['attiva' => 1,
                'docente' => $docente, 'classe' => $documento->getClasse(), 'materia' => $documento->getMateria(),
                'alunno' => $documento->getAlunno()]);
              if ($cattedra && $cattedra->getTipo() != 'P' && $documento->getMateria()->getTipo() != 'E') {
                // cattedra docente esiste (escluso potenziamento e Ed.Civica)
                if ($documento->getMateria()->getTipo() == 'S' && $documento->getTipo() == 'R') {
                  // relazione finale di sostegno: ok
                  return true;
                }
                if ($documento->getMateria()->getTipo() != 'S' &&
                    ($documento->getClasse()->getAnno() != 5 || $documento->getTipo() == 'L' ||
                    ($documento->getTipo() == 'P' && $programmiQuinte))) {
                  // cattedra curricolare, escluso quinte per relazioni o programmi: ok
                  return true;
                }
              }
              break;
            case 'M':   // documento 15 maggio
              if ($documento->getClasse()->getAnno() == 5 && $documento->getClasse()->getCoordinatore() &&
                  $docente->getId() == $documento->getClasse()->getCoordinatore()->getId()) {
                // docente coordinatore di quinta: ok
                return true;
              }
              break;
            case 'B':   // diagnosi BES
            case 'H':   // PEI
            case 'D':   // PDP
            case 'C':   // certificazione BES
              if ($docente->getResponsabileBes() && ($documento->getStato() == 'A' ||
                  ($documento->getAlunno() && $documento->getAlunno()->getClasse() &&
                  (!$docente->getResponsabileBesSede() ||
                  $docente->getResponsabileBesSede()->getId() == $documento->getAlunno()->getClasse()->getSede()->getId())))) {
                // utente responsabile BES di scuola o di stessa sede di alunno: ok
                return true;
              }
              break;
            default:    // documenti generici
              if ($docente instanceOf Staff) {
                // utente staff: ok
                return true;
              }
          }
        }
        break;
    }
    // non consentito
    return false;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente alle circolari
   *
   * @param string $azione Azione da controllare
   * @param DateTime $data Data dell'evento
   * @param Docente $docente Docente che esegue l'azione
   * @param Circolare|null $circolare Circolare su cui eseguire l'azione
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneCircolare(string $azione, DateTime $data, Docente $docente, ?Circolare $circolare=null): bool {
    if ($azione == 'add') {
      // azione di creazione
      if (!$circolare) {
        // nuova circolare
        return true;
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if ($circolare && $circolare->getStato() == 'B') {
        // esiste circolare in bozza
        if ($docente instanceOf Staff &&
            (!$docente->getSede() ||
            ($circolare->getSedi()->count() == 1 && $circolare->getSedi()->contains($docente->getSede())))) {
          // docente autorizzato a modificare circolari
          return true;
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($circolare && $circolare->getStato() == 'B') {
        // esiste circolare in bozza
        if ($docente instanceOf Staff &&
            (!$docente->getSede() ||
            ($circolare->getSedi()->count() == 1 && $circolare->getSedi()->contains($docente->getSede())))) {
          // docente autorizzato a eliminazione circolari
          return true;
        }
      }
    } elseif ($azione == 'publish') {
      // azione di pubblicazione
      if ($circolare && $circolare->getStato() == 'B') {
        // esiste circolare in bozza
        if ($docente instanceOf Staff &&
            (!$docente->getSede() ||
            ($circolare->getSedi()->count() == 1 && $circolare->getSedi()->contains($docente->getSede())))) {
          // docente autorizzato a pubblicare circolari
          return true;
        }
      }
    } elseif ($azione == 'unpublish') {
      // azione di rimozione della pubblicazione
      if ($circolare && $circolare->getStato() == 'P') {
        // esiste circolare pubblicata
        if ($docente instanceOf Staff &&
            (!$docente->getSede() ||
            ($circolare->getSedi()->count() == 1 && $circolare->getSedi()->contains($docente->getSede())))) {
          // docente autorizzato a togliere pubblicazione
          return true;
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Imposta i destinatari predeterminati di un documento
   *
   * @param Documento $documento Documento di cui impostare i destinatari
   */
  public function destinatariDocumento(Documento $documento): void {
    // destinatari predeterminati
    switch ($documento->getTipo()) {
      case 'B':   // diagnosi alunno BES
      case 'H':   // PEI
      case 'D':   // PDP
      case 'C':   // certificazione BES
        // destinatari: CdC
        $documento
          ->setSedi(new ArrayCollection([$documento->getAlunno()->getClasse()->getSede()]))
          ->setDocenti('C')
          ->setFiltroDocenti([$documento->getAlunno()->getClasse()->getId()]);
        break;
      case 'L':   // piani di lavoro
        // destinatari: CdC
        $documento
          ->setSedi(new ArrayCollection([$documento->getClasse()->getSede()]))
          ->setDocenti('C')
          ->setFiltroDocenti([$documento->getClasse()->getId()]);
        break;
      case 'P':   // programmi finali
      case 'M':   // documento 15 maggio
        // destinatari: CdC, genitori/alunni di classe
        $documento
          ->setSedi(new ArrayCollection([$documento->getClasse()->getSede()]))
          ->setDocenti('C')
          ->setFiltroDocenti([$documento->getClasse()->getId()])
          ->setGenitori('C')
          ->setFiltroGenitori([$documento->getClasse()->getId()])
          ->setAlunni('C')
          ->setFiltroAlunni([$documento->getClasse()->getId()]);
        break;
      case 'R':   // relazioni finali
        // nessuno
        break;
    }
    // determina destinatari
    $this->impostaDestinatari($documento);
  }

  /**
   * Aggiunge un allegato al documento indicato
   *
   * @param Documento $documento Documento a cui appartiene l'allegato
   * @param string $file Nome del file temporaneo da usare come allegato
   * @param string $estensione Estensione del file temporaneo da usare come allegato
   * @param int $dimensione Dimensione dell'allegato
   */
  public function allegatoDocumento(Documento $documento, string $file, string $estensione, int $dimensione): void {
    // inizializza
    $fs = new FileSystem();
    $dir = $this->dirDocumento($documento);
    // dati predefiniti
    $nomeClasse = $documento->getClasse() ?
      $documento->getClasse()->getAnno().$documento->getClasse()->getSezione().$documento->getClasse()->getGruppo() : null;
    $nomeMateria = $documento->getMateria() ? $documento->getMateria()->getNomeBreve() : null;
    $nomeAlunno = $documento->getAlunno() ?
      $documento->getAlunno()->getCognome().' '.$documento->getAlunno()->getNome() : null;
    switch ($documento->getTipo()) {
      case 'L':
        // piano di lavoro
        $titolo = 'Piano di lavoro - Classe: '.$nomeClasse.' - Materia: '.$nomeMateria;
        $nome = 'Piano '.$nomeClasse.' '.$nomeMateria;
        break;
      case 'P':
        // programma svolto
        $titolo = 'Programma svolto - Classe: '.$nomeClasse.' - Materia: '.$nomeMateria;
        $nome = 'Programma '.$nomeClasse.' '.$nomeMateria;
        break;
      case 'R':
        // relazione finale
        $titolo = 'Relazione finale - Classe: '.$nomeClasse.' - Materia: '.$nomeMateria.
          ($nomeAlunno ? ' - '.$nomeAlunno : '');
        $nome = 'Relazione '.$nomeClasse.' '.$nomeMateria.($nomeAlunno ? ' '.$nomeAlunno : '');
        break;
      case 'M':
        // documento 15 maggio
        $titolo = 'Documento 15 maggio - Classe: '.$nomeClasse;
        $nome = 'Documento 15 maggio '.$nomeClasse;
        break;
      case 'B':
        // diagnosi alunno BES
        $titolo = 'Diagnosi ('.$documento->getAlunno()->getId().')';;
        $nome = 'Diagnosi '.$documento->getAlunno()->getId();
        break;
      case 'H':
        // PEI
        $titolo = 'P.E.I. ('.$documento->getAlunno()->getId().')';
        $nome = 'PEI '.$documento->getAlunno()->getId();
        break;
      case 'D':
        // PDP
        $titolo = 'P.D.P. ('.$documento->getAlunno()->getId().')';
        $nome = 'PDP '.$documento->getAlunno()->getId();
        break;
      case 'C':
        // altra certificazione
        $titolo = 'Altra certificazione ('.$documento->getAlunno()->getId().')';
        $nome = 'Certificazione '.$documento->getAlunno()->getId();
        break;
    }
    $nome = $this->normalizzaNome($nome);
    $nomefile = date('Ymd_His').'_'.bin2hex(random_bytes(8));
    // imposta documento allegato
    $allegato = (new Allegato)
      ->setTitolo($titolo)
      ->setNome($nome)
      ->setFile($nomefile)
      ->setEstensione($estensione)
      ->setDimensione($dimensione);
    $this->em->persist($allegato);
    $documento->addAllegato($allegato);
    // sposta e rinomina l'allegato
    $fs->rename($this->dirTemp.'/'.$file.'.'.$estensione, $dir.'/'.$allegato->getFile().'.'.$estensione);
  }

  /**
   * Restituisce la directory del documento
   *
   * @param Documento $documento Documento di cui impostare i destinatari
   *
   * @return string Percorso completo della directory
   */
  public function dirDocumento(Documento $documento): string {
    $fs = new FileSystem();
    if ($documento->getTipo() == 'G') {
      // documento generico
      $dir = $this->dirUpload.'/documenti';
    } elseif (in_array($documento->getTipo(), ['B', 'H', 'D', 'C'])) {
      // documenti riservati
      if ($documento->getStato() == 'A') {
        // documento archiviato
        $dir = $this->dirUpload.'/documenti/'.$documento->getAnno().'/riservato';
      } else {
        // documento pubblicato
        $dir = $this->dirUpload.'/documenti/riservato';
      }
    } else {
      // altri documenti in archivio classi
      $classe = ($documento->getAlunno() && $documento->getAlunno()->getClasse()) ?
        $documento->getAlunno()->getClasse() : $documento->getClasse();
      $dir = $this->dirClassi.'/'.$classe->getAnno().$classe->getSezione().$classe->getGruppo();
    }
    // controlla esistenza percorso
    if (!$fs->exists($dir)) {
      // crea directory
      $fs->mkdir($dir);
    }
    // restituisce percorso
    return $dir;
  }

  /**
   * Recupera i documenti dei docenti secondo i criteri indicati
   *
   * @param array $criteri Criteri di ricerca
   * @param int $pagina Indica il numero di pagina da visualizzare
   * @param Sede|null $sede Sede dello staff
   *
   * @return array Dati formattati come array associativo
   */
  public function documentiDocenti(array $criteri, int $pagina, Sede $sede=null): array {
    // legge cattedre
    $dati = $this->em->getRepository(Documento::class)->docenti($criteri, $pagina, $sede);
    if ($criteri['tipo'] == 'M') {
      // documento del 15 maggio: niente da aggiungere
      return $dati;
    }
    // aggiunge info
    foreach ($dati['lista'] as $i=>$cattedra) {
      // query base docenti
      $docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
        ->select('d.cognome,d.nome')
        ->join(Cattedra::class, 'c', 'WITH', 'c.docente=d.id AND c.classe=:classe AND c.materia=:materia')
        ->where("c.attiva=1 AND c.tipo!='P'")
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('classe', $cattedra['classe_id'])
        ->setParameter('materia', $cattedra['materia_id']);
      if ($criteri['tipo'] == 'R' && $cattedra['alunno_id']) {
        // relazioni di sostegno
        $docenti
          ->andWhere('c.alunno=:alunno')
          ->setParameter('alunno', $cattedra['alunno_id']);
        $dati['documenti'][$i] = $this->em->getRepository(Documento::class)->createQueryBuilder('d')
          ->join('d.autore', 'doc')
          ->where("d.tipo='R' AND d.classe=:classe AND d.materia=:materia AND d.alunno=:alunno AND d.stato='P'")
          ->orderBy('doc.cognome,doc.nome', 'ASC')
          ->setParameter('classe', $cattedra['classe_id'])
          ->setParameter('materia', $cattedra['materia_id'])
          ->setParameter('alunno', $cattedra['alunno_id'])
          ->getQuery()
          ->getResult();
      }
      // dati docenti
      $dati['docenti'][$i] = $docenti
        ->getQuery()
        ->getArrayResult();
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i documenti degli alunni BES per il responsabile indicato, secondo i criteri di ricerca
   *
   * @param array $criteri Criteri di ricerca
   * @param Docente $docente Docente responsabile BES
   * @param int $pagina Indica il numero di pagina da visualizzare
   *
   * @return array Dati formattati come array associativo
   */
  public function besDocente(array $criteri, Docente $docente, int $pagina): array {
    // genera documento fittizio per l'azione ADD
    $documentoAdd = (new Documento)
      ->setTipo('B');
    // estrae dati alunni
    $dati = $this->em->getRepository(Documento::class)->bes($criteri, $pagina, $docente->getResponsabileBesSede());
    $filtro = empty($criteri['tipo']) ? ['B', 'H', 'D', 'C'] : [$criteri['tipo']];
    foreach ($dati['lista'] as $i=>$alunno) {
      // dati documenti
      $dati['documenti'][$i]['lista'] = $this->em->getRepository(Documento::class)->createQueryBuilder('d')
        ->join('d.alunno', 'a')
        ->where("d.tipo IN (:tipo) AND d.alunno=:alunno AND d.stato='P'")
        ->orderBy('d.tipo', 'DESC')
        ->setParameter('tipo', $filtro)
        ->setParameter('alunno', $alunno)
        ->getQuery()
        ->getResult();
      // controlla azioni
      foreach ($dati['documenti'][$i]['lista'] as $j=>$documento) {
        if ($this->azioneDocumento('delete', $docente, $documento)) {
          $dati['documenti'][$i]['delete'][$j] = 1;
        }
        if ($this->azioneDocumento('edit', $docente, $documento)) {
          $dati['documenti'][$i]['edit'][$j] = 1;
        }
        $numDocumenti = count($dati['documenti'][$i]['lista']);
        if (!empty($criteri['tipo'])) {
          // conta i documenti esistenti
          $numDocumenti = $this->em->getRepository(Documento::class)->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.alunno', 'a')
            ->where("d.tipo IN ('B', 'H', 'D', 'C') AND d.alunno=:alunno AND d.stato='P'")
            ->setParameter('alunno', $alunno)
            ->getQuery()
            ->getSingleScalarResult();
        }
        if ($numDocumenti < 3 && $this->azioneDocumento('add', $docente, $documentoAdd)) {
          $dati['documenti'][$i]['add'][$j] = 1;
        }
      }
    }
    // controlla azioni
    if ($this->azioneDocumento('add', $docente, $documentoAdd)) {
      $dati['azioni']['add'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i documenti degli alunni secondo i criteri indicati
   *
   * @param array $criteri Criteri di ricerca
   * @param int $pagina Indica il numero di pagina da visualizzare
   * @param Sede|null $sede Sede dello staff
   *
   * @return array Dati formattati come array associativo
   */
  public function documentiAlunni(array $criteri, int $pagina, Sede $sede=null): array {
    // legge dati
    $dati = $this->em->getRepository(Documento::class)->alunni($criteri, $pagina, $sede);
    // query base
    $query = $this->em->getRepository(Documento::class)->createQueryBuilder('d')
      ->join('d.alunno', 'a')
      ->where("d.tipo IN ('B', 'H', 'D', 'C') AND d.alunno=:alunno AND d.stato='P'")
      ->orderBy('d.tipo', 'DESC');
    if ($criteri['tipo']) {
      $query
        ->andWhere('d.tipo=:tipo')
        ->setParameter('tipo', $criteri['tipo']);
    }
    foreach ($dati['lista'] as $i=>$alunno) {
      // dati documenti
      $dati['documenti'][$i] = (clone $query)
        ->setParameter('alunno', $alunno)
        ->getQuery()
        ->getResult();
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i documenti archiviati per gli alunni BES, secondo i criteri indicati
   *
   * @param array $criteri Lista con i criteri di ricerca
   * @param Docente $docente Docente responsabile BES
   * @param int $pagina Indica il numero di pagina da visualizzare
   *
   * @return array Dati formattati come array associativo
   */
  public function archivioBes(array $criteri, Docente $docente, int $pagina): array {
    // estrae dati
    $dati = $this->em->getRepository(Documento::class)->archivioBes($criteri, $pagina);
    foreach ($dati['lista'] as $documento) {
      // controlla azioni
      if ($this->azioneDocumento('delete', $docente, $documento)) {
        $dati['delete'][$documento->getId()] = 1;
      }
      if ($this->azioneDocumento('edit', $docente, $documento)) {
        // controlla se esiste l'alunno
        $codiceFiscale = trim(substr($documento->getTitolo(), strpos($documento->getTitolo(), '- C.F. ') + 7));
        $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['codiceFiscale' => $codiceFiscale,
          'abilitato' => 1]);
        if ($alunno && $alunno->getClasse() && ($docente->getResponsabileBesSede() === null ||
            $alunno->getClasse()->getSede()->getId() == $docente->getResponsabileBesSede()->getId())) {
          // esiste alunno: controlla se esiste già un documento dello stesso tipo
          $doc = $this->em->getRepository(Documento::class)->findOneBy(['alunno' => $alunno,
            'tipo' => $documento->getTipo(), 'stato' => 'P']);
          if (!$doc) {
            // non esiste documento: aggiunge azione
            $dati['restore'][$documento->getId()] = 1;
          }
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la coerenza dei dati inseriti ed eventualmente restituisce il messaggio di errore
   *
   * @param Circolare $circolare Circolare da controllare
   * @param Docente $docente docente che inserisce la circolare
   * @param array $documento File caricato per il documento della circolare
   *
   * @return string|null Messsaggio di errore o valore nullo se il controllo è positivo
   */
  public function validaCircolare(Circolare $circolare, Docente $docente, array $documento): ?string {
    // messaggi di errore
    $messaggi = [
      // errori generici
      1 => 'exception.data_nulla',
      2 => 'exception.circolare_oggetto_nullo',
      3 => 'exception.circolare_sede_nulla',
      4 => 'exception.circolare_sede_non_ammessa',
      5 => 'exception.circolare_destinatari_nulli',
      6 => 'exception.filtro_coordinatori_classi_invalido',
      7 => 'exception.filtro_docenti_classi_invalido',
      8 => 'exception.filtro_materie_invalido',
      9 => 'exception.filtro_docenti_invalido',
      10 => 'exception.filtro_genitori_classi_invalido',
      11 => 'exception.filtro_genitori_invalido',
      12 => 'exception.filtro_alunni_classi_invalido',
      13 => 'exception.filtro_alunni_invalido',
      14 => 'exception.filtro_rappresentanti_genitori_classi_invalido',
      15 => 'exception.filtro_rappresentanti_alunni_classi_invalido',
      16 => 'exception.lista_esterni_invalida',
      // errori specifici
      50 => 'exception.circolare_numero_esiste',
      51 => 'exception.circolare_documento_nullo'];
    // controlli generici
    $errore = $this->validaComunicazione($circolare, $docente);
    if ($errore > 0) {
      // restituisce messaggio di errore
      return $messaggi[$errore];
    }
    // controllo numero
    if (!$this->em->getRepository(circolare::class)->controllaNumero($circolare)) {
      // numero presente
      return $messaggi[50];
    }
    // controllo documento
    if (count($documento) == 0) {
      // documento non inviato
      return $messaggi[51];
    }
    // nessun errore
    return null;
  }

  /**
   * Controlla la coerenza dei dati inseriti ed eventualmente restituisce il messaggio di errore
   *
   * @param Avviso $avviso Avviso da controllare
   * @param Docente $docente docente che inserisce l'avviso
   * @param FormInterface $form Form inviato dal docente
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param bool $allegati Vero se esistono allegati
   *
   * @return string|null Messsaggio di errore o valore nullo se il controllo è positivo
   */
  public function validaAvviso(Avviso $avviso, Docente $docente, FormInterface $form, RegistroUtil $reg,
                               bool $allegati): ?string {
    // messaggi di errore
    $messaggi = [
      // errori generici
      1 => 'exception.data_nulla',
      2 => 'exception.avviso_oggetto_nullo',
      3 => 'exception.avviso_sede_nulla',
      4 => 'exception.avviso_sede_non_ammessa',
      5 => 'exception.avviso_destinatari_nulli',
      6 => 'exception.filtro_coordinatori_classi_invalido',
      7 => 'exception.filtro_docenti_classi_invalido',
      8 => 'exception.filtro_materie_invalido',
      9 => 'exception.filtro_docenti_invalido',
      10 => 'exception.filtro_genitori_classi_invalido',
      11 => 'exception.filtro_genitori_invalido',
      12 => 'exception.filtro_alunni_classi_invalido',
      13 => 'exception.filtro_alunni_invalido',
      14 => 'exception.filtro_rappresentanti_genitori_classi_invalido',
      15 => 'exception.filtro_rappresentanti_alunni_classi_invalido',
      16 => 'exception.lista_esterni_invalida',
      // errori specifici
      50 => 'exception.annotazione_no_destinatari',
      51 => 'exception.annotazione_non_permessa',
      52 => 'exception.annotazione_con_file'];
    // controlli generici
    $errore = $this->validaComunicazione($avviso, $docente);
    if ($errore > 0) {
      // restituisce messaggio di errore
      return $messaggi[$errore];
    }
    // controlla annotazione
    $creaAnnotazione = $form->get('creaAnnotazione')->getData();
    if ($creaAnnotazione && $avviso->getCoordinatori() == 'N' && in_array($avviso->getDocenti(), ['N', 'M', 'U']) &&
        $avviso->getGenitori() == 'N' && $avviso->getAlunni() == 'N' &&
        $avviso->getRappresentantiGenitori() == 'N' && $avviso->getRappresentantiAlunni() == 'N') {
      // errore: annotazione senza destinatari tra docenti/genitori/alunni
      return $messaggi[50];
    }
    if ($creaAnnotazione && !$reg->azioneAnnotazione('add', $avviso->getData(), $docente, null, null)) {
      // errore: nuova annotazione non permessa
      return $messaggi[51];
    }
    if ($avviso->getAnnotazioni()->count() > 0) {
      $a = $avviso->getAnnotazioni()[0];
      if (!$reg->azioneAnnotazione('delete', $a->getData(), $docente, $a->getClasse(), $a)) {
        // errore: cancellazione annotazione non permessa
        return $messaggi[51];
      }
    }
    if ($creaAnnotazione && $allegati) {
      // errore: annotazione con allegati
      return $messaggi[52];
    }
    // nessun errore
    return null;
  }

  /**
   * Restituisce le circolari secondo i criteri di ricerca inseriti.
   *
   * @param array $criteri Criteri di ricerca
   * @param int $pagina Pagina corrente
   * @param Docente $docente Docente che visualizza le circolari
   *
   * @return array Dati formattati come array associativo
   */
  public function listaCircolari(array $criteri, int $pagina, Docente $docente): array {
    $dati = [];
    // legge circolari in bozza
    $dati['bozza'] = $this->em->getRepository(Circolare::class)->bozza();
    // controllo azioni e aggiunta info
    foreach ($dati['bozza'] as $k=>$c) {
      // edit
      if ($this->azioneCircolare('edit', $c->getData(), $docente, $c)) {
        // pulsante edit
        $dati['azioni']['unpublish-'.$k]['edit'] = 1;
      }
      // delete
      if ($this->azioneCircolare('delete', $c->getData(), $docente, $c)) {
        // pulsante delete
        $dati['azioni']['unpublish-'.$k]['delete'] = 1;
      }
      // publish
      if ($this->azioneCircolare('publish', $c->getData(), $docente, $c)) {
        // pulsante publish
        $dati['azioni']['unpublish-'.$k]['publish'] = 1;
      }
    }
    // legge circolari pubblicate
    $dati['pubblicate'] = $this->em->getRepository(Circolare::class)->pubblicate($criteri, $pagina);
    // controllo azioni e aggiunta info
    foreach ($dati['pubblicate']['lista'] as $k=>$c) {
      // unpublish
      if ($this->azioneCircolare('unpublish', $c->getData(), $docente, $c)) {
        // pulsante publish
        $dati['azioni']['publish-'.$k]['unpublish'] = 1;
      }
    }
    // add
    if ($this->azioneCircolare('add', new DateTime(), $docente, null)) {
      // pulsante add
      $dati['azioni']['add'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce gli avvisi secondo i criteri di ricerca inseriti
   *
   * @param array $criteri Criteri di ricerca
   * @param int $pagina Pagina corrente
   * @param Docente $docente Docente che richiede i dati
   *
   * @return array Dati formattati come array associativo
   */
  public function listaAvvisi(array $criteri, int $pagina, Docente $docente): array {
    $dati = [];
    // legge avvisi (solo anno corrente)
    $dati = $this->em->getRepository(Avviso::class)->lista($criteri, $pagina);
    // controllo azioni e aggiunta info
    foreach ($dati['lista'] as $k => $a) {
      // legge classi
      if ($a->getGenitori() == 'T' || $a->getAlunni() == 'T') {
        // tutte le classi di sedi
        $dati['classi'][$a->getId()] = 'T';
      } elseif ($a->getGenitori() == 'C' || $a->getAlunni() == 'C') {
        // solo le classi di filtro
        $dati['classi'][$a->getId()] = $this->em->getRepository(ComunicazioneClasse::class)->classiComunicazione($a);
      }
      // pulsante edit
      if ($this->azioneAvviso('edit', $a->getData(), $docente, $a)) {
        $dati['azioni'][$k]['edit'] = 1;
      }
      // pulsante delete
      if ($this->azioneAvviso('delete', $a->getData(), $docente, $a)) {
        $dati['azioni'][$k]['delete'] = 1;
      }
    }
    // pulsante add
    if ($this->azioneAvviso('add', new DateTime(), $docente, null)) {
      $dati['azioni']['add'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente agli avvisi
   *
   * @param string $azione Azione da controllare
   * @param DateTime $data Data dell'evento
   * @param Docente $docente Docente che esegue l'azione
   * @param Avviso|null $avviso Avviso su cui eseguire l'azione
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneAvviso($azione, DateTime $data, Docente $docente, ?Avviso $avviso=null): bool {
    if ($azione == 'add') {
      // azione di creazione
      if (!$avviso) {
        // nuovo avviso
        if ($data >= new DateTime('today')) {
          // data non in passato, ok
          return true;
        }
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if ($avviso) {
        // esiste avviso
        if ($data >= new DateTime('today')) {
          // data non in passato
          if ($avviso->getAutore()->getId() == $docente->getId()) {
            // docente autore dell'avviso
            return true;
          }
          if (!in_array($avviso->getTipo(), ['V', 'P']) &&  $docente instanceOf Staff &&
              (!$docente->getSede() ||
              ($avviso->getSedi()->count() == 1 && $avviso->getSedi()->contains($docente->getSede())))) {
            // docente di staff autorizzato a modificare avvisi
            return true;
          }
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($avviso) {
        // esiste avviso
        if ($avviso->getAutore()->getId() == $docente->getId()) {
          // docente autore dell'avviso
          return true;
        }
        if (!in_array($avviso->getTipo(), ['V', 'P']) &&  $docente instanceOf Staff &&
            (!$docente->getSede() ||
            ($avviso->getSedi()->count() == 1 && $avviso->getSedi()->contains($docente->getSede())))) {
          // docente di staff autorizzato a modificare avvisi
          return true;
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Inserisce o cancella l'annotazione sul registro relativa all'avviso
   *
   * @param Avviso $avviso Avviso da controllare
   * @param bool $modifica Vero se si è in modalità modifica di un avviso esistente
   * @param bool $crea Vero per creare una annotazione sul registro
   * @param bool $oggetto Vero per riportare l'oggetto nel testo dell'annotazione
   */
  public function annotazioneAvviso(Avviso $avviso, bool $modifica, bool $crea, bool $oggetto=false): void {
    // cancella annotazioni se esistono
    if ($modifica && $avviso->getAnnotazioni()->count() > 0) {
      foreach ($avviso->getAnnotazioni() as $annotazione) {
        // rimuove annotazione
        $avviso->removeAnnotazione($annotazione);
        $this->em->remove($annotazione);
      }
    }
    // crea nuova annotazione se richiesto
    if ($crea) {
      // determina classi
      $classi = [];
      $sedi = $avviso->getSedi()->toArray();
      if ($avviso->getCoordinatori() == 'T' || $avviso->getDocenti() == 'T' ||
          $avviso->getGenitori() == 'T' || $avviso->getAlunni() == 'T' ||
          $avviso->getRappresentantiGenitori() == 'T' || $avviso->getRappresentantiAlunni() == 'T') {
        // tutte le classi di sedi
        $classi = $this->em->getRepository(Classe::class)->getIdClasse($sedi, null);
      } elseif ($avviso->getCoordinatori() == 'C' || $avviso->getDocenti() == 'C' ||
          $avviso->getGenitori() == 'C' || $avviso->getAlunni() == 'C' ||
          $avviso->getRappresentantiGenitori() == 'C' || $avviso->getRappresentantiAlunni() == 'C') {
        // solo classi del filtro
        $filtro = array_merge(
          $avviso->getCoordinatori() == 'C'? $avviso->getFiltroCoordinatori() : [],
          $avviso->getDocenti() == 'C' ? $avviso->getFiltroDocenti() : [],
          $avviso->getGenitori() == 'C' ? $avviso->getFiltroGenitori() : [],
          $avviso->getAlunni() == 'C' ? $avviso->getFiltroAlunni() : [],
          $avviso->getRappresentantiGenitori() == 'C' ? $avviso->getFiltroRappresentantiGenitori() : [],
          $avviso->getRappresentantiAlunni() == 'C' ? $avviso->getFiltroRappresentantiAlunni() : []);
        $classi = $this->em->getRepository(Classe::class)->getIdClasse($sedi, $filtro);
      } elseif ($avviso->getGenitori() == 'U' || $avviso->getAlunni() == 'U') {
        // classi di alunni/genitori
        $filtro = array_merge(
          $avviso->getGenitori() == 'U' ? $avviso->getFiltroGenitori() : [],
          $avviso->getAlunni() == 'U' ? $avviso->getFiltroAlunni() : []);
        $classi = $this->em->getRepository(Classe::class)->getIdClasseAlunni($sedi, $filtro);
      }
      // crea annotazione
      $this->creaAnnotazioneAvviso($avviso, $classi, $oggetto);
    }
  }

  /**
   * Crea l'annotazione sul registro in base ai dati dell'avviso
   *
   * @param Avviso $avviso Avviso da cui recuperare i dati
   * @param array $classi Lista ID delle classi a cui inviare l'annotazione
   * @param bool $oggetto Se vero aggiunge l'oggetto nel testo dell'annotazione
   */
  public function creaAnnotazioneAvviso(Avviso $avviso, array $classi, bool $oggetto=false): void {
    // crea annotazioni
    $testo = ($oggetto ? $avviso->getTitolo()."\n" : '').$avviso->testoPersonalizzato();
    foreach ($classi as $classe) {
      $a = (new Annotazione())
        ->setData($avviso->getData())
        ->setTesto($testo)
        ->setVisibile(false)
        ->setAvviso($avviso)
        ->setClasse($this->em->getReference(Classe::class, $classe))
        ->setDocente($avviso->getAutore());
      $this->em->persist($a);
      $avviso->addAnnotazione($a);
    }
  }

  /**
   * Controlla la coerenza dei dati inseriti ed eventualmente restituisce il messaggio di errore
   *
   * @param Avviso $avviso Avviso da controllare
   * @param Docente $docente docente che inserisce l'avviso
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return string|null Messsaggio di errore o valore nullo se il controllo è positivo
   */
  public function validaAvvisoClassi(Avviso $avviso, Docente $docente, RegistroUtil $reg): ?string {
    // messaggi di errore
    $messaggi = [
      // errori generici
      1 => 'exception.data_nulla',
      2 => 'exception.avviso_oggetto_nullo',
      3 => 'exception.avviso_sede_nulla',
      4 => 'exception.avviso_sede_non_ammessa',
      5 => 'exception.avviso_destinatari_nulli',
      6 => 'exception.filtro_coordinatori_classi_invalido',
      7 => 'exception.filtro_docenti_classi_invalido',
      8 => 'exception.filtro_materie_invalido',
      9 => 'exception.filtro_docenti_invalido',
      10 => 'exception.filtro_genitori_classi_invalido',
      11 => 'exception.filtro_genitori_invalido',
      12 => 'exception.filtro_alunni_classi_invalido',
      13 => 'exception.filtro_alunni_invalido',
      14 => 'exception.filtro_rappresentanti_genitori_classi_invalido',
      15 => 'exception.filtro_rappresentanti_alunni_classi_invalido',
      16 => 'exception.lista_esterni_invalida',
      // errori specifici
      50 => 'exception.data_festiva',
      51 => 'exception.filtro_classe_nullo',
      52 => 'exception.annotazione_non_permessa'];
    // controlli generici
    $errore = $this->validaComunicazione($avviso, $docente);
    if ($errore > 0) {
      // restituisce messaggio di errore
      return $messaggi[$errore];
    }
    // controllo data
    if ($avviso->getTipo() != 'A' && $reg->controlloData($avviso->getData(), null)) {
      // errore: festivo
      return $messaggi[50];
    }
    // controlla filtro classi
    if (empty($avviso->getFiltroDocenti())) {
      // errore: filtro vuoto
      return $messaggi[51];
    }
    // controlla permessi
    if (!$reg->azioneAnnotazione('add', $avviso->getData(), $docente, null, null)) {
      // errore: nuova annotazione non permessa
      return $messaggi[52];
    }
    if ($avviso->getAnnotazioni()->count() > 0) {
      $a = $avviso->getAnnotazioni()[0];
      if (!$reg->azioneAnnotazione('delete', $a->getData(), $docente, $a->getClasse(), $a)) {
        // errore: cancellazione annotazione non permessa
        return $messaggi[52];
      }
    }
    // nessun errore
    return null;
  }

  /**
   * Controlla la coerenza dei dati inseriti ed eventualmente restituisce il messaggio di errore
   *
   * @param Avviso $avviso Avviso da controllare
   * @param Docente $docente docente che inserisce l'avviso
   *
   * @return string|null Messsaggio di errore o valore nullo se il controllo è positivo
   */
  public function validaAvvisoPersonali(Avviso $avviso, Docente $docente): ?string {
    // messaggi di errore
    $messaggi = [
      // errori generici
      1 => 'exception.data_nulla',
      2 => 'exception.avviso_oggetto_nullo',
      3 => 'exception.avviso_sede_nulla',
      4 => 'exception.avviso_sede_non_ammessa',
      5 => 'exception.avviso_destinatari_nulli',
      6 => 'exception.filtro_coordinatori_classi_invalido',
      7 => 'exception.filtro_docenti_classi_invalido',
      8 => 'exception.filtro_materie_invalido',
      9 => 'exception.filtro_docenti_invalido',
      10 => 'exception.filtro_genitori_classi_invalido',
      11 => 'exception.filtro_genitori_invalido',
      12 => 'exception.filtro_alunni_classi_invalido',
      13 => 'exception.filtro_alunni_invalido',
      14 => 'exception.filtro_rappresentanti_genitori_classi_invalido',
      15 => 'exception.filtro_rappresentanti_alunni_classi_invalido',
      16 => 'exception.lista_esterni_invalida',
      // errori specifici
      50 => 'exception.filtro_utente_nullo'];
    // controlli generici
    $errore = $this->validaComunicazione($avviso, $docente);
    if ($errore > 0) {
      // restituisce messaggio di errore
      return $messaggi[$errore];
    }
    // controlla filtro utenti
    if (empty($avviso->getFiltroGenitori())) {
      // errore: filtro vuoto
      return $messaggi[50];
    }
    // nessun errore
    return null;
  }

  /**
   * Restituisce gli avvisi dei coordinatori.
   *
   * @param int $pagina Pagina corrente
   * @param Docente $docente Docente coordinatore
   * @param Classe $classe Classe a cui è rivolto l'avviso
   *
   * @return array Dati formattati come array associativo
   */
  public function listaAvvisiCoordinatore(int $pagina, Docente $docente, Classe $classe): array {
    $dati = [];
    // legge avvisi (solo anno corrente)
    $dati = $this->em->getRepository(Avviso::class)->listaCoordinatore($classe, $pagina);
    // controllo azioni e aggiunta info
    foreach ($dati['lista'] as $k => $a) {
      // info destinatari
      $dati['utenti'][$k] = $this->infoDestinatari($a);
      // pulsante edit
      if ($this->azioneAvviso('edit', $a->getData(), $docente, $a)) {
        $dati['azioni'][$k]['edit'] = 1;
      }
      // pulsante delete
      if ($this->azioneAvviso('delete', $a->getData(), $docente, $a)) {
        $dati['azioni'][$k]['delete'] = 1;
      }
    }
    // pulsante add
    if ($this->azioneAvviso('add', new DateTime(), $docente, null)) {
      $dati['azioni']['add'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la coerenza dei dati inseriti ed eventualmente restituisce il messaggio di errore
   *
   * @param Avviso $avviso Avviso da controllare
   * @param Docente $docente docente che inserisce l'avviso
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return string|null Messsaggio di errore o valore nullo se il controllo è positivo
   */
  public function validaAvvisoAgenda(Avviso $avviso, Docente $docente, RegistroUtil $reg): ?string {
    // messaggi di errore
    $messaggi = [
      // errori generici
      1 => 'exception.data_nulla',
      2 => 'exception.avviso_oggetto_nullo',
      3 => 'exception.avviso_sede_nulla',
      4 => 'exception.avviso_sede_non_ammessa',
      5 => 'exception.avviso_destinatari_nulli',
      6 => 'exception.filtro_coordinatori_classi_invalido',
      7 => 'exception.filtro_docenti_classi_invalido',
      8 => 'exception.filtro_materie_invalido',
      9 => 'exception.filtro_docenti_invalido',
      10 => 'exception.filtro_genitori_classi_invalido',
      11 => 'exception.filtro_genitori_invalido',
      12 => 'exception.filtro_alunni_classi_invalido',
      13 => 'exception.filtro_alunni_invalido',
      14 => 'exception.filtro_rappresentanti_genitori_classi_invalido',
      15 => 'exception.filtro_rappresentanti_alunni_classi_invalido',
      16 => 'exception.lista_esterni_invalida',
      // errori specifici
      50 => 'exception.data_festiva',
      51 => 'exception.filtro_classe_nullo',
      52 => 'exception.filtro_utente_nullo',
      53 => 'exception.cattedra_non_valida',
      54 => 'exception.annotazione_non_permessa'];
    // controlli generici
    $errore = $this->validaComunicazione($avviso, $docente);
    if ($errore > 0) {
      // restituisce messaggio di errore
      return $messaggi[$errore];
    }
    // controllo data
    if ($reg->controlloData($avviso->getData(), null)) {
      // errore: festivo
      return $messaggi[50];
    }
    // controlla filtri
    if (($avviso->getGenitori() == 'C' && empty($avviso->getFiltroGenitori())) ||
        ($avviso->getAlunni() == 'C' && empty($avviso->getFiltroAlunni()))) {
      // errore: filtro classi vuoto
      return $messaggi[51];
    }
    if (($avviso->getGenitori() == 'U' && empty($avviso->getFiltroGenitori())) ||
        ($avviso->getAlunni() == 'U' && empty($avviso->getFiltroAlunni()))) {
      // errore: filtro utenti vuoto
      return $messaggi[52];
    }
    // controllo cattedra di sostegno
    if ($avviso->getCattedra() && $avviso->getCattedra()->getMateria()->getTipo() == 'S' &&
        (!$avviso->getMateria() || ($avviso->getCattedra()->getAlunno() &&
        $avviso->getCattedra()->getAlunno()->getId() != (int) $avviso->getFiltroGenitori()[0]))) {
      // materia curricolare non indicata o alunno diverso da quello della cattedra
      return $messaggi[53];
    }
    if ($avviso->getTipo() == 'V') {
      // controlla permessi
      if (!$reg->azioneAnnotazione('add', $avviso->getData(), $docente, null, null)) {
        // errore: nuova annotazione non permessa
        return $messaggi[54];
      }
      if ($avviso->getAnnotazioni()->count() > 0) {
        $a = $avviso->getAnnotazioni()[0];
        if (!$reg->azioneAnnotazione('delete', $a->getData(), $docente, $a->getClasse(), $a)) {
          // errore: cancellazione annotazione non permessa
          return $messaggi[54];
        }
      }
    }
    // nessun errore
    return null;
  }

}
