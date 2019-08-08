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


namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\File;
use App\Entity\Classe;


/**
 * Comando per validare gli esiti degli scrutini importati in Argo
 */
class ScrutinioValidaCommand extends Command {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var LoggerInterface $logger Gestore dei log su file
   */
  private $logger;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var \Twig\Environment $tpl Gestione template
   */
  private $tpl;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param \Twig\Environment $tpl Gestione template
   */
  public function __construct(EntityManagerInterface $em, \Twig\Environment $tpl) {
    parent::__construct();
    $this->em = $em;
    $this->tpl = $tpl;
  }

  /**
   * Configura la sintassi del comando
   *
   */
  protected function configure() {
    // nome del comando (da inserire dopo "php bin/console")
    $this->setName('app:scrutinio:valida');
    // breve descrizione (mostrata col comando "php bin/console list")
    $this->setDescription('Valida gli esiti degli scrutini del periodo indicato');
    // descrizione completa (mostrata con l'opzione "--help")
    $this->setHelp("Il comando permette di validare gli esiti degli scrutini importati in ARGO per il periodo indicato.");
    // argomenti del comando
    $this->addArgument('periodo', InputArgument::REQUIRED, 'Periodo dello scrutinio (codificato in un carattere)');
    $this->addArgument('classe', InputArgument::OPTIONAL, 'Singola classe da importare');
  }

  /**
   * Usato per inizializzare le variabili prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->logger = $this->getContainer()->get('monolog.logger.command');
  }

  /**
   * Usato per validare gli argomenti prima dell'esecuzione
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    // controlla il periodo
    $periodo = $input->getArgument('periodo');
    if (!in_array($periodo, ['P', 'F', 'I'])) {
      // errore
      throw new InvalidArgumentException('Il periodo specificato non è valido.');
    }
    // controlla classe
    $classe = $input->getArgument('classe');
    if ($classe && (strlen($classe) != 2 || $classe{0} < '1' || $classe{0} > '5' || $classe{1} < 'A' || $classe{1} > 'Z'))  {
      // errore
      throw new InvalidArgumentException('Classe non valida.');
    }
  }

  /**
   * Esegue il comando
   *
   * @param InputInterface $input Oggetto che gestisce l'input
   * @param OutputInterface $output Oggetto che gestisce l'output
   *
   * @return null|int Restituisce un valore nullo o 0 se tutto ok, altrimenti un codice di errore come numero intero
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // inizio
    $periodo = $input->getArgument('periodo');
    $this->logger->notice('scrutinio-valida: Inizio validazione', ['periodo' => $periodo]);
    $fs = new Filesystem();
    $params = ['periodo' => $periodo, 'stato' => 'C', 'sincronizzazione' => 'C'];
    // legge dir scrutini
    $dir = $this->getContainer()->getParameter('dir_scrutini');
    // legge classe
    $classe_par = $input->getArgument('classe');
    if ($classe_par) {
      $classe = $this->em->getRepository('App:Classe')->findOneBy(['anno' => $classe_par{0}, 'sezione' => $classe_par{1}]);
      if ($classe) {
        $params['classe'] = $classe;
      } else {
        // errore
        throw new InvalidArgumentException('Classe non valida.');
      }
    }
    // legge scrutini
    $scrutini = $this->em->getRepository('App:Scrutinio')->findBy($params);
    if ($periodo == 'P') {
      // primo trimestre
      $dir = $dir.'/trimestre/';
      foreach ($scrutini as $scrutinio) {
        $nomeclasse = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
        $msg = 'Validazione classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('scrutinio-valida: '.$msg, ['classe' => $nomeclasse]);
        $percorso = $dir.$nomeclasse.'/syncro';
        // legge dati da registro
        $dati = $this->datiVoti($scrutinio->getClasse(), $periodo);
        // confronta dati
        $nomefile = $percorso.'/stampa.pdf';
        $errore = $this->confrontaVoti($scrutinio->getClasse(), $periodo, $dati, $nomefile);
        if ($errore) {
          throw new \Exception($errore);
        }
        $msg = 'Confronto dei dati eseguito senza errori';
        $output->writeln($msg);
        $this->logger->notice('scrutinio-valida: '.$msg, ['classe' => $nomeclasse]);
        // cambia stato
        $scrutinio->setSincronizzazione('V');
        $this->em->flush();
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      $dir = $dir.'/finale/';
      foreach ($scrutini as $scrutinio) {
        $nomeclasse = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
        $msg = 'Validazione classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('scrutinio-valida: '.$msg, ['classe' => $nomeclasse]);
        $percorso = $dir.$nomeclasse.'/syncro';
        // legge dati da registro
        $dati = $this->datiVoti($scrutinio->getClasse(), $periodo);
        // confronta dati
        $nomefile = $percorso.'/stampa.pdf';
        $errore = $this->confrontaVoti($scrutinio->getClasse(), $periodo, $dati, $nomefile);
        if ($errore) {
          throw new \Exception($errore);
        }
        $msg = 'Confronto dei dati eseguito senza errori';
        $output->writeln($msg);
        $this->logger->notice('scrutinio-valida: '.$msg, ['classe' => $nomeclasse]);
        // cambia stato
        $scrutinio->setSincronizzazione('V');
        $this->em->flush();
      }
    } elseif ($periodo == 'I') {
      // scrutinio integrativo
      $dir = $dir.'/integrativo/';
      foreach ($scrutini as $scrutinio) {
        $nomeclasse = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
        $msg = 'Validazione classe: '.$nomeclasse;
        $output->writeln($msg);
        $this->logger->notice('scrutinio-valida: '.$msg, ['classe' => $nomeclasse]);
        $percorso = $dir.$nomeclasse.'/syncro';
        // legge dati da registro
        $dati = $this->datiVoti($scrutinio->getClasse(), $periodo);
        // confronta dati
        $nomefile = $percorso.'/stampa.pdf';
        $errore = $this->confrontaVoti($scrutinio->getClasse(), $periodo, $dati, $nomefile);
        if ($errore) {
          throw new \Exception($errore);
        }
        $msg = 'Confronto dei dati eseguito senza errori';
        $output->writeln($msg);
        $this->logger->notice('scrutinio-valida: '.$msg, ['classe' => $nomeclasse]);
        // cambia stato
        $scrutinio->setSincronizzazione('V');
        $this->em->flush();
      }
    }
    // ok, fine
    $this->logger->notice('scrutinio-valida: Fine validazione', ['periodo' => $periodo]);
    return 0;
  }


  //==================== FUNZIONI PRIVATE  ====================

  /**
   * Restituisce la lista degli alunni per lo scrutinio indicato
   *
   * @param Classe $classe Classe scolastica
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Lista degli ID degli alunni
   */
  private function alunniInScrutinio(Classe $classe, $periodo) {
    $alunni = array();
    $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['periodo' => $periodo, 'classe' => $classe]);
    if ($periodo == 'P' || $periodo == '1') {
      // solo gli alunni al momento dello scrutinio
      $alunni = $scrutinio->getDato('alunni');
    } elseif ($periodo == 'F') {
      // alunni scrutinati
      $scrutinati = ($scrutinio->getDato('scrutinabili') == null ? [] : array_keys($scrutinio->getDato('scrutinabili')));
      // alunni non scrutinati per cessata frequenza
      $cessata_frequenza = ($scrutinio->getDato('cessata_frequenza') == null ? [] : $scrutinio->getDato('cessata_frequenza'));
      // alunni non scrutinabili per limite di assenza
      $no_scrutinabili = array();
      $no_scrut = ($scrutinio->getDato('no_scrutinabili') == null ? [] : $scrutinio->getDato('no_scrutinabili'));
      foreach ($no_scrut as $alu=>$ns) {
        if (!isset($ns['deroga'])) {
          $no_scrutinabili[] = $alu;
        }
      }
      // alunni all'estero
      $alu_estero = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('App:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
        ->where('a.id IN (:lista) AND cc.classe=:classe AND a.frequenzaEstero=:estero')
        ->setParameters(['lista' => ($scrutinio->getDato('ritirati') == null ? [] : $scrutinio->getDato('ritirati')),
          'classe' => $classe, 'estero' => 1])
        ->getQuery()
        ->getArrayResult();
      $estero = ($alu_estero == null ? [] : array_column($alu_estero, 'id'));
      // alunni da considerare
      return array_merge($scrutinati, $cessata_frequenza, $no_scrutinabili, $estero);
    } elseif ($periodo == 'I') {
      // legge lista alunni sospesi
      $sospesi = $scrutinio->getDato('sospesi');
      return $sospesi;
    }
    // restituisce lista di ID
    return $alunni;
  }

  /**
   * Restituisce la situazione dei voti dello scrutinio
   *
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  private function datiVoti(Classe $classe, $periodo) {
    $dati = array();
    $codici['P']['N'] = [0 => 'N', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['P']['C'] = [4 => 'N', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['P']['R'] = [20 => 'N', 21 => 'I', 22 => 'S', 23 => 'B', 24 => 'D', 25 => 'O'];
    $codici['F']['N'] = [0 => 'NC', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['F']['C'] = [4 => 'NC', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['F']['R'] = [20 => 'NC', 21 => 'I', 22 => 'S', 23 => 'B', 24 => 'DIS', 25 => 'OTT'];
    $codici['I']['N'] = [0 => 'N', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['I']['C'] = [4 => 'N', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10'];
    $codici['I']['R'] = [20 => 'N', 21 => 'I', 22 => 'S', 23 => 'B', 24 => 'DIS', 25 => 'OTT'];
    $codici_esito = ['A' => 'Ammess', 'N' => 'Non Ammess', 'S' => 'Sospensione', 'NS' => 'Non Scrutinat'];
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.credito3,a.credito4')
      ->where('a.id in (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = array(
        'nome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
          mb_strtoupper($alu['nome'], 'UTF-8')),
        'cognome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
          mb_strtoupper($alu['cognome'], 'UTF-8')),
        'dataNascita' => $alu['dataNascita']->format('d/m/Y'),
        'sesso' => $alu['sesso'],
        'religione' => $alu['religione'],
        'credito3' => $alu['credito3'],
        'credito4' => $alu['credito4']);
    }
    // legge materie
    $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.tipo')
      ->join('App:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = array(
        'nome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
          mb_strtoupper($mat['nome'], 'UTF-8')),
        'tipo' => $mat['tipo']);
    }
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'nome' => str_replace(['À','È','É','Ì','Ò','Ù'], ["A'","E'","E'","I'","O'","U'"],
        mb_strtoupper($condotta->getNome(), 'UTF-8')),
      'tipo' => $condotta->getTipo());
    if ($periodo == 'P') {
      // legge i voti
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('s.classe=:classe AND s.periodo=:periodo AND vs.unico IS NOT NULL')
        ->setParameters(['classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $voto = $v->getUnico();
        if ($v->getMateria()->getTipo() == 'R') {
          // religione: tutti i giudizi
          $voto = $codici[$periodo]['R'][$v->getUnico()];
        } elseif ($v->getMateria()->getTipo() == 'C') {
          // condotta
          $voto = $codici[$periodo]['C'][$v->getUnico()];
        } elseif ($v->getMateria()->getTipo() == 'N') {
          // altre materie: NC
          $voto = $codici[$periodo]['N'][$v->getUnico()];
        }
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'unico' => $voto,
          'assenze' => $v->getAssenze());
        if ($v->getMateria()->getMedia()) {
          // esclude religione dalla media
          if (!isset($somma[$v->getAlunno()->getId()])) {
            $somma[$v->getAlunno()->getId()] = ($voto == 'N' ? 0 : $voto);
            $numero[$v->getAlunno()->getId()] = ($voto == 'N' ? 0 : 1);
          } else {
            $somma[$v->getAlunno()->getId()] += ($voto == 'N' ? 0 : $voto);
            $numero[$v->getAlunno()->getId()] += ($voto == 'N' ? 0 : 1);
          }
        }
      }
      // calcola medie
      foreach ($somma as $alu=>$s) {
        $dati['medie'][$alu] = number_format($numero[$alu] == 0 ? 0 : $somma[$alu] / $numero[$alu], 2);
      }
    } elseif ($periodo == 'F') {
      // legge i voti
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.alunno IN (:lista) AND vs.unico IS NOT NULL AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // legge voti
        $voto = $v->getUnico();
        if ($v->getMateria()->getTipo() == 'R') {
          // religione
          $voto = $codici[$periodo]['R'][$v->getUnico()];
        } elseif ($v->getMateria()->getTipo() == 'C') {
          // condotta
          $voto = ($v->getUnico() === null ? '' : $codici[$periodo]['C'][$v->getUnico()]);
        } elseif ($v->getMateria()->getTipo() == 'N') {
          // altre materie
          $voto = ($v->getUnico() === null ? '' : $codici[$periodo]['N'][$v->getUnico()]);
        }
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'unico' => $voto,
          'assenze' => $v->getAssenze());
        if ($v->getMateria()->getMedia()) {
          // esclude religione dalla media
          if (!isset($somma[$v->getAlunno()->getId()])) {
            $somma[$v->getAlunno()->getId()] = (($voto == 'N' || $voto == '') ? 0 : $voto);
            $numero[$v->getAlunno()->getId()] = 1;
          } else {
            $somma[$v->getAlunno()->getId()] += (($voto == 'N' || $voto == '') ? 0 : $voto);
            $numero[$v->getAlunno()->getId()]++;
          }
        }
      }
      // calcola medie
      foreach ($somma as $alu=>$s) {
        $dati['medie'][$alu] = number_format($numero[$alu] == 0 ? 0 : $somma[$alu] / $numero[$alu], 2);
      }
      // esiti e crediti
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
        ->join('e.scrutinio', 's')
        ->where('e.alunno IN (:lista) AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = array(
          'esito' => $codici_esito[$e->getEsito()],
          'credito' => $e->getCredito() === null ? 0 : $e->getCredito(),
          'creditoprec' => intval($e->getAlunno()->getCredito3()) + intval($e->getAlunno()->getCredito4()));
      }
      // scrutinabili
      $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo, 'stato' => 'C']);
      // alunni non scrutinati per cessata frequenza
      $dati['cessata_frequenza'] = array();
      $freq = ($scrutinio->getDato('cessata_frequenza') == null ? [] : $scrutinio->getDato('cessata_frequenza'));
      foreach ($freq as $alu) {
        $dati['cessata_frequenza'][$alu] = 1;
        $dati['esiti'][$alu]['esito'] = $codici_esito['NS'];
        $dati['esiti'][$alu]['credito'] = 0;
        $dati['esiti'][$alu]['creditoprec'] = $dati['alunni'][$alu]['credito3'] + $dati['alunni'][$alu]['credito4'];
        $dati['medie'][$alu] = number_format(0, 2);
        foreach ($dati['materie'] as $idmat=>$mat) {
          $dati['voti'][$alu][$idmat]['unico'] = '';
          $dati['voti'][$alu][$idmat]['assenze'] = '';
        }
      }
      // alunni non scrutinabili per limite di assenza
      $dati['no_scrutinabili'] = array();
      $no_scrut = ($scrutinio->getDato('no_scrutinabili') == null ? [] : $scrutinio->getDato('no_scrutinabili'));
      foreach ($no_scrut as $alu=>$ns) {
        if (!isset($ns['deroga'])) {
          $dati['no_scrutinabili'][$alu] = 1;
          $dati['esiti'][$alu]['esito'] = $codici_esito['N'];
          $dati['esiti'][$alu]['credito'] = 0;
          $dati['esiti'][$alu]['creditoprec'] = $dati['alunni'][$alu]['credito3'] + $dati['alunni'][$alu]['credito4'];
          $dati['medie'][$alu] = number_format(0, 2);
          foreach ($dati['materie'] as $idmat=>$mat) {
            $dati['voti'][$alu][$idmat]['unico'] = '';
          }
        }
      }
      // alunni all'estero
      $alu_estero = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('App:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
        ->where('a.id IN (:lista) AND cc.classe=:classe AND a.frequenzaEstero=:estero')
        ->setParameters(['lista' => ($scrutinio->getDato('ritirati') == null ? [] : $scrutinio->getDato('ritirati')),
          'classe' => $classe, 'estero' => 1])
        ->getQuery()
        ->getArrayResult();
      $estero = ($alu_estero == null ? [] : array_column($alu_estero, 'id'));
      foreach ($estero as $alu) {
        $dati['esiti'][$alu]['esito'] = '';
        $dati['esiti'][$alu]['credito'] = 0;
        $dati['esiti'][$alu]['creditoprec'] = $dati['alunni'][$alu]['credito3'] + $dati['alunni'][$alu]['credito4'];
        $dati['medie'][$alu] = number_format(0, 2);
        foreach ($dati['materie'] as $idmat=>$mat) {
          $dati['voti'][$alu][$idmat]['unico'] = '';
          $dati['voti'][$alu][$idmat]['assenze'] = '';
        }
      }
    } elseif ($periodo == 'I') {
      // legge i voti dello scrutinio finale
      $dati = $this->datiVoti($classe, 'F');
      // legge i voti dello scrutinio integrativo
      $somma = array();
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.alunno IN (:lista) AND vs.unico IS NOT NULL AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // legge voti
        $voto = $v->getUnico();
        if ($v->getMateria()->getTipo() == 'R') {
          // religione
          $voto = $codici[$periodo]['R'][$v->getUnico()];
        } elseif ($v->getMateria()->getTipo() == 'C') {
          // condotta
          $voto = ($v->getUnico() === null ? '' : $codici[$periodo]['C'][$v->getUnico()]);
        } elseif ($v->getMateria()->getTipo() == 'N') {
          // altre materie
          $voto = ($v->getUnico() === null ? '' : $codici[$periodo]['N'][$v->getUnico()]);
        }
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'unico' => $voto,
          'assenze' => $v->getAssenze());
        if ($v->getMateria()->getMedia()) {
          // esclude religione dalla media
          if (!isset($somma[$v->getAlunno()->getId()])) {
            $somma[$v->getAlunno()->getId()] = (($voto == 'N' || $voto == '') ? 0 : $voto);
            $numero[$v->getAlunno()->getId()] = 1;
          } else {
            $somma[$v->getAlunno()->getId()] += (($voto == 'N' || $voto == '') ? 0 : $voto);
            $numero[$v->getAlunno()->getId()]++;
          }
        }
      }
      // calcola medie
      foreach ($somma as $alu=>$s) {
        $dati['medie'][$alu] = number_format($numero[$alu] == 0 ? 0 : $somma[$alu] / $numero[$alu], 2);
      }
      // esiti e crediti
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
        ->join('e.scrutinio', 's')
        ->where('e.alunno IN (:lista) AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = array(
          'esito' => $codici_esito[$e->getEsito()],
          'credito' => $e->getCredito() === null ? 0 : $e->getCredito(),
          'creditoprec' => intval($e->getAlunno()->getCredito3()) + intval($e->getAlunno()->getCredito4()));
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Confronta i voti del registro con quelli esportati
   *
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   * @param array $dati Voti del registro come un array associativo
   * @param string $filepdf Nome del file PDF esportato
   *
   * @return string|null Messaggio di errore o NULL se tutto ok
   */
  private function confrontaVoti(Classe $classe, $periodo, $dati, $filepdf) {
    // legge voti dal file PDF
    $xml = $this->pdfVoti($filepdf);
    if (isset($xml['errore'])) {
      // errore di lettura del file
      return $xml['errore'];
    }
    if ($periodo == 'P') {  // primo trimestre
      // controlla classe
      if ($xml['info']['classe'] != $classe->getAnno().$classe->getSezione()) {
        // errore
        return 'La classe "'.$xml['info']['classe'].'" non corrisponde a quella prevista ('.
          $classe->getAnno().$classe->getSezione().')';
      }
      // controlla anno
      $config = $this->em->getRepository('App:Configurazione')->findOneByParametro('anno_scolastico');
      if ($xml['info']['anno'] != substr($config->getValore(), 5)) {
        // errore
        return 'L\'anno "'.$xml['info']['anno'].'" non corrisponde a quello previsto ('.
          substr($config->getValore(), 5).')';
      }
      // controlla periodo
      if ($xml['info']['periodo'] != 'PRIMO TRIMESTRE') {
        // errore
        return 'Il periodo "'.$xml['info']['periodo'].'" non corrisponde a quello previsto (PRIMO TRIMESTRE)';
      }
      // controlla materie
      if ($xml['info']['num_materie'] != count($dati['materie']) - 1) {
        // errore
        return 'Il numero di materie "'.$xml['info']['num_materie'].'" non corrisponde a quello previsto ('.
          (count($dati['materie']) - 1).')';
      }
      $idx = 1;
      foreach ($dati['materie'] as $idmat=>$mat) {
        if ($this->converteNomeMateria($mat['nome']) != $xml['info']['colonne'][$idx]) {
          // errore
          return 'La materia "'.$xml['info']['colonne'][$idx].'" non corrisponde a quella prevista ('.
            $this->converteNomeMateria($mat['nome']).')';
        }
        $dati['materie'][$idmat]['idx'] = $idx;
        // passa a successivo
        $idx++;
      }
      // controllo numero alunni
      if (count($dati['alunni']) != count($xml['alunni'])) {
        // errore
        return 'Il numero di alunni "'.count($xml['alunni']).'" non corrisponde a quello previsto ('.
          count($dati['alunni']).')';
      }
      // controllo dati alunni
      $idx = 1;
      foreach ($dati['alunni'] as $idalu=>$alu) {
        // nome alunno
        $alunno = str_replace(' ', '', $alu['cognome'].$alu['nome'].$alu['dataNascita']);
        if ($alunno != $xml['alunni'][$idx]['nome']) {
          // errore
          return 'L\'alunno "'.$xml['alunni'][$idx]['nome'].'" non corrisponde a quello previsto ('.$alunno.')';
        }
        // voti/assenze alunno
        foreach ($dati['materie'] as $idmat=>$mat) {
          $xml_voto = $xml['alunni'][$idx]['voti'][$mat['idx']];
          if ($mat['tipo'] == 'R') {
            // religione
            $xml_assenze = $xml['alunni'][$idx]['assenze'][$mat['idx']];
            if ($alu['religione'] == 'S') {
              // si avvale
              $voto = $dati['voti'][$idalu][$idmat];
              if ($xml_voto != $voto['unico']) {
                // errore
                return 'Voto errato per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_voto.' - '.
                  $voto['unico'].')';
              }
              if ($xml_assenze != $voto['assenze']) {
                // errore
                return 'Assenze errate per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_assenze.' - '.
                  $voto['assenze'].')';
              }
            } else {
              // non si avvale
              if ($xml_voto != null || ($xml_assenze != '0' && $xml_assenze != null)) {
                // errore
                return 'Voto/assenze di Religione per l\'alunno "'.$alunno.'" che non si avvale ('.
                  $xml_voto.' - '.$xml_assenze.')';
              }
            }
          } else {
            // altre materie
            $voto = $dati['voti'][$idalu][$idmat];
            if ($xml_voto != $voto['unico']) {
              // errore
              return 'Voto errato per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_voto.' - '.
                $voto['unico'].')';
            }
            if ($mat['tipo'] != 'C') {
              // controlla assenze
              $xml_assenze = $xml['alunni'][$idx]['assenze'][$mat['idx']];
              if ($xml_assenze != $voto['assenze']) {
                // errore
                return 'Assenze errate per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_assenze.' - '.
                  $voto['assenze'].')';
              }
            }
          }
        }
        // media
        $xml_media = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 2];
        if ($xml_media != $dati['medie'][$idalu]) {
          // errore
          return 'Media errata per l\'alunno "'.$alunno.'" ('.$xml_media.' - '.$dati['medie'][$idalu].')';
        }
        // passa a successivo
        $idx++;
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      if ($xml['info']['classe'] != $classe->getAnno().$classe->getSezione()) {
        // errore
        return 'La classe "'.$xml['info']['classe'].'" non corrisponde a quella prevista ('.
          $classe->getAnno().$classe->getSezione().')';
      }
      // controlla anno
      $config = $this->em->getRepository('App:Configurazione')->findOneByParametro('anno_scolastico');
      if ($xml['info']['anno'] != substr($config->getValore(), 5)) {
        // errore
        return 'L\'anno "'.$xml['info']['anno'].'" non corrisponde a quello previsto ('.
          substr($config->getValore(), 5).')';
      }
      // controlla periodo
      if ($xml['info']['periodo'] != 'SCRUTINIO FINALE') {
        // errore
        return 'Il periodo "'.$xml['info']['periodo'].'" non corrisponde a quello previsto (PRIMO TRIMESTRE)';
      }
      // controlla materie
      if ($xml['info']['num_materie'] != count($dati['materie']) - 1) {
        // errore
        return 'Il numero di materie "'.$xml['info']['num_materie'].'" non corrisponde a quello previsto ('.
          (count($dati['materie']) - 1).')';
      }
      $idx = 1;
      foreach ($dati['materie'] as $idmat=>$mat) {
        if ($this->converteNomeMateria($mat['nome']) != $xml['info']['colonne'][$idx]) {
          // errore
          return 'La materia "'.$xml['info']['colonne'][$idx].'" non corrisponde a quella prevista ('.
            $this->converteNomeMateria($mat['nome']).')';
        }
        $dati['materie'][$idmat]['idx'] = $idx;
        // passa a successivo
        $idx++;
      }
      // controllo numero alunni
      if (count($dati['alunni']) != count($xml['alunni'])) {
        // errore
        return 'Il numero di alunni "'.count($xml['alunni']).'" non corrisponde a quello previsto ('.
          count($dati['alunni']).')';
      }
      // controllo dati alunni
      $idx = 1;
      foreach ($dati['alunni'] as $idalu=>$alu) {
        // nome alunno
        $alunno = str_replace(' ', '', $alu['cognome'].$alu['nome'].$alu['dataNascita']);
        if ($alunno != $xml['alunni'][$idx]['nome']) {
          // errore
          return 'L\'alunno "'.$xml['alunni'][$idx]['nome'].'" non corrisponde a quello previsto ('.$alunno.')';
        }
        // voti/assenze alunno
        foreach ($dati['materie'] as $idmat=>$mat) {
          $xml_voto = $xml['alunni'][$idx]['voti'][$mat['idx']];
          if ($mat['tipo'] == 'R') {
            // religione
            $xml_assenze = $xml['alunni'][$idx]['assenze'][$mat['idx']];
            if ($alu['religione'] == 'S') {
              // si avvale
              $voto = $dati['voti'][$idalu][$idmat];
              if ($xml_voto != $voto['unico']) {
                // errore
                return 'Voto errato per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_voto.' - '.
                  $voto['unico'].')';
              }
              if ($xml_assenze != $voto['assenze']) {
                // errore
                return 'Assenze errate per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_assenze.' - '.
                  $voto['assenze'].')';
              }
            } else {
              // non si avvale
              if ($xml_voto != null || ($xml_assenze != '0' && $xml_assenze != null)) {
                // errore
                return 'Voto/assenze di Religione per l\'alunno "'.$alunno.'" che non si avvale ('.
                  $xml_voto.' - '.$xml_assenze.')';
              }
            }
          } else {
            // altre materie
            $voto = $dati['voti'][$idalu][$idmat];
            if ($xml_voto != $voto['unico']) {
              // errore
              return 'Voto errato per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_voto.' - '.
                $voto['unico'].')';
            }
            if ($mat['tipo'] != 'C') {
              // controlla assenze
              $xml_assenze = $xml['alunni'][$idx]['assenze'][$mat['idx']];
              if ($xml_assenze != $voto['assenze']) {
                // errore
                return 'Assenze errate per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_assenze.' - '.
                  $voto['assenze'].')';
              }
            }
          }
        }
        // credito
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 2];
        if ($xml_dato != $dati['esiti'][$idalu]['credito']) {
          // errore
          return 'Credito errato per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.$dati['esiti'][$idalu]['credito'].')';
        }
        // integrativo
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 3];
        if ($xml_dato != 0) {
          // errore
          return 'Credito integrativo errato per l\'alunno "'.$alunno.'" ('.$xml_dato.')';
        }
        // credito precedente
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 4];
        if ($xml_dato != $dati['esiti'][$idalu]['creditoprec']) {
          // errore
          return 'Credito precedente errato per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.$dati['esiti'][$idalu]['creditoprec'].')';
        }
        // credito totale
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 5];
        if ($xml_dato != $dati['esiti'][$idalu]['credito'] + $dati['esiti'][$idalu]['creditoprec']) {
          // errore
          return 'Credito totale errato per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.($dati['esiti'][$idalu]['credito'] + $dati['esiti'][$idalu]['creditoprec']).')';
        }
        // media
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 6];
        if ($xml_dato != $dati['medie'][$idalu]) {
          // errore
          return 'Media errata per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.$dati['medie'][$idalu].')';
        }
        // esito
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 7];
        if (strncmp($xml_dato, $dati['esiti'][$idalu]['esito'], strlen($dati['esiti'][$idalu]['esito']))) {
          // errore
          return 'Esito errato per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.$dati['esiti'][$idalu]['esito'].')';
        }
        // passa a successivo
        $idx++;
      }
    } elseif ($periodo == 'I') {
      // scrutinio integrativo
      if ($xml['info']['classe'] != $classe->getAnno().$classe->getSezione()) {
        // errore
        return 'La classe "'.$xml['info']['classe'].'" non corrisponde a quella prevista ('.
          $classe->getAnno().$classe->getSezione().')';
      }
      // controlla anno
      $config = $this->em->getRepository('App:Configurazione')->findOneByParametro('anno_scolastico');
      if ($xml['info']['anno'] != substr($config->getValore(), 5)) {
        // errore
        return 'L\'anno "'.$xml['info']['anno'].'" non corrisponde a quello previsto ('.
          substr($config->getValore(), 5).')';
      }
      // controlla periodo
      if ($xml['info']['periodo'] != 'SCRUTINIO FINALE') {
        // errore
        return 'Il periodo "'.$xml['info']['periodo'].'" non corrisponde a quello previsto (PRIMO TRIMESTRE)';
      }
      // controlla materie
      if ($xml['info']['num_materie'] != count($dati['materie']) - 1) {
        // errore
        return 'Il numero di materie "'.$xml['info']['num_materie'].'" non corrisponde a quello previsto ('.
          (count($dati['materie']) - 1).')';
      }
      $idx = 1;
      foreach ($dati['materie'] as $idmat=>$mat) {
        if ($this->converteNomeMateria($mat['nome']) != $xml['info']['colonne'][$idx]) {
          // errore
          return 'La materia "'.$xml['info']['colonne'][$idx].'" non corrisponde a quella prevista ('.
            $this->converteNomeMateria($mat['nome']).')';
        }
        $dati['materie'][$idmat]['idx'] = $idx;
        // passa a successivo
        $idx++;
      }
      // controllo numero alunni
      if (count($dati['alunni']) != count($xml['alunni'])) {
        // errore
        return 'Il numero di alunni "'.count($xml['alunni']).'" non corrisponde a quello previsto ('.
          count($dati['alunni']).')';
      }
      // controllo dati alunni
      $idx = 1;
      foreach ($dati['alunni'] as $idalu=>$alu) {
        // nome alunno
        $alunno = str_replace(' ', '', $alu['cognome'].$alu['nome'].$alu['dataNascita']);
        if ($alunno != $xml['alunni'][$idx]['nome']) {
          // errore
          return 'L\'alunno "'.$xml['alunni'][$idx]['nome'].'" non corrisponde a quello previsto ('.$alunno.')';
        }
        // voti/assenze alunno
        foreach ($dati['materie'] as $idmat=>$mat) {
          $xml_voto = $xml['alunni'][$idx]['voti'][$mat['idx']];
          if ($mat['tipo'] == 'R') {
            // religione
            $xml_assenze = $xml['alunni'][$idx]['assenze'][$mat['idx']];
            if ($alu['religione'] == 'S') {
              // si avvale
              $voto = $dati['voti'][$idalu][$idmat];
              if ($xml_voto != $voto['unico']) {
                // errore
                return 'Voto errato per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_voto.' - '.
                  $voto['unico'].')';
              }
              if ($xml_assenze != $voto['assenze']) {
                // errore
                return 'Assenze errate per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_assenze.' - '.
                  $voto['assenze'].')';
              }
            } else {
              // non si avvale
              if ($xml_voto != null || ($xml_assenze != '0' && $xml_assenze != null)) {
                // errore
                return 'Voto/assenze di Religione per l\'alunno "'.$alunno.'" che non si avvale ('.
                  $xml_voto.' - '.$xml_assenze.')';
              }
            }
          } else {
            // altre materie
            $voto = $dati['voti'][$idalu][$idmat];
            if ($xml_voto != $voto['unico']) {
              // errore
              return 'Voto errato per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_voto.' - '.
                $voto['unico'].')';
            }
            if ($mat['tipo'] != 'C') {
              // controlla assenze
              $xml_assenze = $xml['alunni'][$idx]['assenze'][$mat['idx']];
              if ($xml_assenze != $voto['assenze']) {
                // errore
                return 'Assenze errate per l\'alunno "'.$alunno.'" e materia "'.$mat['nome'].'" ('.$xml_assenze.' - '.
                  $voto['assenze'].')';
              }
            }
          }
        }
        // credito
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 2];
        if ($xml_dato != $dati['esiti'][$idalu]['credito']) {
          // errore
          return 'Credito errato per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.$dati['esiti'][$idalu]['credito'].')';
        }
        // integrativo
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 3];
        if ($xml_dato != 0) {
          // errore
          return 'Credito integrativo errato per l\'alunno "'.$alunno.'" ('.$xml_dato.')';
        }
        // credito precedente
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 4];
        if ($xml_dato != $dati['esiti'][$idalu]['creditoprec']) {
          // errore
          return 'Credito precedente errato per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.$dati['esiti'][$idalu]['creditoprec'].')';
        }
        // credito totale
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 5];
        if ($xml_dato != $dati['esiti'][$idalu]['credito'] + $dati['esiti'][$idalu]['creditoprec']) {
          // errore
          return 'Credito totale errato per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.($dati['esiti'][$idalu]['credito'] + $dati['esiti'][$idalu]['creditoprec']).')';
        }
        // media
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 6];
        if ($xml_dato != $dati['medie'][$idalu]) {
          // errore
          return 'Media errata per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.$dati['medie'][$idalu].')';
        }
        // esito
        $xml_dato = $xml['alunni'][$idx]['voti'][$xml['info']['num_materie'] + 7];
        if (strncmp($xml_dato, $dati['esiti'][$idalu]['esito'], strlen($dati['esiti'][$idalu]['esito']))) {
          // errore
          return 'Esito errato per l\'alunno "'.$alunno.'" ('.$xml_dato.' - '.$dati['esiti'][$idalu]['esito'].')';
        }
        // passa a successivo
        $idx++;
      }
    }
    // validazione ok
    return null;
  }

  /**
   * Converte il nome della materia dal formato ARGO a quello del registro
   *
   * @param string $nome Nome della materia da convertire nel formato ARGO
   *
   * @return string Nome della materia convertito
   */
  private function converteNomeMateria($nome) {
    $materie = array(
      'BIOLOGIA, MICROBIOLOGIA E TECNOLOGIE DI CONTROLLO AMBIENTALE' => 'BIOL.MIC.TEC.AMB.',
      'CHIMICA ANALITICA E STRUMENTALE' => 'CHIM. ANAL. STRUM.',
      'CHIMICA ORGANICA E BIOCHIMICA' => 'CHIM.ORG. BIOCHIM.',
      'MATEMATICA E COMPLEMENTI DI MATEMATICA' => 'MATEMATICA E  COMPL.',
      'CONDOTTA' => 'CONDOTTA',
      'DIRITTO ED ECONOMIA' => 'DIRITTO ED ECON.',
      'DISEGNO E STORIA DELL\'ARTE' => 'DISEGNO',
      'FILOSOFIA' => 'FILOSOFIA',
      'FISICA' => 'FISICA',
      'FISICA AMBIENTALE' => 'FISICA AMB.',
      'GEOGRAFIA GENERALE ED ECONOMICA' => 'GEOG.GEN.ECON.',
      'GESTIONE PROGETTO, ORGANIZZAZIONE D\'IMPRESA' => 'GEST.PROG.ORG.IMP.',
      'INFORMATICA' => 'INFORMATICA',
      'LINGUA E CULTURA STRANIERA (INGLESE)' => 'INGLESE',
      'LINGUA E LETTERATURA ITALIANA' => 'LETT. ITALIANA',
      'LINGUA STRANIERA (INGLESE)' => 'INGLESE',
      'MATEMATICA' => 'MATEMATICA',
      "RELIGIONE CATTOLICA O ATTIVITA' ALTERNATIVE" => 'RELIGIONE / ATT. ALT',
      'SCIENZE E TECNOLOGIE APPLICATE (INFORMATICA)' => 'SC.TECNOLOG.APPL.',
      'SCIENZE E TECNOLOGIE APPLICATE (CHIMICA)' => 'SC.TECNOLOG.APPL.',
      'SCIENZE INTEGRATE (CHIMICA)' => 'CHIMICA',
      'SCIENZE INTEGRATE (FISICA)' => 'FISICA',
      'SCIENZE INTEGRATE (SCIENZE DELLA TERRA E BIOLOGIA)' => 'SC. TERRA E BIOL.',
      'SCIENZE MOTORIE E SPORTIVE' => 'SC. MOTORIE',
      'SCIENZE NATURALI (BIOLOGIA, CHIMICA, SCIENZE DELLA TERRA)' => 'SC. NATURALI',
      'SISTEMI E RETI' => 'SIST. RETI',
      'STORIA' => 'STORIA',
      'STORIA E GEOGRAFIA' => 'STORIA-GEOGR.',
      'TECNOLOGIE CHIMICHE INDUSTRIALI' => 'TECNOL. CHIM. IND.',
      'TECNOLOGIE E PROGETTAZIONE DI SISTEMI INFORMATICI E DI TELECOMUNICAZIONI' => 'TEC.PROG.SIST.INF.TE',
      'TECNOLOGIE E TECNICHE DI RAPPRESENTAZIONE GRAFICA' => 'TECN.RAPP.GRAFICA',
      'TECNOLOGIE INFORMATICHE' => 'TEC.INFORMATICHE',
      'TELECOMUNICAZIONI' => 'TELECOMUN.'
      );
    if (array_key_exists($nome, $materie)) {
      // ok
      return $materie[$nome];
    } else {
      // errore
      throw new \Exception('Materia non presente "'.$nome.'"');
    }
  }

  /**
   * Restituisce i dati del file PDF
   *
   * @param string $filepdf Nome del file PDF esportato
   *
   * @return array Dati formattati come un array associativo
   */
  private function pdfVoti($filepdf) {
    $dati = array();
    $fs = new Filesystem();
    // converte file PDF in XML
    $filexml = substr($filepdf, 0, -3).'xml';
    if (!$fs->exists($filexml)) {
      $wdir = (new File($filepdf))->getPath();
      $proc = new Process('pdftohtml -xml "'.$filepdf.'"', $wdir);
      $proc->run();
      if (!$proc->isSuccessful() || !$fs->exists($filexml)) {
        // errore
        return 'Impossibile convertire il file "'.$filepdf.'" in XML';
      }
    }
    // legge XML
    $crawler = new Crawler(file_get_contents($filexml));
    // intestazione
    $txt = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text[3]')->text();
    $dati['info']['classe'] = substr($txt, 0, 2);
    $txt = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text[7]')->text();
    $txt2 = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text[8]')->text();
    $dati['info']['anno'] = $txt.$txt2;
    $txt = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text[9]')->text();
    $dati['info']['periodo'] = $txt;
    $doppia_intestazione = null;
    for ($idx = 10; ($txt = $crawler->filterXPath('//pdf2xml/page[@number="1"]/text['.$idx.']')->text()) != '1'; $idx++) {
      $dati['info']['colonne'][$idx - 10] = $txt;
      if ($txt == 'CONDOTTA') {
        // numero di materie con assenza
        $dati['info']['num_materie'] = $idx - 11;
      } elseif (isset($dati['info']['num_materie']) && substr($txt, 0, 5) == 'Media' && strlen($txt) > 5) {
        // doppia intestazione
        $doppia_intestazione = $idx-10;
      }
    }
    if ($doppia_intestazione !== null) {
      for ($i = count($dati['info']['colonne']) - 1; $i >= $doppia_intestazione; $i--) {
        $dati['info']['colonne'][$i + 1] = $dati['info']['colonne'][$i];
      }
      $valori = explode(' ', $dati['info']['colonne'][$doppia_intestazione]);
      $dati['info']['colonne'][$doppia_intestazione] = $valori[0];
      $dati['info']['colonne'][$doppia_intestazione + 1] = $valori[1];
    }
    // legge righe
    while (intval($txt) > 0 || ($txt != 'Prof' && $txt != 'Data')) {
      $nodo = $crawler->filterXPath('//text['.$idx.']');
      if ($nodo->attr('top') != '72' && $nodo->attr('top') != '1205') {
        // esclude intestazione
        $alu_num = intval($txt);
        if ($alu_num == 0) {
          // errore lettura alunno
          $dati['errore'] = 'Errore di lettura dati [alunno-1]';
          return $dati;
        }
        // dati alunno
        $alu_nome = '';
        for ($idx++; ($txt = $crawler->filterXPath('//text['.$idx.']')->text()) != 'Assenze->'; $idx++) {
          $alu_nome .= $txt;
        }
        $alu_nome = str_replace(' ', '', $alu_nome);
        $dati['alunni'][$alu_num]['nome'] = $alu_nome;
        // voti
        $idx++;
        $nodo = $crawler->filterXPath('//text['.$idx.']');
        $riga = $nodo->attr('top');
        $nodo2 = $crawler->filterXPath('//text['.($idx+1).']');
        $riga2 = $nodo2->attr('top');
        $voti = array();
        while ($nodo->attr('top') == $riga || $nodo->attr('top') == $riga2) {
          $voti[] = $nodo->text();
          $idx++;
          $nodo = $crawler->filterXPath('//text['.$idx.']');
        }
        // assenze
        $riga = $nodo->attr('top');
        $nodo2 = $crawler->filterXPath('//text['.($idx+1).']');
        $riga2 = $nodo2->attr('top');
        $assenze = array();
        while ($nodo->attr('top') == $riga || $nodo->attr('top') == $riga2) {
          $assenze[] = $nodo->text();
          $idx++;
          $nodo = $crawler->filterXPath('//text['.$idx.']');
        }
        // controllo doppio voto
        if (isset($voti[0]) && strpos($voti[0], ' ') !== false) {
          // doppio voto
          for ($i = count($voti) - 1; $i >= 0; $i--) {
            $voti[$i + 1] = $voti[$i];
          }
          $valori = explode(' ', $voti[0]);
          $voti[0] = $valori[0];
          $voti[1] = $valori[1];
        }
        // salta voti nulli
        if (count($voti) == count($dati['info']['colonne']) - 2) {
          // voto religione nullo
          for ($i = count($voti) - 1; $i >= 0; $i--) {
            $voti[$i + 1] = $voti[$i];
          }
          $voti[0] = null;
        } elseif (count($voti) == count($dati['info']['colonne']) - $dati['info']['num_materie'] - 2) {
          // tutti i voti nulli
          for ($i = count($dati['info']['colonne']) - $dati['info']['num_materie'] - 3; $i >= 0; $i--) {
            $voti[$i + $dati['info']['num_materie'] + 1] = $voti[$i];
          }
          for ($i = 0; $i < $dati['info']['num_materie']; $i++) {
            $voti[$i] = null;
          }
        } elseif ($voti[0] == '///' && count($voti) == count($dati['info']['colonne']) - $dati['info']['num_materie'] - 1) {
          // NA e altri voti nulli
          for ($i = count($dati['info']['colonne']) - $dati['info']['num_materie'] - 3; $i >= 0; $i--) {
            $voti[$i + $dati['info']['num_materie'] + 1] = $voti[$i + 1];
          }
          for ($i = 0; $i < $dati['info']['num_materie']; $i++) {
            $voti[$i] = null;
          }
        }
        if ($voti[0] == '///') {
          // voto religione nullo
          $voti[0] = null;
        }
        // assegna voti
        for ($n = 1; $n < count($dati['info']['colonne']); $n++) {
          $dati['alunni'][$alu_num]['voti'][$n] = isset($voti[$n-1]) ? $voti[$n-1] : null;
        }
        // assenze religione
        if ($voti[0] === null) {
          if (count($assenze) < $dati['info']['num_materie']) {
            // nessun voto e nessuna assenza di religione
            for ($i = count($assenze) - 1; $i >= 0; $i--) {
              $assenze[$i + 1] = $assenze[$i];
            }
            $assenze[0] = null;
          } elseif (isset($assenze[0]) && $assenze[0] == '///') {
            $assenze[0] = null;
          }
        }
        // assegna assenze
        for ($n = 1; $n <= $dati['info']['num_materie']; $n++) {
          $dati['alunni'][$alu_num]['assenze'][$n] = isset($assenze[$n-1]) ? $assenze[$n-1] : null;
        }
        // fine riga, aggiusta indice
        $idx--;
      }
      // prossima riga
      $idx++;
      $txt = $crawler->filterXPath('//text['.$idx.']')->text();
    }
    // restituisce dati
    return $dati;
  }

}
