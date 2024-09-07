<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Util\RegistroUtil;
use App\Util\StaffUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * SchedaController - mostra le schede informative da visualizzare in una modal window
 *
 * @author Antonello Dessì
 */
class SchedaController extends BaseController {

  /**
   * Dettaglio delle valutazioni per la cattedra e l'alunno indicati
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $cattedra Identificativo della cattedra
   * @param int $alunno Identificativo dell'alunno
   * @param string $periodo Periodo dell'anno scolastico
   *
   * @return Response Pagina di risposta
   *
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  #[Route(path: '/scheda/voti/materia/{cattedra}/{alunno}/{periodo}', name: 'scheda_voti_materia', requirements: ['cattedra' => '\d+', 'alunno' => '\d+', 'periodo' => 'P|S|F|G|R|X'], methods: ['GET'])]
  public function votiMateria(RegistroUtil $reg, TranslatorInterface $trans, int $cattedra,
                              int $alunno, string $periodo): Response {
    // inizializza variabili
    $info = null;
    $dati = null;
    // controllo cattedra
    $cattedra = $this->em->getRepository(\App\Entity\Cattedra::class)->findOneBy(['id' => $cattedra,
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
    // valutazioni
    $materiaTipo = $cattedra->getMateria()->getTipo();
    $valutazioni[$materiaTipo] = unserialize(
      $this->em->getRepository(\App\Entity\Configurazione::class)->getParametro('voti_finali_'.$materiaTipo));
    $listaValori = explode(',', (string) $valutazioni[$materiaTipo]['valori']);
    $listaVoti = explode(',', (string) $valutazioni[$materiaTipo]['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['lista'][$val] = trim($listaVoti[$key], '"');
    }
    // controllo alunno
    $alunno = $this->em->getRepository(\App\Entity\Alunno::class)->findOneBy(['id' => $alunno]);
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
      $voti = $this->em->getRepository(\App\Entity\VotoScrutinio::class)->voti($cattedra->getClasse(), 'P',
        [$alunno->getId()], [$cattedra->getMateria()->getId()], 'C');
      if (empty($voti[$alunno->getId()][$cattedra->getMateria()->getId()])) {
        // valutazione non presente
        $dati['scrutini'][0]['voto'] = null;
      } else {
        // imposta valutazione
        $dati['scrutini'][0]['voto'] =
          $valutazioni['lista'][$voti[$alunno->getId()][$cattedra->getMateria()->getId()]->getUnico()];
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale con trimestri
      $periodoNome = $periodi[3]['nome'];
      // voto primo trimestre/quadrimestre
      $dati['scrutini'][0]['nome'] = 'Scrutinio del '.$periodi[1]['nome'];
      $voti = $this->em->getRepository(\App\Entity\VotoScrutinio::class)->voti($cattedra->getClasse(), 'P',
        [$alunno->getId()], [$cattedra->getMateria()->getId()], 'C');
      if (empty($voti[$alunno->getId()][$cattedra->getMateria()->getId()])) {
        // valutazione non presente
        $dati['scrutini'][0]['voto'] = null;
      } else {
        // imposta valutazione
        $dati['scrutini'][0]['voto'] =
          $valutazioni['lista'][$voti[$alunno->getId()][$cattedra->getMateria()->getId()]->getUnico()];
      }
      // voto secondo periodo
      $dati['scrutini'][1]['nome'] = 'Scrutinio del '.$periodi[2]['nome'];
      $voti = $this->em->getRepository(\App\Entity\VotoScrutinio::class)->voti($cattedra->getClasse(), 'S',
        [$alunno->getId()], [$cattedra->getMateria()->getId()], 'C');
      if (empty($voti[$alunno->getId()][$cattedra->getMateria()->getId()])) {
        // valutazione non presente
        $dati['scrutini'][1]['voto'] = null;
      } else {
        // imposta valutazione
        $dati['scrutini'][1]['voto'] =
          $valutazioni['lista'][$voti[$alunno->getId()][$cattedra->getMateria()->getId()]->getUnico()];
      }
    } elseif (in_array($periodo, ['G', 'R'])) {
      // scrutinio sospeso
      $periodoNome = $trans->trans('label.periodo_G');
      // voto finale
      $dati['scrutini'][0]['nome'] = $trans->trans('label.periodo_F');
      $voti = $this->em->getRepository(\App\Entity\VotoScrutinio::class)->voti($cattedra->getClasse(), 'F',
        [$alunno->getId()], [$cattedra->getMateria()->getId()], 'C');
      if (empty($voti[$alunno->getId()][$cattedra->getMateria()->getId()])) {
        // valutazione non presente
        $dati['scrutini'][0]['voto'] = null;
      } else {
        // imposta valutazione
        $dati['scrutini'][0]['voto'] =
          $valutazioni['lista'][$voti[$alunno->getId()][$cattedra->getMateria()->getId()]->getUnico()];
        $dati['scrutini'][0]['info'] = $voti[$alunno->getId()][$cattedra->getMateria()->getId()];
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
    return $this->render('schede/voti_materia.html.twig', [
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Dettaglio delle note della classe indicata nel periodo previsto
   *
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $classe Identificativo della classe
   * @param string $inizio Data iniziale del periodo previsto
   * @param string $inizio Data finale del periodo previsto
   *
   * @return Response Pagina di risposta
   *
   *
   * @IsGranted("ROLE_STAFF")
   */
  #[Route(path: '/scheda/note/{classe}/{inizio}/{fine}', name: 'scheda_note', requirements: ['classe' => '\d+', 'inizio' => '\d\d\d\d-\d\d-\d\d', 'fine' => '\d\d\d\d-\d\d-\d\d'], methods: ['GET'])]
  public function note(StaffUtil $staff, int $classe, string $inizio, string $fine): Response {
    // inizializza variabili
    $info = null;
    $dati = null;
    // controllo classe
    $classe = $this->em->getRepository(\App\Entity\Classe::class)->findOneBy(['id' => $classe]);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo date
    $dataInizio = \DateTime::createFromFormat('Y-m-d', $inizio);
    $dataFine = \DateTime::createFromFormat('Y-m-d', $fine);
    // informazioni
    $info['classe'] = $classe;
    // legge dati
    $dati = $staff->note($classe, $dataInizio, $dataFine);
    // visualizza pagina
    return $this->render('schede/note.html.twig', [
      'info' => $info,
      'dati' => $dati]);
  }

}
