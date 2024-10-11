<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Materia;
use DateTime;
use IntlDateFormatter;
use App\Entity\Festivita;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Style\Image;
use PhpOffice\PhpWord\IOFactory;
use App\Util\RegistroUtil;
use App\Util\StaffUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * LezioniController - gestione delle lezioni
 *
 * @author Antonello Dessì
 */
class LezioniController extends BaseController {

  /**
   * Gestione delle lezioni
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/', name: 'lezioni', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function lezioni(): Response {
    if (!$this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione') && !$this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione')) {
      // scelta classe
      return $this->redirectToRoute('lezioni_classe');
    }
    if ($this->reqstack->getSession()->get('/APP/DOCENTE/menu_lezione')) {
      // vai all'ultima pagina visitata
      return $this->redirectToRoute($this->reqstack->getSession()->get('/APP/DOCENTE/menu_lezione')['name'], $this->reqstack->getSession()->get('/APP/DOCENTE/menu_lezione')['param']);
    } else {
      // vai al registro
      return $this->redirectToRoute('lezioni_registro_firme');
    }
  }

  /**
   * Gestione della scelta delle classi
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/classe/', name: 'lezioni_classe', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function classe(): Response
  {
      // lista cattedre
      $lista = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
        ->join('c.classe', 'cl')
        ->join('c.materia', 'm')
        ->where('c.docente=:docente AND c.attiva=:attiva')
        ->orderBy('cl.sede,cl.anno,cl.sezione,cl.gruppo,m.nomeBreve', 'ASC')
        ->setParameters(['docente' => $this->getUser(), 'attiva' => 1])
        ->getQuery()
        ->getResult();
      // raggruppa per classi
      $cattedre = [];
      foreach ($lista as $c) {
        $cattedre[$c->getClasse()->getId()][] = $c;
      }
      // lista tutte le classi
      $lista = $this->em->getRepository(Classe::class)->createQueryBuilder('cl')
        ->orderBy('cl.sede,cl.sezione,cl.anno,cl.gruppo', 'ASC')
        ->getQuery()
        ->getResult();
      // raggruppa per sezione
      $classi = [];
      foreach ($lista as $c) {
        $classi[$c->getSezione()][] = $c;
      }
      // visualizza pagina
      return $this->render('lezioni/classe.html.twig', [
        'pagina_titolo' => 'page.lezioni_classe',
        'cattedre' => $cattedre,
        'classi' => $classi]);
  }

  /**
   * Mostra gli argomenti e le attività delle lezioni svolte.
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/argomenti/{cattedra}/{classe}', name: 'lezioni_argomenti', requirements: ['cattedra' => '\d+', 'classe' => '\d+'], defaults: ['cattedra' => 0, 'classe' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function argomenti(Request $request, RegistroUtil $reg, int $cattedra,
                            int $classe): Response {
    // inizializza variabili
    $info = null;
    $dati = null;
    $template = 'lezioni/argomenti.html.twig';
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
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
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $this->em->getRepository(Materia::class)->findOneByTipo('U');
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
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render($template, [
      'pagina_titolo' => 'page.lezioni_argomenti',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Mostra il riepilogo mensile delle lezioni svolte.
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/argomenti/riepilogo/{cattedra}/{data}', name: 'lezioni_argomenti_riepilogo', requirements: ['cattedra' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d'], defaults: ['data' => '0000-00-00'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function argomentiRiepilogo(RegistroUtil $reg, int $cattedra, string $data): Response {
    // inizializza variabili
    $dati = null;
    $info = null;
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $template = 'lezioni/argomenti_riepilogo.html.twig';
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata (non la memorizza)
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    }
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // lezione in propria cattedra: controlla esistenza
    $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
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
    $data_inizio = DateTime::createFromFormat('Y-m-d', $data_obj->format('Y-m-01'));
    $data_fine = clone $data_inizio;
    $data_fine->modify('last day of this month');
    $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_fine);
    $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_inizio);
    // recupera dati
    $dati = $reg->riepilogo($data_obj, $cattedra);
    // visualizza pagina
    return $this->render($template, [
      'pagina_titolo' => 'page.lezioni_riepilogo',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'mesi' => $mesi,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Mostra le note disciplinari della classe.
   *
   * @param Request $request Pagina richiesta
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/note/{cattedra}/{classe}', name: 'lezioni_note', requirements: ['cattedra' => '\d+', 'classe' => '\d+'], defaults: ['cattedra' => 0, 'classe' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function note(Request $request, StaffUtil $staff, int $cattedra, int $classe): Response {
    // inizializza variabili
    $dati = null;
    $info = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
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
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $this->em->getRepository(Materia::class)->findOneByTipo('U');
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
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/note.html.twig', [
      'pagina_titolo' => 'page.lezioni_note',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Crea automaticamente il programma svolto.
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/argomenti/programma/{cattedra}', name: 'lezioni_argomenti_programma', requirements: ['cattedra' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function argomentiProgramma(RegistroUtil $reg, int $cattedra): Response {
    // inizializza
    $info = null;
    $dati = null;
    $dir = $this->getParameter('dir_tmp').'/';
    $nomefile = md5(uniqid()).'-'.random_int(1, 1000).'.docx';
    // controlla cattedra
    $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra || $cattedra->getMateria()->getTipo() == 'S') {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera dati
    $dati = $reg->programma($cattedra);
    // info dati
    $info['classe'] = ''.$cattedra->getClasse();
    $info['classe_corso'] = $info['classe'].' - '.$cattedra->getClasse()->getCorso().' - '.
      $cattedra->getClasse()->getSede()->getNomeBreve();
    $info['materia'] = $cattedra->getMateria()->getNome();
    $info['docenti'] = (count($dati['docenti']) == 1) ? 'Docente: ' : 'Docenti: ';
    foreach ($dati['docenti'] as $doc) {
      $info['docenti'] .= $doc['nome'].' '. $doc['cognome'].', ';
    }
    $info['docenti'] = substr($info['docenti'], 0, -2);
    $m = strtoupper((string) preg_replace('/\W+/','-', (string) $cattedra->getMateria()->getNomeBreve()));
    if (str_ends_with($m, '-')) {
      $m = substr($m, 0, -1);
    }
    $info['documento'] = 'PROGRAMMA-'.$cattedra->getClasse()->getAnno().$cattedra->getClasse()->getSezione().
      $cattedra->getClasse()->getGruppo().'-'.$m.'.docx';
    // configurazione documento
    Settings::setOutputEscapingEnabled(true);
    $phpWord = new PhpWord();
    $properties = $phpWord->getDocInfo();
    $properties->setCreator($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'));
    $properties->setTitle('Programma svolto - '.$info['classe'].' - '.$info['materia']);
    $properties->setDescription('');
    $properties->setSubject('');
    $properties->setKeywords('');
    // stili predefiniti
    $phpWord->setDefaultFontName('Times New Roman');
    $phpWord->setDefaultFontSize(12);
    $phpWord->setDefaultParagraphStyle([
      'alignment' => Jc::BOTH,
      'spaceAfter' => Converter::cmToTwip(0.2)]);
    $lista_paragrafo = ['spaceAfter' => 0];
    $lista_stile = 'multilevel';
    $phpWord->addNumberingStyle($lista_stile, [
      'type' => 'multilevel',
      'levels' => [
        ['format' => 'decimal', 'text' => '%1)', 'left' => 720, 'hanging' => 360, 'tabPos' => 720]]]);
    // imposta pagina
    $section = $phpWord->addSection([
      'orientation' => 'portrait',
      'marginTop' => Converter::cmToTwip(2),
      'marginBottom' => Converter::cmToTwip(2),
      'marginLeft' => Converter::cmToTwip(2),
      'marginRight' => Converter::cmToTwip(2),
      'headerHeight' => Converter::cmToTwip(0),
      'footerHeight' => Converter::cmToTwip(1.5),
      'pageSizeH' =>  Converter::cmToTwip(29.70),
      'pageSizeW' =>  Converter::cmToTwip(21)]);
    $footer = $section->addFooter();
    $footer->addPreserveText('- Pag. {PAGE}/{NUMPAGES} -',
      ['name' => 'Arial', 'size' => 9],
      ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    // intestazione
    $section->addImage($this->getParameter('kernel.project_dir').'/public/img/logo-italia.png', [
      'width' => 35,
      'height' => 35,
      'positioning' => Image::POSITION_RELATIVE,
      'posHorizontal' => Image::POSITION_HORIZONTAL_CENTER,
      'posHorizontalRel' => Image::POSITION_RELATIVE_TO_COLUMN,
      'posVertical' => Image::POSITION_VERTICAL_TOP,
      'posVerticalRel' => Image::POSITION_RELATIVE_TO_LINE]);
    $section->addTextBreak(1);
    $section->addText('ISTITUTO DI ISTRUZIONE SUPERIORE',
      ['bold' => true],
      ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addText($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/nome'),
      ['bold' => true, 'italic' => true],
      ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addText($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/sede_0_citta'),
      ['bold' => true],
      ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addTextBreak(1);
    $as = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico');
    $section->addText('ANNO SCOLASTICO '.$as,
      ['bold' => true],
      ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addTextBreak(1);
    $section->addText('PROGRAMMA SVOLTO',
      ['bold' => true],
      ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addText('Classe: '.$info['classe_corso'],
      ['bold' => true],
      ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addText('Materia: '.$info['materia'],
      ['bold' => true],
      ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addText($info['docenti'],
      ['bold' => true],
      ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addTextBreak(2);
    // programma
    foreach ($dati['argomenti'] as $arg) {
      $section->addListItem($arg, 0, null, null, $lista_paragrafo);
    }
    // salva documento
    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($dir.$nomefile);
    // invia il documento
    return $this->file($dir.$nomefile, $info['documento']);
  }

}
