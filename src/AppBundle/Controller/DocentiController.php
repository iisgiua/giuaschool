<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Entity\Cattedra;
use AppBundle\Util\CsvImporter;


/**
 * DocentiController - gestione docenti
 */
class DocentiController extends Controller {

  /**
   * Gestione docenti
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/", name="docenti")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function docentiAction() {
    return $this->render('docenti/index.html.twig', array(
      'pagina_titolo' => 'page.docenti',
    ));
  }

  /**
   * Importa docenti da file
   *
   * @param Request $request Pagina richiesta
   * @param CsvImporter $importer Servizio per l'importazione dei dati da file CSV
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/importa/", name="docenti_importa")
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function importaAction(Request $request, CsvImporter $importer) {
    $lista = null;
    // form docenti
    $form1 = $this->container->get('form.factory')->createNamedBuilder('docenti_importa_docenti', FormType::class)
      ->add('file', FileType::class, array('label' => 'label.csv_file',
        'required' => true
        ))
      ->add('onlynew', CheckboxType::class, array('label' => 'label.solo_nuovi',
        'required' => false
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form1->handleRequest($request);
    if ($form1->isSubmitted() && $form1->isValid()) {
      // importa file
      $file = $form1->get('file')->getData();
      $lista = $importer->importaDocenti($file, $form1);
    }
    // form cattedre
    $form2 = $this->container->get('form.factory')->createNamedBuilder('docenti_importa_cattedre', FormType::class)
      ->add('file', FileType::class, array('label' => 'label.csv_file',
        'required' => true
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form2->handleRequest($request);
    if ($form2->isSubmitted() && $form2->isValid()) {
      // importa file
      $file = $form2->get('file')->getData();
      $lista = $importer->importaCattedre($file, $form2);
    }
    return $this->render('docenti/importa.html.twig', array(
      'pagina_titolo' => 'page.importa_docenti',
      'lista' => $lista,
      'form1' => $form1->createView(),
      'form1_title' => 'title.importa_docenti',
      'form1_help' => 'message.importa_docenti',
      'form1_success' => null,
      'form2' => $form2->createView(),
      'form2_title' => 'title.importa_cattedre',
      'form2_help' => 'message.importa_cattedre',
      'form2_success' => null,
    ));
  }

  /**
   * Gestisce la modifica dei dati dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $page Numero di pagina per la lista dei docenti
   *
   * @Route("/docenti/modifica/", name="docenti_modifica", defaults={"page": 0})
   * @Route("/docenti/modifica/{page}", name="docenti_modifica-param", requirements={"page": "\d+"})
   *
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function modificaAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $page) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/docenti_modifica/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/docenti_modifica/cognome', '');
    if ($page == 0) {
      // pagina non definita: la cerca in sessione
      $page = $session->get('/APP/ROUTE/docenti_modifica/page', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/docenti_modifica/page', $page);
    }
    // form di ricerca
    $limit = 10;
    $form = $this->container->get('form.factory')->createNamedBuilder('docenti_modifica', FormType::class)
      ->setAction($this->generateUrl('docenti_modifica'))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false
        ))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $page = 1;
      $session->set('/APP/ROUTE/docenti_modifica/nome', $search['nome']);
      $session->set('/APP/ROUTE/docenti_modifica/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/docenti_modifica/page', $page);
    }
    // lista docenti
    $paginator = $em->getRepository('AppBundle:Docente')->findAll($search, $page, $limit);
    // mostra la pagina di risposta
    return $this->render('docenti/modifica.html.twig', array(
      'pagina_titolo' => 'page.modifica_docenti',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $paginator,
      'page' => $page,
      'maxPages' => ceil($paginator->count() / $limit),
    ));
  }

  /**
   * Abilitazione o disabilitazione dei docenti
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   * @param string $enable Valore 'true' per abilitare, valore 'false' per disabilitare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/modifica/enable/{id}/{enable}", name="docenti_modifica_enable",
   *    requirements={"id": "\d+", "enable": "true|false"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function modificaEnableAction(EntityManagerInterface $em, $id, $enable) {
    $docente = $em->getRepository('AppBundle:Docente')->find($id);
    if ($docente) {
      // abilita o disabilita
      $docente->setAbilitato($enable === 'true');
      $em->flush();
      // redirezione
      return $this->redirectToRoute('docenti_modifica');
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Modifica dei dati di un docente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/modifica/edit/{id}", name="docenti_modifica_edit", requirements={"id": "\d+"})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function modificaEditAction(Request $request, EntityManagerInterface $em, $id) {
    $docente = $em->getRepository('AppBundle:Docente')->find($id);
    if ($docente) {
      // form
      $form = $this->container->get('form.factory')->createNamedBuilder('docenti_modifica_edit', FormType::class, $docente)
        ->add('nome', TextType::class, array('label' => 'label.nome',
          'required' => true))
        ->add('cognome', TextType::class, array('label' => 'label.cognome',
          'required' => true))
        ->add('sesso', ChoiceType::class, array('label' => 'label.sesso',
          'choices' => array('label.maschile' => 'M', 'label.femminile' => 'F'),
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline'],
          'required' => true))
        ->add('email', TextType::class, array('label' => 'label.email',
          'required' => true))
        ->add('codiceFiscale', TextType::class, array('label' => 'label.codice_fiscale',
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$this->generateUrl('docenti_modifica')."'"]))
        ->getForm();
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // memorizza modifiche
        $em->flush();
        // redirect
        return $this->redirectToRoute('docenti_modifica');
      }
      // mostra la pagina di risposta
      return $this->render('docenti/edit.html.twig', array(
        'pagina_titolo' => 'page.modifica_docenti',
        'form' => $form->createView(),
        'form_title' => 'title.modifica_docenti',
        'form_help' => 'message.required_fields',
        'form_success' => null,
      ));
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Gestisce la modifica delle cattedre dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $page Numero di pagina per la lista delle cattedre
   *
   * @Route("/docenti/cattedre/", name="docenti_cattedre", defaults={"page": 0})
   * @Route("/docenti/cattedre/{page}", name="docenti_cattedre-param", requirements={"page": "\d+"})
   *
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function cattedreAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $page) {
    // recupera criteri dalla sessione
    $search = array();
    $search['classe'] = $session->get('/APP/ROUTE/docenti_cattedre/classe', null);
    $search['materia'] = $session->get('/APP/ROUTE/docenti_cattedre/materia', null);
    $search['docente'] = $session->get('/APP/ROUTE/docenti_cattedre/docente', null);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : null);
    $materia = ($search['materia'] > 0 ? $em->getRepository('AppBundle:Materia')->find($search['materia']) : null);
    $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) : null);
    if ($page == 0) {
      // pagina non definita: la cerca in sessione
      $page = $session->get('/APP/ROUTE/docenti_cattedre/page', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/docenti_cattedre/page', $page);
    }
    // form di ricerca
    $limit = 10;
    $form = $this->container->get('form.factory')->createNamedBuilder('docenti_cattedre', FormType::class)
      ->setAction($this->generateUrl('docenti_cattedre'))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().' '.$obj->getSezione();
          },
        'placeholder' => 'label.classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => 'sede.citta',
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('materia', EntityType::class, array('label' => 'label.materia',
        'data' => $materia,
        'class' => 'AppBundle:Materia',
        'choice_label' => 'nome',
        'placeholder' => 'label.materia',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->where("c.tipo IN ('N','R','S')")
              ->orderBy('c.nome', 'ASC');
          },
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('docente', EntityType::class, array('label' => 'label.docente',
        'data' => $docente,
        'class' => 'AppBundle:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')';
          },
        'placeholder' => 'label.docente',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d NOT INSTANCE OF AppBundle:Preside AND d.abilitato=1')
              ->orderBy('d.cognome,d.nome', 'ASC');
          },
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = ($form->get('classe')->getData() ? $form->get('classe')->getData()->getId() : null);
      $search['materia'] = ($form->get('materia')->getData() ? $form->get('materia')->getData()->getId() : null);
      $search['docente'] = ($form->get('docente')->getData() ? $form->get('docente')->getData()->getId() : null);
      $page = 1;
      $session->set('/APP/ROUTE/docenti_cattedre/classe', $search['classe']);
      $session->set('/APP/ROUTE/docenti_cattedre/materia', $search['materia']);
      $session->set('/APP/ROUTE/docenti_cattedre/docente', $search['docente']);
      $session->set('/APP/ROUTE/docenti_cattedre/page', $page);
    }
    // lista cattedre
    $paginator = $em->getRepository('AppBundle:Cattedra')->findAll($search, $page, $limit);
    // mostra la pagina di risposta
    return $this->render('docenti/cattedre.html.twig', array(
      'pagina_titolo' => 'page.cattedre',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $paginator,
      'page' => $page,
      'maxPages' => ceil($paginator->count() / $limit),
    ));
  }

  /**
   * Crea una nuova cattedra per un docente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @Route("/docenti/cattedre/add/", name="docenti_cattedre_add")
   *
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function cattedreAddAction(Request $request, EntityManagerInterface $em) {
    // form di inserimento
    $cattedra = new Cattedra();
    $form = $this->container->get('form.factory')->createNamedBuilder('docenti_cattedre_add', FormType::class, $cattedra)
      ->add('docente', EntityType::class, array('label' => 'label.docente',
        'class' => 'AppBundle:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')';
          },
        'placeholder' => 'label.choose_option',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d NOT INSTANCE OF AppBundle:Preside AND d.abilitato=1')
              ->orderBy('d.cognome,d.nome', 'ASC');
          },
        'required' => true))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().' '.$obj->getSezione();
          },
        'placeholder' => 'label.choose_option',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => 'sede.citta',
        'required' => true))
      ->add('materia', EntityType::class, array('label' => 'label.materia',
        'class' => 'AppBundle:Materia',
        'choice_label' => 'nome',
        'placeholder' => 'label.choose_option',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->where("c.tipo IN ('N','R','S')")
              ->orderBy('c.nome', 'ASC');
          },
        'required' => true))
      ->add('alunno', EntityType::class, array('label' => 'label.alunno_H',
        'class' => 'AppBundle:Alunno',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')';
          },
        'placeholder' => 'label.choose_option',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('a')
              ->where("a.bes='H' AND a.abilitato=1")
              ->orderBy('a.cognome,a.nome', 'ASC');
          },
        'required' => false))
      ->add('tipo', ChoiceType::class, array('label' => 'label.tipo',
        'choices' => array('label.tipo_N' => 'N', 'label.tipo_I' => 'I', 'label.tipo_S' => 'S', 'label.tipo_P' => 'P'),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('supplenza', CheckboxType::class, array('label' => 'label.supplenza',
        'required' => false
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$this->generateUrl('docenti_cattedre')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $cattedra->setAttiva(true);
      if ($cattedra->getMateria()->getTipo() == 'S') {
        // sostegno
        $cattedra->setTipo('S');
        if ($cattedra->getAlunno() && $cattedra->getAlunno()->getClasse() != $cattedra->getClasse()) {
          // classe diversa da quella di alunno
          $form->get('classe')->addError(new FormError($this->get('translator')->trans('exception.classe_errata')));
        }
      } else {
        // materia non è sostegno, nessun alunno deve essere presente
        $cattedra->setAlunno(null);
        if ($cattedra->getTipo() == 'S') {
          // tipo sostegno su materia non di sostegno
          $form->get('tipo')->addError(new FormError($this->get('translator')->trans('exception.tipo_sostegno')));
        }
      }
      // controlla esistenza di cattedra
      $lista = $em->getRepository('AppBundle:Cattedra')->findBy(array(
        'docente' => $cattedra->getDocente(),
        'classe' => $cattedra->getClasse(),
        'materia' => $cattedra->getMateria(),
        'alunno' => $cattedra->getAlunno()));
      if (count($lista) > 0) {
        // cattedra esiste già
        $form->addError(new FormError($this->get('translator')->trans('exception.cattedra_esiste')));
      }
      if ($form->isValid()) {
        // memorizza dati
        $em->persist($cattedra);
        $em->flush();
        return $this->redirectToRoute('docenti_cattedre');
      }
    }
    // mostra la pagina di risposta
    return $this->render('docenti/edit.html.twig', array(
      'pagina_titolo' => 'page.cattedre',
      'form' => $form->createView(),
      'form_title' => 'title.nuova_cattedra',
      'form_help' => 'message.required_fields',
      'form_success' => null,
    ));
  }

  /**
   * Abilitazione o disabilitazione delle cattedre
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   * @param string $enable Valore 'true' per abilitare, valore 'false' per disabilitare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/cattedre/enable/{id}/{enable}", name="docenti_cattedre_enable",
   *    requirements={"id": "\d+", "enable": "true|false"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function cattedreEnableAction(EntityManagerInterface $em, $id, $enable) {
    $cattedra = $em->getRepository('AppBundle:Cattedra')->find($id);
    if ($cattedra) {
      // abilita o disabilita
      $cattedra->setAttiva($enable === 'true');
      $em->flush();
      // redirezione
      return $this->redirectToRoute('docenti_cattedre');
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Importa docenti da file
   *
   * @param Request $request Pagina richiesta
   * @param CsvImporter $importer Servizio per l'importazione dei dati da file CSV
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/colloqui/", name="docenti_colloqui")
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function colloquiAction(Request $request, CsvImporter $importer) {
    $lista = null;
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('docenti_colloqui', FormType::class)
      ->add('file', FileType::class, array('label' => 'label.csv_file',
        'required' => true
        ))
      ->add('onlynew', CheckboxType::class, array('label' => 'label.solo_nuovi',
        'required' => false
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // importa file
      $file = $form->get('file')->getData();
      $lista = $importer->importaColloqui($file, $form);
    }
    return $this->render('docenti/colloqui.html.twig', array(
      'pagina_titolo' => 'page.docenti_colloqui',
      'lista' => $lista,
      'form' => $form->createView(),
      'form_title' => 'title.docenti_colloqui_importa',
      'form_help' => 'message.docenti_colloqui_importa',
      'form_success' => null,
    ));
  }

}

