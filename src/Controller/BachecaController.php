<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;


use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;
use App\Entity\Avviso;
use App\Entity\Classe;
use App\Util\BachecaUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * BachecaController - gestione della bacheca
 *
 * @author Antonello Dessì
 */
class BachecaController extends BaseController {

  /**
   * Visualizza gli avvisi destinati ai docenti
   *
   * @param Request $request Pagina richiesta
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/bacheca/avvisi/{pagina}', name: 'bacheca_avvisi', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function avvisi(Request $request, BachecaUtil $bac, int $pagina): Response {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    // recupera criteri dalla sessione
    $cerca = [];
    $cerca['visualizza'] = $this->reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi/visualizza', 'T');
    $cerca['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('bacheca_avvisi', FormType::class)
      ->add('visualizza', ChoiceType::class, ['label' => 'label.avvisi_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.avvisi_da_leggere' => 'D', 'label.avvisi_tutti' => 'T'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true])
      ->add('oggetto', TextType::class, ['label' => 'label.avvisi_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.filtra',
	      'attr' => ['class' => 'btn-primary']])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi/visualizza', $cerca['visualizza']);
      $this->reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi/oggetto', $cerca['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->bachecaAvvisi($cerca, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render(
      'bacheca/avvisi.html.twig', ['pagina_titolo' => 'page.bacheca_avvisi',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati]);
  }

  /**
   * Mostra i dettagli di un avviso destinato al docente e segna la lettura
   *
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/bacheca/avvisi/dettagli/{id}', name: 'bacheca_avvisi_dettagli', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_DOCENTE') or is_granted('ROLE_ATA')"))]
  public function avvisiDettagli(BachecaUtil $bac, int $id): Response {
    // inizializza
    $dati = null;
    // controllo avviso
    $avviso = $this->em->getRepository(Avviso::class)->find($id);
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
    return $this->render('bacheca/scheda_avviso.html.twig', [
      'dati' => $dati]);
  }

  /**
   * Mostra gli avvisi destinati agli alunni della classe
   *
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $classe ID della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/bacheca/avvisi/alunni/{classe}', name: 'bacheca_avvisi_alunni', requirements: ['classe' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function avvisiAlunni(BachecaUtil $bac, int $classe): Response {
    // inizializza
    $dati = null;
    // controllo classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->bachecaAvvisiAlunni($classe);
    // visualizza pagina
    return $this->render('bacheca/scheda_avviso_alunni.html.twig', ['dati' => $dati,
	'classe' => $classe]);
  }

  /**
   * Conferma la lettura dell'avviso destinato agli alunni della classe
   *
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $classe ID della classe
   * @param mixed $id ID dell'avviso o "ALL" per tutti gli avvisi della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/bacheca/avvisi/alunni/firma/{classe}/{id}', name: 'bacheca_avvisi_alunni_firma', requirements: ['classe' => '\d+', 'id' => '\d+|ALL'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function avvisiAlunniFirma(BachecaUtil $bac, int $classe, string $id): Response {
    // controllo classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // aggiorna firma
    $bac->letturaAvvisoAlunni($classe, $id);
    // ok: memorizza dati
    $this->em->flush();
    // redirect
    return $this->redirectToRoute('lezioni');
  }

  /**
   * Visualizza gli avvisi destinati al personale ATA
   *
   * @param Request $request Pagina richiesta
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/bacheca/avvisi/ata/{pagina}', name: 'bacheca_avvisi_ata', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_ATA')]
  public function avvisiATA(Request $request, BachecaUtil $bac, int $pagina): Response {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    // recupera criteri dalla sessione
    $cerca = [];
    $cerca['visualizza'] = $this->reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi_ata/visualizza', 'T');
    $cerca['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi_ata/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/bacheca_avvisi_ata/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi_ata/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('bacheca_avvisi_ata', FormType::class)
      ->add('visualizza', ChoiceType::class, ['label' => 'label.avvisi_filtro_visualizza',
	'data' => $cerca['visualizza'],
	'choices' => ['label.avvisi_da_leggere' => 'D',
	'label.avvisi_tutti' => 'T'],
	'label_attr' => ['class' => 'sr-only'],
	'choice_attr' => fn($val,
	$key, $index) => ['class' => 'gs-no-placeholder'],
	'attr' => ['class' => 'gs-placeholder'],
	'required' => true])
      ->add('oggetto', TextType::class, ['label' => 'label.avvisi_filtro_oggetto',
	'data' => $cerca['oggetto'],
	'attr' => ['placeholder' => 'label.oggetto',
	'class' => 'gs-placeholder'],
	'label_attr' => ['class' => 'sr-only'],
	'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi_ata/visualizza', $cerca['visualizza']);
      $this->reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi_ata/oggetto', $cerca['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/bacheca_avvisi_ata/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->bachecaAvvisi($cerca, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('bacheca/avvisi_ata.html.twig', ['pagina_titolo' => 'page.bacheca_avvisi_ata',
	'form' => $form->createView(),
	'form_help' => null,
	'form_success' => null,
	'page' => $pagina,
	'maxPages' => ceil($dati['lista']->count() / $limite),
	'dati' => $dati]);
  }

}
