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


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Util\RegistroUtil;


/**
 * SchedaController - mostra le schede informative da visualizzare in una modal window
 */
class SchedaController extends AbstractController {

  /**
   * Dettaglio delle valutazioni per la cattedra e l'alunno indicati
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scheda/voti/materia/{cattedra}/{alunno}/{periodo}", name="scheda_voti_materia",
   *    requirements={"cattedra": "\d+", "alunno": "\d+", "periodo": "P|S|F|I|1|2|0"},
   *    methods={"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiMateriaAction(EntityManagerInterface $em, RegistroUtil $reg, $cattedra, $alunno, $periodo) {
    // inizializza variabili
    $info = null;
    $dati = null;
    // controllo cattedra
    $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni cattedra
    $classe = $cattedra->getClasse();
    $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni alunno
    $info['alunno'] = $alunno->getCognome().' '.$alunno->getNome().' ('.
        $alunno->getDataNascita()->format('d/m/Y').')';
    $info['sesso'] = $alunno->getSesso();
    $info['bes'] = $alunno->getBes();
    $info['note'] = $alunno->getNote();
    // recupera dati
    $dati = $reg->dettagliVoti($this->getUser(), $cattedra, $alunno);
    $dati['lezioni'] = $reg->assenzeMateria($cattedra, $alunno);
    $periodi = $reg->infoPeriodi();
    if ($periodo == 'P') {
      foreach ($dati['lista'] as $per=>$d) {
        if ($per != $periodi[1]['nome']) {
          unset($dati['lista'][$per]);
          unset($dati['media'][$per]);
          unset($dati['lezioni'][$per]);
        }
      }
    } elseif ($periodo == '1') {
      foreach ($dati['lista'] as $per=>$d) {
        if ($per != $periodi[2]['nome']) {
          unset($dati['lista'][$per]);
          unset($dati['media'][$per]);
          unset($dati['lezioni'][$per]);
        }
      }
      // voto primo trimestre
      $giudizi = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
      $dati['precedente'][0]['nome'] = 'Scrutinio del '.$periodi[1]['nome'];
      $voto = $em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.alunno=:alunno AND vs.materia=:materia AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['alunno' => $alunno, 'classe' => $cattedra->getClasse(), 'materia' => $cattedra->getMateria(),
          'periodo' => 'P', 'stato' => 'C'])
        ->getQuery()
        ->getOneOrNullResult();
      if ($voto) {
        $dati['precedente'][0]['voto'] = ($voto->getUnico() == 0 ? 'NC' : ($voto->getUnico() >= 20 ? $giudizi[$voto->getUnico()] : $voto->getUnico()));
      } else {
        $dati['precedente'][0]['voto'] = null;
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      foreach ($dati['lista'] as $per=>$d) {
        if ($per != $periodi[2]['nome']) {
          unset($dati['lista'][$per]);
          unset($dati['media'][$per]);
          unset($dati['lezioni'][$per]);
        }
      }
      // voto primo trimestre
      $giudizi = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
      $dati['precedente'][0]['nome'] = 'Scrutinio del '.$periodi[1]['nome'];
      $voto = $em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.alunno=:alunno AND vs.materia=:materia AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['alunno' => $alunno, 'classe' => $cattedra->getClasse(), 'materia' => $cattedra->getMateria(),
          'periodo' => 'P', 'stato' => 'C'])
        ->getQuery()
        ->getOneOrNullResult();
      if ($voto) {
        $dati['precedente'][0]['voto'] = ($voto->getUnico() == 0 ? 'NC' : ($voto->getUnico() >= 20 ? $giudizi[$voto->getUnico()] : $voto->getUnico()));
      } else {
        $dati['precedente'][0]['voto'] = null;
      }
      // valutazione intermedia
      $giudizi = [30 => 'NC', 31 => 'Scarso', 32 => 'Insuff.', 33 => 'Mediocre', 34 => 'Suff.', 35 => 'Discreto', 36 => 'Buono', 37 => 'Ottimo'];
      $dati['precedente'][1]['nome'] = 'Valutazione intermedia';
      $voto = $em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.alunno=:alunno AND vs.materia=:materia AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['alunno' => $alunno, 'classe' => $cattedra->getClasse(), 'materia' => $cattedra->getMateria(),
          'periodo' => '1', 'stato' => 'C'])
        ->getQuery()
        ->getOneOrNullResult();
      if ($voto) {
        $dati['precedente'][1]['voto'] = $giudizi[$voto->getUnico()];
      } else {
        $dati['precedente'][1]['voto'] = null;
      }
    }
    // visualizza pagina
    return $this->render('schede/voti_materia.html.twig', array(
      'info' => $info,
      'dati' => $dati,
    ));
  }

}

