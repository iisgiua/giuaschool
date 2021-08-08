<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use App\Entity\Utente;
use App\Entity\Docente;
use App\Entity\Staff;
use App\Entity\Documento;
use App\Entity\ListaDestinatari;
use App\Entity\ListaDestinatariUtente;
use App\Entity\ListaDestinatariClasse;
use App\Entity\File;


/**
 * DocumentiUtil - classe di utilità per la gestione dei documenti di classe
 */
class DocumentiUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var string $dirTemp Percorso della directory per i file temporanei
   */
  private $dirTemp;

  /**
   * @var string $dirClassi Percorso della directory per l'archivio delle classi
   */
  private $dirClassi;

  /**
   * @var string $dirUpload Percorso della directory per i file di upload
   */
  private $dirUpload;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param string $dirTemp Percorso della directory per i file temporanei
   * @param string $dirArchivio Percorso della directory per l'archivio dei documenti
   * @param string $dirUpload Percorso della directory per i file di upload
   */
  public function __construct(EntityManagerInterface $em, $dirTemp, $dirArchivio, $dirUpload) {
    $this->em = $em;
    $this->dirTemp = $dirTemp;
    $this->dirClassi = $dirArchivio.'/classi';
    $this->dirUpload = $dirUpload;
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
    $cattedre = $this->em->getRepository('App:Documento')->piani($docente);
    foreach ($cattedre as $cattedra) {
      $id = $cattedra['cattedra_id'];
      $dati[$id] = $cattedra;
      if (empty($cattedra['documento'])) {
        // documento non presente
        $dati[$id]['documento'] = null;
        // genera documento fittizio
        $documento = (new Documento)
          ->setTipo('L')
          ->setClasse($this->em->getRepository('App:Classe')->find($cattedra['classe_id']))
          ->setMateria($this->em->getRepository('App:Materia')->find($cattedra['materia_id']));
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
   *
   * @return array Dati formattati come array associativo
   */
  public function programmiDocente(Docente $docente) {
    $dati = [];
    $cattedre = $this->em->getRepository('App:Documento')->programmi($docente);
    foreach ($cattedre as $cattedra) {
      $id = $cattedra['cattedra_id'];
      $dati[$id] = $cattedra;
      if (empty($cattedra['documento'])) {
        // documento non presente
        $dati[$id]['documento'] = null;
        // genera documento fittizio
        $documento = (new Documento)
          ->setTipo('P')
          ->setClasse($this->em->getRepository('App:Classe')->find($cattedra['classe_id']))
          ->setMateria($this->em->getRepository('App:Materia')->find($cattedra['materia_id']));
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
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneDocumento($azione, Docente $docente, Documento $documento) {
    switch ($azione) {
      case 'add':     // crea
        if (!$documento->getId()) {
          // documento non esiste su db
          if (in_array($documento->getTipo(), ['L', 'P', 'R'])) {
            // piano lavoro/programma/relazione
            $cattedra = $this->em->getRepository('App:Cattedra')->findOneBy(['attiva' => 1,
              'docente' => $docente, 'classe' => $documento->getClasse(), 'materia' => $documento->getMateria(),
              'alunno' => $documento->getAlunno()]);
            if ($cattedra && $cattedra->getTipo() != 'P' && $documento->getMateria()->getTipo() != 'E') {
              // cattedra docente esiste (escluso potenziamento e Ed.Civica)
              if ($documento->getMateria()->getTipo() == 'S' && $documento->getTipo() == 'R') {
                // relazione finale di sostegno: ok
                return true;
              }
              if ($documento->getMateria()->getTipo() != 'S' &&
                  ($documento->getClasse()->getAnno() != 5 || $documento->getTipo() == 'L')) {
                // cattedra curricolare, escluso quinte per programmi e relazioni: ok
                return true;
              }
            }
          } elseif ($documento->getTipo() == 'M' && $documento->getClasse()->getAnno() == 5 &&
                    $documento->getClasse()->getCoordinatore() &&
                    $docente->getId() == $documento->getClasse()->getCoordinatore()->getId()) {
            // documento 15 maggio e docente coordinatore di quinta: ok
            return true;
          } else {
            // altri documenti
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
          if (in_array($documento->getTipo(), ['L', 'P', 'R'])) {
            // documento: piano lavoro/programma/relazione
            $cattedra = $this->em->getRepository('App:Cattedra')->findOneBy(['attiva' => 1,
              'docente' => $docente, 'classe' => $documento->getClasse(), 'materia' => $documento->getMateria(),
              'alunno' => $documento->getAlunno()]);
            if ($cattedra && $cattedra->getTipo() != 'P' && $documento->getMateria()->getTipo() != 'E') {
              // cattedra docente esiste (escluso potenziamento e Ed.Civica)
              if ($documento->getMateria()->getTipo() == 'S' && $documento->getTipo() == 'R') {
                // relazione finale di sostegno: ok
                return true;
              }
              if ($documento->getMateria()->getTipo() != 'S' &&
                  ($documento->getClasse()->getAnno() != 5 || $documento->getTipo() == 'L')) {
                // cattedra curricolare, escluso quinte per programmi e relazioni: ok
                return true;
              }
            }
          } elseif ($documento->getTipo() == 'M' && $documento->getClasse()->getAnno() == 5 &&
                    $documento->getClasse()->getCoordinatore() &&
                    $docente->getId() == $documento->getClasse()->getCoordinatore()->getId()) {
            // documento 15 maggio e docente coordinatore: ok
            return true;
          } else {
            // altri documenti
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
        if ($proc->isSuccessful() && file_exists($this->dirTemp.'/'.$file.'.pdf')) {
          // conversione ok
          unlink($this->dirTemp.'/'.$file.'.'.$estensione);
          $estensione = 'pdf';
        }
      } catch (\Exception $err) {
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
      $documento->getClasse()->getAnno().$documento->getClasse()->getSezione() : null;
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
    }
    $nome = strtoupper(preg_replace('/\W+/','-', $nome));
    if (substr($nome, -1) == '-') {
      $nome = substr($nome, 0, -1);
    }
    $nomefile = $nome;
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
    $cattedre = $this->em->getRepository('App:Documento')->relazioni($docente);
    foreach ($cattedre as $cattedra) {
      $id = $cattedra['cattedra_id'];
      $dati[$id] = $cattedra;
      if (empty($cattedra['documento'])) {
        // documento non presente
        $dati[$id]['documento'] = null;
        // genera documento fittizio
        $documento = (new Documento)
          ->setTipo('R')
          ->setClasse($this->em->getRepository('App:Classe')->find($cattedra['classe_id']))
          ->setMateria($this->em->getRepository('App:Materia')->find($cattedra['materia_id']))
          ->setAlunno($cattedra['alunno_id'] ?
            $this->em->getRepository('App:Alunno')->find($cattedra['alunno_id']) : null);
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
    $classi = $this->em->getRepository('App:Documento')->maggio($docente);
    foreach ($classi as $classe) {
      $id = $classe['classe_id'];
      $dati[$id] = $classe;
      if (empty($classe['documento'])) {
        // documento non presente
        $dati[$id]['documento'] = null;
        // genera documento fittizio
        $documento = (new Documento)
          ->setTipo('M')
          ->setClasse($this->em->getRepository('App:Classe')->find($classe['classe_id']));
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
    $documento->setListaDestinatari($destinatari);;
    // determina destinatari
    $utenti = array();
    $classi = array();
    $sedi = array_map(function($ogg) { return $ogg->getId(); }, $destinatari->getSedi()->toArray());
    // dsga
    if ($destinatari->getDsga()) {
      // aggiunge DSGA
      $utenti = $this->em->getRepository('App:Ata')->getIdDsga();
    }
    // ata
    if ($destinatari->getAta()) {
      // aggiunge ATA
      $utenti = array_merge($utenti, $this->em->getRepository('App:Ata')->getIdAta($sedi));
    }
    // docenti
    if ($destinatari->getDocenti() != 'N') {
      // aggiunge docenti
      $utenti = array_merge($utenti, $this->em->getRepository('App:Docente')
        ->getIdDocente($sedi, $destinatari->getDocenti(), $destinatari->getFiltroDocenti()));
    }
    // coordinatori
    if ($destinatari->getCoordinatori() != 'N') {
      // aggiunge coordinatori
      $utenti = array_merge($utenti, $this->em->getRepository('App:Docente')
        ->getIdCoordinatore($sedi, $destinatari->getCoordinatori() == 'C' ?
          $destinatari->getFiltroCoordinatori() : null));
    }
    // staff
    if ($destinatari->getStaff()) {
      // aggiunge staff
      $utenti = array_merge($utenti, $this->em->getRepository('App:Staff')->getIdStaff($sedi));
    }
    // genitori
    if ($destinatari->getGenitori() != 'N') {
      // aggiunge genitori
      $utenti = array_merge($utenti, $this->em->getRepository('App:Genitore')
        ->getIdGenitore($sedi, $destinatari->getGenitori(), $destinatari->getFiltroGenitori()));
      if ($destinatari->getGenitori() != 'U') {
        // aggiunge classi
        $classi = array_merge($classi, $this->em->getRepository('App:Classe')
          ->getIdClasse($sedi, $destinatari->getGenitori() == 'C' ? $destinatari->getFiltroGenitori() : null));
      }
    }
    // alunni
    if ($destinatari->getAlunni() != 'N') {
      // aggiunge alunni
      $utenti = array_merge($utenti, $this->em->getRepository('App:Alunno')
        ->getIdAlunno($sedi, $destinatari->getAlunni(), $destinatari->getFiltroAlunni()));
      if ($destinatari->getAlunni() != 'U') {
        // aggiunge classi
        $classi = array_merge($classi, $this->em->getRepository('App:Classe')
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
        ->setUtente($this->em->getReference('App:Utente', $utente));
      $this->em->persist($obj);
    }
    // imposta classi destinatarie
    foreach ($classi as $classe) {
      $obj = (new ListaDestinatariClasse())
        ->setListaDestinatari($destinatari)
        ->setClasse($this->em->getReference('App:Classe', $classe));
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
    $this->em->getRepository('App:ListaDestinatariUtente')->createQueryBuilder('ldu')
      ->delete()
      ->where('ldu.listaDestinatari=:destinatari')
      ->setParameters(['destinatari' => $documento->getListaDestinatari()])
      ->getQuery()
      ->execute();
    // cancella classi in lista
    $this->em->getRepository('App:ListaDestinatariClasse')->createQueryBuilder('ldc')
      ->delete()
      ->where('ldc.listaDestinatari=:destinatari')
      ->setParameters(['destinatari' => $documento->getListaDestinatari()])
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
      $dir = $this->dirUpload;
    } else {
      // altri documenti in archivio classi
      $dir = $this->dirClassi.'/'.$documento->getClasse()->getAnno().$documento->getClasse()->getSezione();
      if (in_array($documento->getTipo(), ['H', 'D', 'C'])) {
        // documenti riservati
        $dir .= '/riservato';
      }
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
    if (!empty($this->em->getRepository('App:ListaDestinatariUtente')->findOneBy([
        'listaDestinatari' => $documento->getListaDestinatari(), 'utente' => $utente]))) {
      // utente è tra i destinatari: ok
      return true;
    }
    if ($documento->getTipo() == 'R' && ($utente instanceOf Docente)) {
      // documento di tipo relazione e utente docente
      $cattedra = $this->em->getRepository('App:Cattedra')->findOneBy(['attiva' => 1,
        'docente' => $utente, 'classe' => $documento->getClasse(), 'materia' => $documento->getMateria()]);
      if ($cattedra && $cattedra->getTipo() != 'P' && $documento->getMateria()->getTipo() != 'E' &&
          $documento->getMateria()->getTipo() != 'P') {
        // cattedra docente esiste (escluso potenziamento, Ed.Civica e sostegno)
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
   *
   * @return boolean Restituisce vero se l'utente è autorizzato alla lettura, falso altrimenti
   */
  public function leggeUtente(Utente $utente, Documento $documento) {
    // dati lettura utente
    $ldu = $this->em->getRepository('App:ListaDestinatariUtente')->findOneBy([
      'listaDestinatari' => $documento->getListaDestinatari(), 'utente' => $utente]);
    if ($ldu && !$ldu->getLetto()) {
      // imposta lettura
      $ldu->setLetto(new \DateTime());
    }
  }

  /**
   * Recupera i documenti dei docenti secondo i criteri indicati
   *
   * @param array $criteri Criteri di ricerca
   * @param int $pagina Indica il numero di pagina da visualizzare
   *
   * @return array Dati formattati come array associativo
   */
  public function docenti($criteri, $pagina) {
    // legge cattedre
    $dati = $this->em->getRepository('App:Documento')->docenti($criteri, $pagina);
    if ($criteri['tipo'] == 'M') {
      // documento del 15 maggio: niente da aggiungere
      return $dati;
    }
    // aggiunge info
    foreach ($dati['lista'] as $i=>$cattedra) {
      // query base docenti
      $docenti = $this->em->getRepository('App:Docente')->createQueryBuilder('d')
        ->select('d.cognome,d.nome')
        ->join('App:Cattedra', 'c', 'WITH', 'c.docente=d.id AND c.classe=:classe AND c.materia=:materia')
        ->where('c.attiva=:attiva AND c.tipo!=:potenziamento')
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->setParameters(['classe' => $cattedra['classe_id'], 'materia' => $cattedra['materia_id'],
          'attiva' => 1, 'potenziamento' => 'P']);
      if ($criteri['tipo'] == 'R' && $cattedra['alunno_id']) {
        // relazioni di sostegno
        $docenti
          ->andWhere('c.alunno=:alunno')
          ->setParameter('alunno', $cattedra['alunno_id']);
        $dati['documenti'][$i] = $this->em->getRepository('App:Documento')->createQueryBuilder('d')
          ->join('d.docente', 'doc')
          ->where('d.tipo=:documento AND d.classe=:classe AND d.materia=:materia AND d.alunno=:alunno')
          ->orderBy('doc.cognome,doc.nome', 'ASC')
          ->setParameters(['documento' => 'R', 'classe' => $cattedra['classe_id'],
            'materia' => $cattedra['materia_id'], 'alunno' => $cattedra['alunno_id']])
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

}
