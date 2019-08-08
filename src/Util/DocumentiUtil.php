<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use App\Entity\Docente;
use App\Entity\Documento;


/**
 * DocumentiUtil - classe di utilità per la gestione dei documenti di classe
 */
class DocumentiUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var RouterInterface $router Gestore delle URL
   */
  private $router;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var TranslatorInterface $trans Gestore delle traduzioni
   */
  private $trans;

  /**
   * @var string $root Directory principale dell'applicazione
   */
  private $root;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param string $root Directory principale dell'applicazione
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               $root) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->root = $root;
  }

  /**
   * Recupera i programmi svolti del docente indicato
   *
   * @param Docente $docente Docente selezionato
   *
   * @return Array Dati formattati come array associativo
   */
  public function programmi(Docente $docente) {
    $dir = $this->root.'/';
    $dati = null;
    // lista cattedre e programmi
    $cattedre = $this->em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id AS cattedra,cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,d.id AS documento_id,d.file,d.dimensione')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('App:Documento', 'd', 'WHERE', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
      ->where('c.docente=:docente AND c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo!=:sostegno AND cl.anno!=:quinta')
      ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
      ->setParameters(['docente' => $docente, 'attiva' => 1, 'potenziamento' => 'P', 'sostegno' => 'S',
        'documento' => 'P', 'quinta' => 5])
      ->getQuery()
      ->getArrayResult();
    foreach ($cattedre as $k=>$c) {
      $dati[$k] = $c;
      if ($c['documento_id']) {
        // dati documento
        $dati[$k]['dimensione'] = number_format($c['dimensione'] / 1000, 0, ',', '.');
        // controlla azioni
        $documento = $this->em->getRepository('App:Documento')->find($c['documento_id']);
        // azione edit
        if ($this->azioneDocumento('edit', new \DateTime(), $docente, $documento)) {
          $dati[$k]['edit'] = 1;
        }
        // azione edit
        if ($this->azioneDocumento('delete', new \DateTime(), $docente, $documento)) {
          $dati[$k]['delete'] = 1;
        }
      } else {
        // azione add
        if ($this->azioneDocumento('add', new \DateTime(), $docente, null)) {
          $dati[$k]['add'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente ai documenti.
   *
   * @param string $azione Azione da controllare
   * @param \DateTime $data Data dell'evento
   * @param Docente $docente Docente che esegue l'azione
   * @param Documento $documento Documento su cui eseguire l'azione
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneDocumento($azione, \DateTime $data, Docente $docente, Documento $documento=null) {
    if ($azione == 'add') {
      // azione di creazione
      if (!$documento) {
        // nuovo documento
        return true;
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if ($documento) {
        // esiste documento
        if ($docente->getId() == $documento->getDocente()->getId() ||
            in_array('ROLE_STAFF', $documento->getDocente()->getRoles())) {
          // stesso docente o docente dello staff: ok
          return true;
        } elseif ($documento->getMateria()) {
          // documento relativo a classe/materia
          $cattedra = $this->em->getRepository('App:Cattedra')->createQueryBuilder('c')
            ->where('c.docente=:docente AND c.attiva=:attiva AND c.classe=:classe AND c.materia=:materia AND c.tipo!=:potenziamento')
            ->setParameters(['docente' => $docente, 'attiva' => 1, 'classe' => $documento->getClasse(),
              'materia' => $documento->getMateria(), 'potenziamento' => 'P'])
            ->getQuery()
            ->getResult();
          if (!empty($cattedra)) {
            // docente ha stessa cattedra (no potenziamento): ok
            return true;
          }
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($documento) {
        // esiste documento
        if ($docente->getId() == $documento->getDocente()->getId() ||
            in_array('ROLE_STAFF', $documento->getDocente()->getRoles())) {
          // stesso docente o docente dello staff: ok
          return true;
        } elseif ($documento->getMateria()) {
          // documento relativo a classe/materia
          $cattedra = $this->em->getRepository('App:Cattedra')->createQueryBuilder('c')
            ->where('c.docente=:docente AND c.attiva=:attiva AND c.classe=:classe AND c.materia=:materia AND c.tipo!=:potenziamento')
            ->setParameters(['docente' => $docente, 'attiva' => 1, 'classe' => $documento->getClasse(),
              'materia' => $documento->getMateria(), 'potenziamento' => 'P'])
            ->getQuery()
            ->getResult();
          if (!empty($cattedra)) {
            // docente ha stessa cattedra (no potenziamento): ok
            return true;
          }
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Esegue la conversione in formato PDF del file indicato
   *
   * @param File $file Documento da convertire
   *
   * @return string Restituisce l'estensione del file convertito
   */
  public function convertiPDF(File $file) {
    $fl = $file;
    $ext = $file->guessExtension();
    if ($ext != 'pdf') {
      // conversione
      $nomefile = $file->getRealPath();
      $proc = new Process('/usr/bin/unoconv -f pdf -d document "'.$nomefile.'"', $file->getPath());
      $proc->run();
      if ($proc->isSuccessful() && file_exists($nomefile.'.pdf')) {
        // conversione ok
        $fs = new FileSystem();
        $fs->remove($file);
        $fl = new File($nomefile.'.pdf');
      }
    }
    // restituisce estensione di nuovo file
    return $fl;
  }

  /**
   * Recupera le relazioni finali del docente indicato
   *
   * @param Docente $docente Docente selezionato
   *
   * @return Array Dati formattati come array associativo
   */
  public function relazioni(Docente $docente) {
    $dir = $this->root.'/';
    $dati = null;
    // lista cattedre e relazioni
    $cattedre = $this->em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id AS cattedra,cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,d.id AS documento_id,d.file,d.dimensione')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('App:Documento', 'd', 'WHERE', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
      ->where('c.docente=:docente AND c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo!=:sostegno AND cl.anno!=:quinta')
      ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
      ->setParameters(['docente' => $docente, 'attiva' => 1, 'potenziamento' => 'P', 'sostegno' => 'S',
        'documento' => 'R', 'quinta' => 5])
      ->getQuery()
      ->getArrayResult();
    foreach ($cattedre as $k=>$c) {
      $dati[$k] = $c;
      if ($c['documento_id']) {
        // dati documento
        $dati[$k]['dimensione'] = number_format($c['dimensione'] / 1000, 0, ',', '.');
        // controlla azioni
        $documento = $this->em->getRepository('App:Documento')->find($c['documento_id']);
        // azione edit
        if ($this->azioneDocumento('edit', new \DateTime(), $docente, $documento)) {
          $dati[$k]['edit'] = 1;
        }
        // azione edit
        if ($this->azioneDocumento('delete', new \DateTime(), $docente, $documento)) {
          $dati[$k]['delete'] = 1;
        }
      } else {
        // azione add
        if ($this->azioneDocumento('add', new \DateTime(), $docente, null)) {
          $dati[$k]['add'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i piani di lavoro del docente indicato
   *
   * @param Docente $docente Docente selezionato
   *
   * @return Array Dati formattati come array associativo
   */
  public function piani(Docente $docente) {
    $dir = $this->root.'/';
    $dati = null;
    // lista cattedre e relazioni
    $cattedre = $this->em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('c.id AS cattedra,cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,m.id AS materia_id,m.nome AS materia,m.nomeBreve AS materiaBreve,d.id AS documento_id,d.file,d.dimensione')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('App:Documento', 'd', 'WHERE', 'd.tipo=:documento AND d.classe=cl.id AND d.materia=m.id')
      ->where('c.docente=:docente AND c.attiva=:attiva AND c.tipo!=:potenziamento AND m.tipo!=:sostegno')
      ->orderBy('cl.anno,cl.sezione,m.nome', 'ASC')
      ->setParameters(['docente' => $docente, 'attiva' => 1, 'potenziamento' => 'P', 'sostegno' => 'S',
        'documento' => 'L'])
      ->getQuery()
      ->getArrayResult();
    foreach ($cattedre as $k=>$c) {
      $dati[$k] = $c;
      if ($c['documento_id']) {
        // dati documento
        $dati[$k]['dimensione'] = number_format($c['dimensione'] / 1000, 0, ',', '.');
        // controlla azioni
        $documento = $this->em->getRepository('App:Documento')->find($c['documento_id']);
        // azione edit
        if ($this->azioneDocumento('edit', new \DateTime(), $docente, $documento)) {
          $dati[$k]['edit'] = 1;
        }
        // azione edit
        if ($this->azioneDocumento('delete', new \DateTime(), $docente, $documento)) {
          $dati[$k]['delete'] = 1;
        }
      } else {
        // azione add
        if ($this->azioneDocumento('add', new \DateTime(), $docente, null)) {
          $dati[$k]['add'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i documenti dei Consigli di Classe del docente indicato
   *
   * @param Docente $docente Docente selezionato
   * @param array $search Criteri di ricerca
   * @param int $pagina Pagina corrente
   * @param int $limite Numero di elementi per pagina
   *
   * @return Array Dati formattati come array associativo
   */
  public function classi(Docente $docente, $search, $pagina, $limite) {
    // lista documenti
    $param = ['tipi' => ['L', 'M'], 'attiva' => 1, 'docente' => $docente];
    $documenti = $this->em->getRepository('App:Documento')->createQueryBuilder('d')
      ->join('d.classe', 'cl')
      ->join('App:Cattedra', 'ca', 'WHERE', 'ca.classe=cl.id')
      ->leftJoin('d.materia', 'm')
      ->where('d.tipo IN (:tipi) AND ca.attiva=:attiva AND ca.docente=:docente');
    if ($search['classe']) {
      $documenti = $documenti
        ->andWhere('cl.id=:classe');
      $param['classe'] = $search['classe'];
    }
    if ($search['tipo']) {
      $documenti = $documenti
        ->andWhere('d.tipo=:tipo');
      $param['tipo'] = $search['tipo'];
    }
    $documenti = $documenti
      ->groupBy('cl.anno,cl.sezione,d.tipo,m.nomeBreve')
      ->orderBy('cl.anno,cl.sezione,d.tipo,m.nomeBreve', 'ASC')
      ->setParameters($param)
      ->getQuery();
    // paginazione
    $paginator = new Paginator($documenti);
    $paginator->getQuery()
      ->setFirstResult($limite * ($pagina - 1))
      ->setMaxResults($limite);
    $dati['lista'] = $paginator;
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i documenti del 15 maggio del docente indicato
   *
   * @param Docente $docente Docente selezionato
   *
   * @return Array Dati formattati come array associativo
   */
  public function doc15(Docente $docente) {
    $dir = $this->root.'/';
    $dati = null;
    // lista cattedre e relazioni
    $cattedre = $this->em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('DISTINCT cl.id AS classe_id,cl.anno,cl.sezione,co.nomeBreve AS corso,s.citta AS sede,d.id AS documento_id,d.file,d.dimensione')
      ->join('c.classe', 'cl')
      ->join('cl.corso', 'co')
      ->join('cl.sede', 's')
      ->leftJoin('App:Documento', 'd', 'WHERE', 'd.tipo=:documento AND d.classe=cl.id')
      ->where('c.docente=:docente AND c.attiva=:attiva AND c.tipo!=:potenziamento AND cl.anno=:quinta AND cl.coordinatore=:docente')
      ->orderBy('cl.anno,cl.sezione', 'ASC')
      ->setParameters(['docente' => $docente, 'attiva' => 1, 'potenziamento' => 'P', 'quinta' => 5, 'documento' => 'M'])
      ->getQuery()
      ->getArrayResult();
    foreach ($cattedre as $k=>$c) {
      $dati[$k] = $c;
      if ($c['documento_id']) {
        // dati documento
        $dati[$k]['dimensione'] = number_format($c['dimensione'] / 1000, 0, ',', '.');
        // controlla azioni
        $documento = $this->em->getRepository('App:Documento')->find($c['documento_id']);
        // azione edit
        if ($this->azioneDocumento('edit', new \DateTime(), $docente, $documento)) {
          $dati[$k]['edit'] = 1;
        }
        // azione edit
        if ($this->azioneDocumento('delete', new \DateTime(), $docente, $documento)) {
          $dati[$k]['delete'] = 1;
        }
      } else {
        // azione add
        if ($this->azioneDocumento('add', new \DateTime(), $docente, null)) {
          $dati[$k]['add'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

}

