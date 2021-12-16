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


namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use App\Entity\DefinizioneScrutinio;
use App\Form\DefinizioneScrutinioType;


/**
 * ScuolaController - gestione dei dati della scuola
 */
class ScuolaController extends BaseController {

  /**
   * Gestisce la modifica dei dati degli scrutini
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param string $periodo Periodo dello scrutinio
   *
   * @Route("/scuola/scrutini/{periodo}", name="scuola_scrutini",
   *    requirements={"periodo": "P|S|F|E|U|X"},
   *    defaults={"periodo": ""},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function scrutiniAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                 $periodo): Response {
    // init
    $dati = [];
    $info = [];
    // lista periodi scrutinio
    $info['listaPeriodi'] = $em->getRepository('App:Configurazione')->infoScrutini();
    $info['listaPeriodi']['E'] = $trans->trans('label.scrutini_periodo_E');
    $info['listaPeriodi']['U'] = $trans->trans('label.scrutini_periodo_U');
    // periodo predefinito
    if (empty($periodo)) {
      // ultimo periodo configurato
      $periodo = $em->getRepository('App:DefinizioneScrutinio')->ultimo();
    }
    $info['periodo'] = $periodo;
    // legge dati
    $definizione = $em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo($periodo);
    if (!$definizione) {
      // nuova definizione
      $argomenti[1] = $trans->trans('label.verbale_scrutinio_'.$periodo);
      $argomenti[2] = $trans->trans('label.verbale_situazioni_particolari');
      $struttura[1] = ['ScrutinioInizio', false, []];
      $struttura[2] = ['ScrutinioSvolgimento', false, ['sezione' => 'Punto primo', 'argomento' => 1]];
      $struttura[3] = ['ScrutinioFine', false, []];
      $struttura[4] = ['Argomento', true, ['sezione' => 'Punto secondo', 'argomento' => 2]];
      $definizione = (new DefinizioneScrutinio())
        ->setData(new \DateTime('today'))
        ->setDataProposte(new \DateTime('today'))
        ->setPeriodo($periodo)
        ->setArgomenti($argomenti)
        ->setStruttura($struttura);
      $em->persist($definizione);
    }
    // form
    $form = $this->createForm(DefinizioneScrutinioType::class, $definizione,
      ['returnUrl' => $this->generateUrl('scuola_scrutini'), 'dati' => $definizione->getClassiVisibili()]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // classi visibili
      $classiVisibili = $definizione->getClassiVisibili();
      for ($cl = 1; $cl <= 5; $cl++) {
        if ($classiVisibili[$cl] && ($ora = $form->get('classiVisibiliOra'.$cl)->getData())) {
          // aggiunge ora
          $classiVisibili[$cl]->setTime($ora->format('H'), $ora->format('i'));
        }
      }
      $definizione->setClassiVisibili($classiVisibili);
      // aggiorna classi visibili di scrutini
      $subquery = $em->getRepository('App:Classe')->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.anno=:anno')
        ->getDQL();
      for ($cl = 1; $cl <= 5; $cl++) {
        $risultato = $em->getRepository('App:Scrutinio')->createQueryBuilder('s')
          ->update()
          ->set('s.modificato', ':modificato')
          ->set('s.visibile', ':visibile')
          ->where('s.periodo=:periodo AND s.classe IN ('.$subquery.')')
          ->setParameters(['modificato' => new \DateTime(), 'visibile' => $classiVisibili[$cl],
            'periodo' => $periodo, 'anno' => $cl])
          ->getQuery()
          ->getResult();
      }
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('scuola', 'scrutini', $dati, $info, [$form->createView(), 'message.definizione_scrutinio']);
  }

}
