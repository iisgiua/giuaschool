<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Festivita;
use App\Entity\Materia;
use App\Util\RegistroUtil;
use App\Util\StaffUtil;


/**
 * LezioniController - gestione delle lezioni
 */
class LezioniController extends AbstractController {

  /**
   * Gestione delle lezioni
   *
   * @param SessionInterface $session Gestore delle sessioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/", name="lezioni",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function lezioniAction(SessionInterface $session) {
    if (!$session->get('/APP/DOCENTE/cattedra_lezione') && !$session->get('/APP/DOCENTE/classe_lezione')) {
      // scelta classe
      return $this->redirectToRoute('lezioni_classe');
    }
    if ($session->get('/APP/DOCENTE/menu_lezione')) {
      // vai all'ultima pagina visitata
      return $this->redirectToRoute($session->get('/APP/DOCENTE/menu_lezione')['name'], $session->get('/APP/DOCENTE/menu_lezione')['param']);
    } else {
      // vai al registro
      return $this->redirectToRoute('lezioni_registro_firme');
    }
  }

  /**
   * Gestione della scelta delle classi
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/classe/", name="lezioni_classe",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function classeAction(Request $request, EntityManagerInterface $em) {
    // lista cattedre
    $lista = $em->getRepository('App\Entity\Cattedra')->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('c.materia', 'm')
      ->where('c.docente=:docente AND c.attiva=:attiva')
      ->orderBy('cl.sede,cl.anno,cl.sezione,m.nomeBreve', 'ASC')
      ->setParameters(['docente' => $this->getUser(), 'attiva' => 1])
      ->getQuery()
      ->getResult();
    // raggruppa per classi
    $cattedre = array();
    foreach ($lista as $c) {
      $cattedre[$c->getClasse()->getId()][] = $c;
    }
    // lista tutte le classi
    $lista = $em->getRepository('App\Entity\Classe')->createQueryBuilder('cl')
      ->orderBy('cl.sede,cl.sezione,cl.anno', 'ASC')
      ->getQuery()
      ->getResult();
    // raggruppa per sezione
    $classi = array();
    foreach ($lista as $c) {
      $classi[$c->getSezione()][] = $c;
    }
    // visualizza pagina
    return $this->render('lezioni/classe.html.twig', array(
      'pagina_titolo' => 'page.lezioni_classe',
      'cattedre' => $cattedre,
      'classi' => $classi,
    ));
  }

  /**
   * Mostra gli argomenti e le attività delle lezioni svolte.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/argomenti/{cattedra}/{classe}", name="lezioni_argomenti",
   *    requirements={"cattedra": "\d+", "classe": "\d+"},
   *    defaults={"cattedra": 0, "classe": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function argomentiAction(Request $request, EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                                   $cattedra, $classe) {
    // inizializza variabili
    $info = null;
    $dati = null;
    $template = 'lezioni/argomenti.html.twig';
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $session->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $session->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $template = 'lezioni/argomenti'.(($cattedra->getTipo() == 'S' || $cattedra->getMateria()->getTipo() == 'S') ? '_sostegno' : '').'.html.twig';
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $em->getRepository('App\Entity\Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
      // informazioni necessarie
      $cattedra = null;
      $info['materia'] = $materia->getNomeBreve();
      $info['alunno'] = null;
    }
    if ($cattedra) {
      // recupera dati
      $dati = $reg->argomenti($cattedra);
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render($template, array(
      'pagina_titolo' => 'page.lezioni_argomenti',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra il riepilogo mensile delle lezioni svolte.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/argomenti/riepilogo/{cattedra}/{data}", name="lezioni_argomenti_riepilogo",
   *    requirements={"cattedra": "\d+", "data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"data": "0000-00-00"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function argomentiRiepilogoAction(EntityManagerInterface $em, SessionInterface $session,
                                            RegistroUtil $reg, $cattedra, $data) {
    // inizializza variabili
    $dati = null;
    $info = null;
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $template = 'lezioni/argomenti_riepilogo.html.twig';
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($session->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata (non la memorizza)
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // lezione in propria cattedra: controlla esistenza
    $cattedra = $em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni necessarie
    $classe = $cattedra->getClasse();
    $template = 'lezioni/argomenti_riepilogo'.(($cattedra->getTipo() == 'S' || $cattedra->getMateria()->getTipo() == 'S') ? '_sostegno' : '').'.html.twig';
    $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
    $info['alunno'] = $cattedra->getAlunno();
    // data prec/succ
    $data_inizio = \DateTime::createFromFormat('Y-m-d', $data_obj->format('Y-m-01'));
    $data_fine = clone $data_inizio;
    $data_fine->modify('last day of this month');
    $data_succ = $em->getRepository('App\Entity\Festivita')->giornoSuccessivo($data_fine);
    $data_prec = $em->getRepository('App\Entity\Festivita')->giornoPrecedente($data_inizio);
    // recupera dati
    $dati = $reg->riepilogo($data_obj, $cattedra);
    // visualizza pagina
    return $this->render($template, array(
      'pagina_titolo' => 'page.lezioni_riepilogo',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'mesi' => $mesi,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra le note disciplinari della classe.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/note/{cattedra}/{classe}", name="lezioni_note",
   *    requirements={"cattedra": "\d+", "classe": "\d+"},
   *    defaults={"cattedra": 0, "classe": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function noteAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                              StaffUtil $staff, $cattedra, $classe) {
    // inizializza variabili
    $dati = null;
    $info = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $session->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $session->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $em->getRepository('App\Entity\Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
      // informazioni necessarie
      $cattedra = null;
      $info['materia'] = $materia->getNomeBreve();
      $info['alunno'] = null;
    }
    if ($classe) {
      // recupera dati
      $dati = $staff->note($classe);
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/note.html.twig', array(
      'pagina_titolo' => 'page.lezioni_note',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Crea automaticamente il programma svolto.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/argomenti/programma/{cattedra}", name="lezioni_argomenti_programma",
   *    requirements={"cattedra": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function argomentiProgrammaAction(EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                                            $cattedra) {
    // inizializza
    $info = null;
    $dati = null;
    $dir = $this->getParameter('dir_tmp').'/';
    $nomefile = md5(uniqid()).'-'.rand(1,1000).'.docx';
    // controlla cattedra
    $cattedra = $em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra || $cattedra->getMateria()->getTipo() == 'S') {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera dati
    $dati = $reg->programma($cattedra);
    // info dati
    $info['classe'] = $cattedra->getClasse()->getAnno().'ª '.$cattedra->getClasse()->getSezione();
    $info['classe_corso'] = $info['classe'].' - '.$cattedra->getClasse()->getCorso().' - '.
      $cattedra->getClasse()->getSede()->getCitta();
    $info['materia'] = $cattedra->getMateria()->getNome();
    $info['docenti'] = (count($dati['docenti']) == 1) ? 'Docente: ' : 'Docenti: ';
    foreach ($dati['docenti'] as $doc) {
      $info['docenti'] .= $doc['nome'].' '. $doc['cognome'].', ';
    }
    $info['docenti'] = substr($info['docenti'], 0, -2);
    $m = strtoupper(preg_replace('/\W+/','-', $cattedra->getMateria()->getNomeBreve()));
    if (substr($m, -1) == '-') {
      $m = substr($m, 0, -1);
    }
    $info['documento'] = 'PROGRAMMA-'.$cattedra->getClasse()->getAnno().$cattedra->getClasse()->getSezione().
      '-'.$m.'.docx';
    // configurazione documento
    \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $properties = $phpWord->getDocInfo();
    $properties->setCreator($session->get('/CONFIG/ISTITUTO/intestazione'));
    $properties->setTitle('Programma svolto - '.$info['classe'].' - '.$info['materia']);
    $properties->setDescription('');
    $properties->setSubject('');
    $properties->setKeywords('');
    // stili predefiniti
    $phpWord->setDefaultFontName('Times New Roman');
    $phpWord->setDefaultFontSize(12);
    $phpWord->setDefaultParagraphStyle(array(
      'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
      'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.2)));
    $lista_paragrafo = array('spaceAfter' => 0);
    $lista_stile = 'multilevel';
    $phpWord->addNumberingStyle($lista_stile, array(
      'type' => 'multilevel',
      'levels' => array(
        array('format' => 'decimal', 'text' => '%1)', 'left' => 720, 'hanging' => 360, 'tabPos' => 720))));
    // imposta pagina
    $section = $phpWord->addSection(array(
      'orientation' => 'portrait',
      'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
      'headerHeight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0),
      'footerHeight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
      'pageSizeH' =>  \PhpOffice\PhpWord\Shared\Converter::cmToTwip(29.70),
      'pageSizeW' =>  \PhpOffice\PhpWord\Shared\Converter::cmToTwip(21)
      ));
    $footer = $section->addFooter();
    $footer->addPreserveText('- Pag. {PAGE}/{NUMPAGES} -',
      array('name' => 'Arial', 'size' => 9),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    // intestazione
    $section->addImage($this->getParameter('kernel.project_dir').'/public/img/logo-italia.png', array(
      'width' => 35,
      'height' => 35,
      'positioning' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE,
      'posHorizontal' => \PhpOffice\PhpWord\Style\Image::POSITION_HORIZONTAL_CENTER,
      'posHorizontalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_COLUMN,
      'posVertical' => \PhpOffice\PhpWord\Style\Image::POSITION_VERTICAL_TOP,
      'posVerticalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_LINE
      ));
    $section->addTextBreak(1);
    $section->addText('ISTITUTO DI ISTRUZIONE SUPERIORE',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText($session->get('/CONFIG/ISTITUTO/nome'),
      array('bold' => true, 'italic' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText($session->get('/CONFIG/ISTITUTO/sede_0_citta'),
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addTextBreak(1);
    $as = $session->get('/CONFIG/SCUOLA/anno_scolastico');
    $section->addText('ANNO SCOLASTICO '.$as,
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addTextBreak(1);
    $section->addText('PROGRAMMA SVOLTO',
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText('Classe: '.$info['classe_corso'],
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText('Materia: '.$info['materia'],
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addText($info['docenti'],
      array('bold' => true),
      array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0));
    $section->addTextBreak(2);
    // programma
    foreach ($dati['argomenti'] as $arg) {
      $section->addListItem($arg, 0, null, null, $lista_paragrafo);
    }
    // salva documento
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($dir.$nomefile);
    // invia il documento
    return $this->file($dir.$nomefile, $info['documento']);
  }

}
