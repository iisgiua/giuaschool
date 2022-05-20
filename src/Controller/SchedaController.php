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
   * @IsGranted("ROLE_DOCENTE")
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
    $info['edcivica'] = ($cattedra->getMateria()->getTipo() == 'E');
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
    // recupera dati
    $dati = $reg->dettagliVoti($this->getUser(), $cattedra, $alunno, $info['edcivica']);
    $dati['lezioni'] = $reg->assenzeMateria($cattedra, $alunno);
    $periodi = $reg->infoPeriodi();
    if ($periodo == 'P') {
      // primo trimestre/quadrimestre
      $periodoNome = $periodi[1]['nome'];
    } elseif ($periodo == 'S' || ($periodo == 'F' && empty($periodi[3]['nome']))) {
      // secondo trimestre o scrutinio finale di quadrimestri
      $periodoNome = $periodi[2]['nome'];
      // voto primo trimestre/quadrimestre
      $dati['scrutini'][0]['nome'] = 'Scrutinio del '.$periodi[1]['nome'];
      $dati['scrutini'][0]['voto'] = null;
      $voto = $em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.alunno=:alunno AND vs.materia=:materia AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['alunno' => $alunno, 'classe' => $cattedra->getClasse(), 'materia' => $cattedra->getMateria(),
          'periodo' => 'P', 'stato' => 'C'])
        ->getQuery()
        ->getOneOrNullResult();
      if ($voto) {
        // retrocompatibilità per A.S 21/22
        $giudizi = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Discreto', 24 => 'Buono',  25 => 'Distinto', 26 => 'Ottimo'];
        $dati['scrutini'][0]['voto'] = ($voto->getUnico() == 0 ? 'NC' :
          ($voto->getUnico() >= 20 ? $giudizi[$voto->getUnico()] :
          ($voto->getUnico() == 3 && $cattedra->getMateria()->getTipo() == 'E' ? 'NC' : $voto->getUnico())));
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale con trimestri
      $periodoNome = $periodi[3]['nome'];
      // voto primo trimestre/quadrimestre
      $dati['scrutini'][0]['nome'] = 'Scrutinio del '.$periodi[1]['nome'];
      $dati['scrutini'][0]['voto'] = null;
      $dati['scrutini'][1]['nome'] = 'Scrutinio del '.$periodi[2]['nome'];
      $dati['scrutini'][1]['voto'] = null;
      $voti = $em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.alunno=:alunno AND vs.materia=:materia AND s.classe=:classe AND s.periodo IN (:periodi) AND s.stato=:stato')
        ->setParameters(['alunno' => $alunno, 'classe' => $cattedra->getClasse(), 'materia' => $cattedra->getMateria(),
          'periodi' => ['P', 'S'], 'stato' => 'C'])
        ->orderBy('s.periodo')
        ->getQuery()
        ->getResult();
      foreach ($voti as $p=>$v) {
        // retrocompatibilità per A.S 21/22
        $giudizi = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Discreto', 24 => 'Buono',  25 => 'Distinto', 26 => 'Ottimo'];
        if ($v !== null) {
          $dati['scrutini'][$p]['voto'] = ($v->getUnico() == 0 ? 'NC' :
            ($v->getUnico() >= 20 ? $giudizi[$v->getUnico()] :
            ($v->getUnico() == 3 && $cattedra->getMateria()->getTipo() == 'E' ? 'NC' : $v->getUnico())));
        }
      }
    }
    // cancella dati inutili
    foreach (['lista', 'media', 'lezioni'] as $tipo) {
      foreach ($dati[$tipo] as $per=>$d) {
        if ($per != $periodoNome) {
          unset($dati[$tipo][$per]);
        }
      }
    }
    // visualizza pagina
    return $this->render('schede/voti_materia.html.twig', array(
      'info' => $info,
      'dati' => $dati,
    ));
  }

}
