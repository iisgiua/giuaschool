<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use App\Entity\Alunno;
use App\Entity\Ata;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\Documento;
use App\Entity\File;
use App\Entity\Genitore;
use App\Entity\ListaDestinatari;
use App\Entity\ListaDestinatariClasse;
use App\Entity\ListaDestinatariUtente;
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


/**
 * DocumentiUtil - classe di utilità per la gestione dei documenti di classe
 *
 * @author Antonello Dessì
 */
class DocumentiUtil {

  /**
   * @var string $dirClassi Percorso della directory per l'archivio delle classi
   */
  private $dirClassi;


  //==================== METODI DELLA CLASSE ====================

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
   * Recupera i piani di lavoro del docente indicato
   *
   * @param Docente $docente Docente selezionato
   *
   * @return array Dati formattati come array associativo
   */
  public function pianiDocente(Docente $docente) {
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
          ->setClasse($this->em->getRepository(Classe::class)->find($cattedra['classe_id']))
          ->setMateria($this->em->getRepository(Materia::class)->find($cattedra['materia_id']));
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
  public function programmiDocente(Docente $docente, bool $programmiQuinte) {
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
          ->setClasse($this->em->getRepository(Classe::class)->find($cattedra['classe_id']))
          ->setMateria($this->em->getRepository(Materia::class)->find($cattedra['materia_id']));
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
   * Controlla se è possibile eseguire l'azione specificata relativamente a un documento
   *
   * @param string $azione Azione da controllare
   * @param Docente $docente Docente che esegue l'azione
   * @param Documento $documento Documento su cui eseguire l'azione
   * @param bool $programmiQuinte Vero se è consentito caricare programmi per le quinte
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneDocumento($azione, Docente $docente, Documento $documento,
                                  bool $programmiQuinte = false) {
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
      case 'edit':    // modifica
      case 'delete':  // cancella
        if ($documento->getId()) {
          // documento esiste su db
          if ($docente->getId() == $documento->getDocente()->getId()) {
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
   * Esegue la conversione in formato PDF del file indicato (presente nella dir temporanea)
   *
   * @param string $nomefile File da convertire
   *
   * @return array Restituisce una lista con il nome del file e l'estensione
   */
  public function convertePdf($nomefile) {
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
   * Imposta un allegato per il documento a cui appartiene
   *
   * @param Documento $documento Documento a cui appartiene l'allegato
   * @param string $file Nome del file temporaneo da usare come allegato
   * @param string $estensione Estensione del file temporaneo da usare come allegato
   * @param int $dimensione Dimensione dell'allegato
   */
  public function impostaUnAllegato(Documento $documento, $file, $estensione, $dimensione) {
    // inizializza
    $fs = new FileSystem();
    $dir = $this->documentoDir($documento);
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
    $allegato = (new File)
      ->setTitolo($titolo)
      ->setNome($nome)
      ->setFile($nomefile)
      ->setEstensione($estensione)
      ->setDimensione($dimensione);
    $this->em->persist($allegato);
    $documento->setAllegati(new ArrayCollection([$allegato]));
    // sposta e rinomina l'allegato
    $fs->rename($this->dirTemp.'/'.$file.'.'.$estensione, $dir.'/'.$allegato->getFile().'.'.$estensione);
  }

  /**
   * Recupera le relazioni finali del docente indicato
   *
   * @param Docente $docente Docente selezionato
   *
   * @return array Dati formattati come array associativo
   */
  public function relazioniDocente(Docente $docente) {
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
          ->setClasse($this->em->getRepository(Classe::class)->find($cattedra['classe_id']))
          ->setMateria($this->em->getRepository(Materia::class)->find($cattedra['materia_id']))
          ->setAlunno($cattedra['alunno_id'] ?
            $this->em->getRepository(Alunno::class)->find($cattedra['alunno_id']) : null);
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
  public function maggioDocente(Docente $docente) {
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
          ->setClasse($this->em->getRepository(Classe::class)->find($classe['classe_id']));
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
   * Imposta i destinatari di un documento
   *
   * @param Documento $documento Documento di cui impostare i destinatari
   * @param ListaDestinatari|null $destinatari Lista dei destinatari, o null se destinatari predefiniti
   */
  public function impostaDestinatari(Documento $documento, ListaDestinatari $destinatari=null) {
    if (!$destinatari) {
      $destinatari = new ListaDestinatari();
      $this->em->persist($destinatari);
      // destinatari predeterminati
      switch ($documento->getTipo()) {
        case 'B':   // diagnosi alunno BES
        case 'H':   // PEI
        case 'D':   // PDP
        case 'C':   // certificazione BES
          // crea destinatari: CdC
          $destinatari
            ->setSedi(new ArrayCollection([$documento->getAlunno()->getClasse()->getSede()]))
            ->setDocenti('C')
            ->setFiltroDocenti([$documento->getAlunno()->getClasse()->getId()]);
          break;
        case 'L':   // piani di lavoro
          // crea destinatari: CdC
          $destinatari
            ->setSedi(new ArrayCollection([$documento->getClasse()->getSede()]))
            ->setDocenti('C')
            ->setFiltroDocenti([$documento->getClasse()->getId()]);
          break;
        case 'P':   // programmi finali
        case 'M':   // documento 15 maggio
          // crea destinatari: CdC, genitori/alunni di classe
          $destinatari
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
    }
    // destinatari del documento
    $documento->setListaDestinatari($destinatari);
    // determina destinatari
    $utenti = [];
    $classi = [];
    $sedi = array_map(fn($ogg) => $ogg->getId(), $destinatari->getSedi()->toArray());
    // dsga
    if ($destinatari->getDsga()) {
      // aggiunge DSGA
      $utenti = $this->em->getRepository(Ata::class)->getIdDsga();
    }
    // ata
    if ($destinatari->getAta()) {
      // aggiunge ATA
      $utenti = array_merge($utenti, $this->em->getRepository(Ata::class)->getIdAta($sedi));
    }
    // docenti
    if ($destinatari->getDocenti() != 'N') {
      // controllo classi
      $filtroClassi = [];
      if ($destinatari->getDocenti() == 'C') {
        $filtroClassi = $destinatari->getFiltroDocenti();
        $articolate = $this->em->getRepository(Classe::class)->classiArticolate($filtroClassi);
        foreach ($articolate as $articolata) {
          if (!empty($articolata['comune'])) {
            $filtroClassi[] = $articolata['comune'];
          } else {
            $filtroClassi = array_merge($filtroClassi, $articolata['gruppi']);
          }
        }
      }
      // aggiunge docenti
      $utenti = array_merge($utenti, $this->em->getRepository(Docente::class)
        ->getIdDocente($sedi, $destinatari->getDocenti(),
          $destinatari->getDocenti() == 'C' ? $filtroClassi : $destinatari->getFiltroDocenti()));
    }
    // coordinatori
    if ($destinatari->getCoordinatori() != 'N') {
      // aggiunge coordinatori
      $utenti = array_merge($utenti, $this->em->getRepository(Docente::class)
        ->getIdCoordinatore($sedi, $destinatari->getCoordinatori() == 'C' ?
          $destinatari->getFiltroCoordinatori() : null));
    }
    // staff
    if ($destinatari->getStaff()) {
      // aggiunge staff
      $utenti = array_merge($utenti, $this->em->getRepository(Staff::class)->getIdStaff($sedi));
    }
    // genitori
    if ($destinatari->getGenitori() != 'N') {
      // aggiunge genitori
      $utenti = array_merge($utenti, $this->em->getRepository(Genitore::class)
        ->getIdGenitore($sedi, $destinatari->getGenitori(), $destinatari->getFiltroGenitori()));
      if ($destinatari->getGenitori() != 'U') {
        // aggiunge classi
        $classi = array_merge($classi, $this->em->getRepository(Classe::class)
          ->getIdClasse($sedi, $destinatari->getGenitori() == 'C' ? $destinatari->getFiltroGenitori() : null));
      }
    }
    // alunni
    if ($destinatari->getAlunni() != 'N') {
      // aggiunge alunni
      $utenti = array_merge($utenti, $this->em->getRepository(Alunno::class)
        ->getIdAlunno($sedi, $destinatari->getAlunni(), $destinatari->getFiltroAlunni()));
      if ($destinatari->getAlunni() != 'U') {
        // aggiunge classi
        $classi = array_merge($classi, $this->em->getRepository(Classe::class)
          ->getIdClasse($sedi, $destinatari->getAlunni() == 'C' ? $destinatari->getFiltroAlunni() : null));
      }
    }
    // destinatari univoci
    $utenti = array_unique($utenti);
    $classi = array_unique($classi);
    // imposta utenti destinatari
    foreach ($utenti as $utente) {
      $obj = (new ListaDestinatariUtente())
        ->setListaDestinatari($destinatari)
        ->setUtente($this->em->getReference(Utente::class, $utente));
      $this->em->persist($obj);
    }
    // imposta classi destinatarie
    foreach ($classi as $classe) {
      $obj = (new ListaDestinatariClasse())
        ->setListaDestinatari($destinatari)
        ->setClasse($this->em->getReference(Classe::class, $classe));
      $this->em->persist($obj);
    }
  }

  /**
   * Cancella i destinatari di un documento
   *
   * @param Documento $documento Documento di cui cancellare i destinatari
   */
  public function cancellaDestinatari(Documento $documento) {
    // cancella utenti in lista
    $this->em->getRepository(ListaDestinatariUtente::class)->createQueryBuilder('ldu')
      ->delete()
      ->where('ldu.listaDestinatari=:destinatari')
			->setParameter('destinatari', $documento->getListaDestinatari())
      ->getQuery()
      ->execute();
    // cancella classi in lista
    $this->em->getRepository(ListaDestinatariClasse::class)->createQueryBuilder('ldc')
      ->delete()
      ->where('ldc.listaDestinatari=:destinatari')
			->setParameter('destinatari', $documento->getListaDestinatari())
      ->getQuery()
      ->execute();
    // cancella lista
    $this->em->remove($documento->getListaDestinatari());
  }

  /**
   * Restituisce la directory del documento
   *
   * @param Documento $documento Documento di cui impostare i destinatari
   *
   * @return string Percorso completo della directory
   */
  public function documentoDir(Documento $documento) {
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
   * Controlla se l'utente è autorizzato alla lettura del documento
   *
   * @param Utente $utente Utente da controllare
   * @param Documento $documento Documento da controllare
   *
   * @return boolean Restituisce vero se l'utente è autorizzato alla lettura, falso altrimenti
   */
  public function permessoLettura(Utente $utente, Documento $documento) {
    if ($utente->getId() == $documento->getDocente()->getId() ||
        ($utente instanceOf Staff)) {
      // utente è autore di documento o fa parte di staff: ok
      return true;
    }
    if (!empty($this->em->getRepository(ListaDestinatariUtente::class)->findOneBy([
        'listaDestinatari' => $documento->getListaDestinatari(), 'utente' => $utente]))) {
      // utente è tra i destinatari: ok
      return true;
    }
    if (in_array($documento->getTipo(), ['L', 'P', 'R']) && ($utente instanceOf Docente)) {
      // documento di tipo relazione e utente docente
      $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['attiva' => 1,
        'docente' => $utente, 'classe' => $documento->getClasse(), 'materia' => $documento->getMateria()]);
      if ($cattedra && $cattedra->getTipo() != 'P' && $documento->getMateria()->getTipo() != 'E' &&
          $documento->getMateria()->getTipo() != 'S') {
        // cattedra docente esiste (escluso potenziamento, Ed.Civica e sostegno)
        return true;
      }
    }
    if (in_array($documento->getTipo(), ['B', 'H', 'D', 'C']) && ($utente instanceOf Docente)) {
      // documento PEI/PDP/diagnosi/altro e utente docente
      if ($utente->getResponsabileBes() && $documento->getAlunno() && $documento->getAlunno()->getClasse() &&
          (!$utente->getResponsabileBesSede() ||
          $utente->getResponsabileBesSede()->getId() == $documento->getAlunno()->getClasse()->getSede()->getId())) {
        // utente responsabile BES di scuola o di stessa sede di alunno: ok
        return true;
      }
    }
    // non autorizzato
    return false;
  }

  /**
   * Segna la lettura del documento da parte di un utente (non memorizza su db)
   *
   * @param Utente $utente Utente che esegue la lettura
   * @param Documento $documento Documento letto
   */
  public function leggeUtente(Utente $utente, Documento $documento) {
    // dati lettura utente
    $ldu = $this->em->getRepository(ListaDestinatariUtente::class)->findOneBy([
      'listaDestinatari' => $documento->getListaDestinatari(), 'utente' => $utente]);
    if ($ldu && !$ldu->getLetto()) {
      // imposta lettura
      $ldu->setLetto(new DateTime());
    }
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
  public function docenti($criteri, $pagina, Sede $sede=null) {
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
        ->where('c.attiva=:attiva AND c.tipo!=:potenziamento')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameter('classe', $cattedra['classe_id'])
        ->setParameter('materia', $cattedra['materia_id'])
        ->setParameter('attiva', 1)
        ->setParameter('potenziamento', 'P');
      if ($criteri['tipo'] == 'R' && $cattedra['alunno_id']) {
        // relazioni di sostegno
        $docenti
          ->andWhere('c.alunno=:alunno')
          ->setParameter('alunno', $cattedra['alunno_id']);
        $dati['documenti'][$i] = $this->em->getRepository(Documento::class)->createQueryBuilder('d')
          ->join('d.docente', 'doc')
          ->where('d.tipo=:documento AND d.classe=:classe AND d.materia=:materia AND d.alunno=:alunno')
          ->orderBy('doc.cognome,doc.nome', 'ASC')
          ->setParameter('documento', 'R')
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
  public function besDocente($criteri, Docente $docente, $pagina) {
    // genera documento fittizio per l'azione ADD
    $documentoAdd = (new Documento)
      ->setTipo('B');
    // estrae dati alunni
    $dati = $this->em->getRepository(Documento::class)->bes($criteri, $pagina, $docente->getResponsabileBesSede());
    foreach ($dati['lista'] as $i=>$alunno) {
      // dati documenti
      $dati['documenti'][$i]['lista'] = $this->em->getRepository(Documento::class)->createQueryBuilder('d')
        ->join('d.alunno', 'a')
        ->where("d.tipo IN ('B', 'H', 'D', 'C') AND d.alunno=:alunno AND d.stato='P'")
        ->orderBy('d.tipo', 'DESC')
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
        if (count($dati['documenti'][$i]['lista']) < 3 &&
            $this->azioneDocumento('add', $docente, $documentoAdd)) {
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
   * Restituisce il nome di file normalizzato
   *
   * @param string $nome Nome di file da normalizzare
   *
   * @return string Nome di file normalizzato
   */
  public function normalizzaNome($nome) {
    $testo = mb_strtoupper($nome, 'UTF-8');
    $testo = str_replace(['À', 'È', 'É', 'Ì', 'Ò', 'Ù'], ['A', 'E', 'E', 'I', 'O', 'U'], $testo);
    $testo = preg_replace('/\W+/','-', $testo);
    while (str_ends_with((string) $testo, '-')) {
      $testo = substr((string) $testo, 0, -1);
    }
    return $testo;
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
  public function alunni($criteri, $pagina, Sede $sede=null) {
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
   * Codifica i file del documento
   * NB: si presuppone che tutti i file siano in formato PDF
   *
   * @param Documento $documento Documento da codificare
   *
   * @return boolean Vero se codifica è avvenuta correttamente, falso altrimenti
   */
  public function codificaDocumento(Documento $documento) {
    // crea password
    $minuscolo = "abcdefghijklmnopqrstuvwxyz";
    $maiuscolo = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $cifre = "1234567890";
    $password = substr(str_shuffle($minuscolo), 0, 5).substr(str_shuffle($maiuscolo), 0, 5).
      substr(str_shuffle($cifre), 0, 5);
    $password = substr(str_shuffle($password), 0, 10);
    // imposta password per i file del documento
    $documento->setCifrato($password);
    $dir = $this->documentoDir($documento);
    foreach ($documento->getAllegati() as $file) {
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
   * Recupera i documenti archiviati per gli alunni BES, secondo i criteri indicati
   *
   * @param array $criteri Lista con i criteri di ricerca
   * @param Docente $docente Docente responsabile BES
   * @param int $pagina Indica il numero di pagina da visualizzare
   *
   * @return array Dati formattati come array associativo
   */
  public function archivioBes($criteri, Docente $docente, int $pagina): array {
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
            $alunno->getClasse()->getSede() == $docente->getResponsabileBesSede())) {
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

}
