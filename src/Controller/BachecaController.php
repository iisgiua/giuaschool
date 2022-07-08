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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\Avviso;
use App\Entity\Classe;
use App\Util\BachecaUtil;


/**
 * BachecaController - gestione della bacheca
 */
class BachecaController extends AbstractController {

  /**
   * Visualizza gli avvisi destinati ai docenti
   *
   * @param Request $request Pagina richiesta
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/{pagina}", name="bacheca_avvisi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisiAction(Request $request, RequestStack $reqstack, BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    // recupera criteri dalla sessione
    $cerca = array();
    $cerca['visualizza'] = $reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi/visualizza', 'T');
    $cerca['oggetto'] = $reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('bacheca_avvisi', FormType::class)
      ->add('visualizza', ChoiceType::class, array('label' => 'label.avvisi_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.avvisi_da_leggere' => 'D', 'label.avvisi_tutti' => 'T'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('oggetto', TextType::class, array('label' => 'label.avvisi_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.filtra',
        'attr' => ['class' => 'btn-primary']))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi/visualizza', $cerca['visualizza']);
      $reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi/oggetto', $cerca['oggetto']);
      $reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->bachecaAvvisi($cerca, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('bacheca/avvisi.html.twig', array(
      'pagina_titolo' => 'page.bacheca_avvisi',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i dettagli di un avviso destinato al docente e segna la lettura
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/dettagli/{id}", name="bacheca_avvisi_dettagli",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_DOCENTE') or is_granted('ROLE_ATA')")
   */
  public function avvisiDettagliAction(EntityManagerInterface $em, BachecaUtil $bac, $id) {
    // inizializza
    $dati = null;
    // controllo avviso
    $avviso = $em->getRepository('App\Entity\Avviso')->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if (!$bac->destinatario($avviso, $this->getUser())) {
      // errore: non è destinatario dell'avviso
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->dettagliAvviso($avviso);
    // aggiorna lettura
    $bac->letturaAvviso($avviso, $this->getUser());
    // visualizza pagina
    return $this->render('bacheca/scheda_avviso.html.twig', array(
      'dati' => $dati,
    ));
  }

  /**
   * Mostra gli avvisi destinati agli alunni della classe
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $classe ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/alunni/{classe}", name="bacheca_avvisi_alunni",
   *    requirements={"classe": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisiAlunniAction(EntityManagerInterface $em, BachecaUtil $bac, $classe) {
    // inizializza
    $dati = null;
    // controllo classe
    $classe = $em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->bachecaAvvisiAlunni($classe);
    // visualizza pagina
    return $this->render('bacheca/scheda_avviso_alunni.html.twig', array(
      'dati' => $dati,
      'classe' => $classe,
    ));
  }

  /**
   * Conferma la lettura dell'avviso destinato agli alunni della classe
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $classe ID della classe
   * @param mixed $id ID dell'avviso o "ALL" per tutti gli avvisi della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/alunni/firma/{classe}/{id}", name="bacheca_avvisi_alunni_firma",
   *    requirements={"classe": "\d+", "id": "\d+|ALL"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisiAlunniFirmaAction(EntityManagerInterface $em, BachecaUtil $bac, $classe, $id) {
    // controllo classe
    $classe = $em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // aggiorna firma
    $bac->letturaAvvisoAlunni($classe, $id);
    // ok: memorizza dati
    $em->flush();
    // redirect
    return $this->redirectToRoute('lezioni');
  }

  /**
   * Visualizza gli avvisi destinati al personale ATA
   *
   * @param Request $request Pagina richiesta
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/bacheca/avvisi/ata/{pagina}", name="bacheca_avvisi_ata",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_ATA")
   */
  public function avvisiATAAction(Request $request, RequestStack $reqstack, BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    // recupera criteri dalla sessione
    $cerca = array();
    $cerca['visualizza'] = $reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi_ata/visualizza', 'T');
    $cerca['oggetto'] = $reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi_ata/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi_ata/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi_ata/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('bacheca_avvisi_ata', FormType::class)
      ->add('visualizza', ChoiceType::class, array('label' => 'label.avvisi_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.avvisi_da_leggere' => 'D', 'label.avvisi_tutti' => 'T'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('oggetto', TextType::class, array('label' => 'label.avvisi_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi_ata/visualizza', $cerca['visualizza']);
      $reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi_ata/oggetto', $cerca['oggetto']);
      $reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi_ata/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->bachecaAvvisi($cerca, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('bacheca/avvisi_ata.html.twig', array(
      'pagina_titolo' => 'page.bacheca_avvisi_ata',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

}
