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
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use AppBundle\Entity\CambioClasse;
use AppBundle\Util\CsvImporter;
use AppBundle\Util\LogHandler;
use AppBundle\Util\PdfManager;


/**
 * AlunniController - gestione alunni e genitori
 */
class AlunniController extends Controller {

  /**
   * Gestione alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/", name="alunni")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function alunniAction() {
    return $this->render('alunni/index.html.twig', array(
      'pagina_titolo' => 'page.alunni',
    ));
  }

  /**
   * Importa alunni e genitori da file
   *
   * @param Request $request Pagina richiesta
   * @param CsvImporter $importer Servizio per l'importazione dei dati da file CSV
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/importa/", name="alunni_importa")
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function importaAction(Request $request, CsvImporter $importer) {
    $lista = null;
    // form alunni
    $form = $this->container->get('form.factory')->createNamedBuilder('alunni_importa', FormType::class)
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
      $lista = $importer->importaAlunni($file, $form);
    }
    // mostra pagina di risposta
    return $this->render('alunni/importa.html.twig', array(
      'pagina_titolo' => 'page.importa_alunni',
      'lista' => $lista,
      'form' => $form->createView(),
      'form_title' => 'title.importa_alunni',
      'form_help' => 'message.importa_alunni',
      'form_success' => null,
    ));
  }

  /**
   * Gestisce la modifica dei dati dei alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $page Numero di pagina per la lista dei alunni
   *
   * @Route("/alunni/modifica/", name="alunni_modifica", defaults={"page": 0})
   * @Route("/alunni/modifica/{page}", name="alunni_modifica-param", requirements={"page": "\d+"})
   *
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function modificaAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $page) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/alunni_modifica/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/alunni_modifica/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/alunni_modifica/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($page == 0) {
      // pagina non definita: la cerca in sessione
      $page = $session->get('/APP/ROUTE/alunni_modifica/page', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/alunni_modifica/page', $page);
    }
    // form di ricerca
    $limit = 10;
    $classi = $em->getRepository('AppBundle:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    $classi[] = -1;
    $form = $this->container->get('form.factory')->createNamedBuilder('alunni_modifica', FormType::class)
      ->setAction($this->generateUrl('alunni_modifica'))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return (is_object($obj) ? $obj->getAnno().' '.$obj->getSezione() :
              $this->get('translator')->trans('label.nessuna_classe'));
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return (is_object($obj)  ? $obj->getSede()->getCitta() :
              $this->get('translator')->trans('label.altro'));
          },
        'placeholder' => 'label.classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() :
        ($form->get('classe')->getData() == -1 ? -1 : 0));
      $page = 1;
      $session->set('/APP/ROUTE/alunni_modifica/nome', $search['nome']);
      $session->set('/APP/ROUTE/alunni_modifica/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/alunni_modifica/classe', $search['classe']);
      $session->set('/APP/ROUTE/alunni_modifica/page', $page);
    }
    // lista alunni
    $paginator = $em->getRepository('AppBundle:Alunno')->findAll($search, $page, $limit);
    // mostra la pagina di risposta
    return $this->render('alunni/modifica.html.twig', array(
      'pagina_titolo' => 'page.modifica_alunni',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $paginator,
      'page' => $page,
      'maxPages' => ceil($paginator->count() / $limit),
    ));
  }

  /**
   * Abilitazione o disabilitazione degli alunni
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   * @param string $enable Valore 'true' per abilitare, valore 'false' per disabilitare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/modifica/enable/{id}/{enable}", name="alunni_modifica_enable",
   *    requirements={"id": "\d+", "enable": "true|false"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function modificaEnableAction(EntityManagerInterface $em, $id, $enable) {
    $alunno = $em->getRepository('AppBundle:Alunno')->find($id);
    if ($alunno) {
      // recupera genitori (anche più di uno)
      $genitori = $em->getRepository('AppBundle:Genitore')->findBy(['alunno' => $alunno]);
      // abilita o disabilita
      $alunno->setAbilitato($enable === 'true');
      foreach ($genitori as $gen) {
        $gen->setAbilitato($enable === 'true');
      }
      $em->flush();
      // redirezione
      return $this->redirectToRoute('alunni_modifica');
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Mostra i dati di un alunno e dei genitori
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/modifica/show/{id}", name="alunni_modifica_show", requirements={"id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function modificaShowAction(EntityManagerInterface $em, $id) {
    $alunno = $em->getRepository('AppBundle:Alunno')->find($id);
    if ($alunno) {
      // recupera genitori (anche più di uno)
      $genitori = $em->getRepository('AppBundle:Genitore')->findBy(['alunno' => $alunno]);
      // form
      $form = $this->container->get('form.factory')->createNamedBuilder('alunni_modifica_show', FormType::class, $alunno)
        // dat utente
        ->add('username', TextType::class, array('label' => 'label.username',
          'data' => $alunno->getUsername().', '.implode(', ',
             array_map(function($g) { return $g->getUsername(); }, $genitori)),
          'disabled' => true,
          'required' => false))
        ->add('email', TextType::class, array('label' => 'label.email',
          'data' => $alunno->getEmail().', '.implode(', ',
             array_map(function($g) { return $g->getEmail(); }, $genitori)),
          'disabled' => true,
          'required' => false))
        ->add('ultimoAccesso', TextType::class, array('label' => 'label.ultimo_accesso',
          'data' => ($alunno->getUltimoAccesso() ? $alunno->getUltimoAccesso()->format('d/m/Y H:i:s') :
            $this->get('translator')->trans('label.mai')).', '.
            implode(', ', array_map(function($g) {
              return $g->getUltimoAccesso() ? $g->getUltimoAccesso()->format('d/m/Y H:i:s') :
                $this->get('translator')->trans('label.mai'); }, $genitori)),
          'disabled' => true,
          'required' => false))
        ->add('abilitato', TextType::class, array('label' => 'label.abilitato',
          'data' => $this->get('translator')->trans($alunno->getAbilitato() ? 'label.si' : 'label.no'),
          'disabled' => true,
          'required' => false))
        // dati anagrafici
        ->add('nome', TextType::class, array('label' => 'label.nome',
          'disabled' => true,
          'required' => false))
        ->add('cognome', TextType::class, array('label' => 'label.cognome',
          'disabled' => true,
          'required' => false))
        ->add('sesso', ChoiceType::class, array('label' => 'label.sesso',
          'choices' => array('label.maschile' => 'M', 'label.femminile' => 'F'),
          'expanded' => false,
          'multiple' => false,
          'disabled' => true,
          'required' => false))
        ->add('dataNascita', TextType::class, array('label' => 'label.data_nascita',
          'data' => $alunno->getDataNascita()->format('d/m/Y'),
          'disabled' => true,
          'required' => false))
        ->add('comuneNascita', TextType::class, array('label' => 'label.comune_nascita',
          'disabled' => true,
          'required' => false))
        ->add('codiceFiscale', TextType::class, array('label' => 'label.codice_fiscale',
          'disabled' => true,
          'required' => false))
        ->add('citta', TextType::class, array('label' => 'label.citta',
          'disabled' => true,
          'required' => false))
        ->add('indirizzo', TextType::class, array('label' => 'label.indirizzo',
          'disabled' => true,
          'required' => false))
        ->add('numeriTelefono', TextType::class, array('label' => 'label.numeri_telefono',
          'data' => implode(', ', $alunno->getNumeriTelefono()),
          'disabled' => true,
          'required' => false))
        // dati scolastici
        ->add('bes', ChoiceType::class, array('label' => 'label.bes',
          'choices' => array('label.bes_B' => 'B', 'label.bes_D' => 'D', 'label.bes_H' => 'H', 'label.bes_N' => 'N'),
          'expanded' => false,
          'multiple' => false,
          'disabled' => true,
          'required' => false))
        ->add('rappresentanteClasse', TextType::class, array('label' => 'label.rappresentante_classe',
          'data' => $this->get('translator')->trans($alunno->getRappresentanteClasse() ? 'label.si' : 'label.no'),
          'disabled' => true,
          'required' => false))
        ->add('rappresentanteIstituto', TextType::class, array('label' => 'label.rappresentante_istituto',
          'data' => $this->get('translator')->trans($alunno->getRappresentanteIstituto() ? 'label.si' : 'label.no'),
          'disabled' => true,
          'required' => false))
        ->add('rappresentanteConsulta', TextType::class, array('label' => 'label.rappresentante_consulta',
          'data' => $this->get('translator')->trans($alunno->getRappresentanteConsulta() ? 'label.si' : 'label.no'),
          'disabled' => true,
          'required' => false))
        ->add('autorizzaEntrata', TextType::class, array('label' => 'label.autorizza_entrata',
          'disabled' => true,
          'required' => false))
        ->add('autorizzaUscita', TextType::class, array('label' => 'label.autorizza_uscita',
          'disabled' => true,
          'required' => false))
        ->add('note', TextType::class, array('label' => 'label.note',
          'disabled' => true,
          'required' => false))
        ->add('frequenzaEstero', TextType::class, array('label' => 'label.frequenza_estero',
          'data' => $this->get('translator')->trans($alunno->getFrequenzaEstero() ? 'label.si' : 'label.no'),
          'disabled' => true,
          'required' => false))
        ->add('religione', ChoiceType::class, array('label' => 'label.religione',
          'choices' => array('label.religione_S' => 'S', 'label.religione_U' => 'U', 'label.religione_I' => 'I',
            'label.religione_D' => 'D', 'label.religione_M' => 'M'),
          'expanded' => false,
          'multiple' => false,
          'disabled' => true,
          'required' => false))
        ->add('credito3', TextType::class, array('label' => 'label.credito3',
          'disabled' => true,
          'required' => false))
        ->add('credito4', TextType::class, array('label' => 'label.credito4',
          'disabled' => true,
          'required' => false))
        ->add('classe', TextType::class, array('label' => 'label.classe',
          'data' => $alunno->getClasse() ? $alunno->getClasse()->getAnno().' '.$alunno->getClasse()->getSezione() : '',
          'disabled' => true,
          'required' => false))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['onclick' => "location.href='".$this->generateUrl('alunni_modifica')."'"]))
        ->getForm();
      // mostra la pagina di risposta
      return $this->render('alunni/edit.html.twig', array(
        'pagina_titolo' => 'page.mostra_alunni',
        'form' => $form->createView(),
        'form_title' => 'title.mostra_alunni',
        'form_help' => null,
        'form_success' => null,
        ));
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Gestione del cambio di classe degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/modifica/classe/{id}", name="alunni_modifica_classe", requirements={"id": "\d+"})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function modificaClasseAction(Request $request, EntityManagerInterface $em, $id) {
    $alunno = $em->getRepository('AppBundle:Alunno')->find($id);
    if ($alunno) {
      // form
      $form = $this->container->get('form.factory')->createNamedBuilder('alunni_modifica_classe', FormType::class)
        ->add('alunno', TextType::class, array('label' => 'label.alunno',
          'data' => $alunno,
          'disabled' => true,
          'required' => true))
        ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'class' => 'AppBundle:Classe',
          'choice_label' => function ($obj) {
              return $obj->getAnno().' '.$obj->getSezione();
            },
          'placeholder' => 'label.classe',
          'query_builder' => function (EntityRepository $er) {
              return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC');
            },
          'group_by' => 'sede.citta',
          'required' => false))
        ->add('note', TextType::class, array('label' => 'label.note',
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.submit'))
        ->getForm();
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // crea oggetto
        $cambio = (new CambioClasse())
          ->setAlunno($alunno)
          ->setInizio($form->get('inizio')->getData())
          ->setFine($form->get('fine')->getData())
          ->setClasse($form->get('classe')->getData())
          ->setNote($form->get('note')->getData());
        $em->persist($cambio);
        // validazione
        if ($cambio->getInizio() > $cambio->getFine()) {
          $form->get('inizio')->addError(new FormError($this->get('translator')->trans('exception.date_start_end')));
        }
        $errors = $this->get('validator')->validate($cambio);
        if (count($errors) > 0) {
          $form->addError(new FormError($errors[0]->getMessage()));
        } else {
          // ok
          $em->flush();
          // redirezione
          return $this->redirectToRoute('alunni_classe');
        }
      }
      // mostra pagina di risposta
      return $this->render('alunni/edit.html.twig', array(
        'pagina_titolo' => 'page.cambio_classe',
        'form' => $form->createView(),
        'form_title' => 'title.cambio_classe',
        'form_help' => 'message.required_fields',
        'form_success' => null,
      ));
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Gestione cambio classe
   *
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/classe/", name="alunni_classe")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function classeAction(EntityManagerInterface $em) {
    // lista
    $lista = $em->getRepository('AppBundle:CambioClasse')->createQueryBuilder('cc')
      ->select('cc.id,cc.inizio,cc.fine,cc.note,a.cognome,a.nome,a.dataNascita,cl.anno,cl.sezione')
      ->join('cc.alunno', 'a')
      ->leftJoin('cc.classe', 'cl')
      ->orderBy('a.cognome,a.nome,cc.inizio', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // mostra la pagina di risposta
    return $this->render('alunni/cambio.html.twig', array(
      'pagina_titolo' => 'page.cambio_classe',
      'lista' => $lista,
    ));
  }

  /**
   * Cancella un cambio di classe di un alunno
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID del cambio classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/classe/delete/{id}", name="alunni_classe_delete", requirements={"id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function classeDeleteAction(EntityManagerInterface $em, $id) {
    $cambio = $em->getRepository('AppBundle:CambioClasse')->find($id);
    if ($cambio) {
      // elimina il cambio classe
      $em->remove($cambio);
      $em->flush();
      // redirezione
      return $this->redirectToRoute('alunni_classe');
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Modifica un cambio di classe di un alunno
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID del cambio classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/classe/edit/{id}", name="alunni_classe_edit", requirements={"id": "\d+"})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function classeEditAction(Request $request, EntityManagerInterface $em, $id) {
    $cambio = $em->getRepository('AppBundle:CambioClasse')->find($id);
    if ($cambio) {
      // form
      $form = $this->container->get('form.factory')->createNamedBuilder('alunni_classe_edit', FormType::class, $cambio)
        ->add('alunno', EntityType::class, array('label' => 'label.alunno',
          'class' => 'AppBundle:Alunno',
          'choice_label' => function ($obj) {
              return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')';
            },
          'query_builder' => function (EntityRepository $er) {
              return $er->createQueryBuilder('a')->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC');
            },
          'required' => true))
        ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'class' => 'AppBundle:Classe',
          'choice_label' => function ($obj) {
              return $obj->getAnno().' '.$obj->getSezione();
            },
          'placeholder' => 'label.classe',
          'query_builder' => function (EntityRepository $er) {
              return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC');
            },
          'group_by' => 'sede.citta',
          'required' => false))
        ->add('note', TextType::class, array('label' => 'label.note',
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$this->generateUrl('alunni_classe')."'"]))
        ->getForm();
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
          // ok
          $em->flush();
          // redirezione
          return $this->redirectToRoute('alunni_classe');
      }
      // mostra pagina di risposta
      return $this->render('alunni/edit.html.twig', array(
        'pagina_titolo' => 'page.cambio_classe',
        'form' => $form->createView(),
        'form_title' => 'title.cambio_classe_modifica',
        'form_help' => 'message.required_fields',
        'form_success' => null,
      ));
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Gestisce la generazione della password per gli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $page Numero di pagina per la lista dei alunni
   *
   * @Route("/alunni/password/", name="alunni_password", defaults={"page": 0})
   * @Route("/alunni/password/{page}", name="alunni_password-param", requirements={"page": "\d+"})
   *
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function passwordAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $page) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/alunni_password/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/alunni_password/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/alunni_password/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($page == 0) {
      // pagina non definita: la cerca in sessione
      $page = $session->get('/APP/ROUTE/alunni_password/page', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/alunni_password/page', $page);
    }
    // form di ricerca
    $limit = 30;
    $classi = $em->getRepository('AppBundle:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    $classi[] = -1;
    $form = $this->container->get('form.factory')->createNamedBuilder('alunni_password', FormType::class)
      ->setAction($this->generateUrl('alunni_password'))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return (is_object($obj) ? $obj->getAnno().' '.$obj->getSezione() :
              $this->get('translator')->trans('label.nessuna_classe'));
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return (is_object($obj)  ? $obj->getSede()->getCitta() :
              $this->get('translator')->trans('label.altro'));
          },
        'placeholder' => 'label.classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() :
        ($form->get('classe')->getData() == -1 ? -1 : 0));
      $page = 1;
      $session->set('/APP/ROUTE/alunni_password/nome', $search['nome']);
      $session->set('/APP/ROUTE/alunni_password/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/alunni_password/classe', $search['classe']);
      $session->set('/APP/ROUTE/alunni_password/page', $page);
    }
    // lista alunni
    $paginator = $em->getRepository('AppBundle:Alunno')->findAllEnabled($search, $page, $limit);
    // mostra la pagina di risposta
    return $this->render('alunni/password.html.twig', array(
      'pagina_titolo' => 'page.password',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $paginator,
      'page' => $page,
      'maxPages' => ceil($paginator->count() / $limit),
      'modal_confirm_msg' => 'message.nuova_password_classe',
    ));
  }

  /**
   * Generazione della password degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $idalunno ID dell'utente
   * @param int $idclasse ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/password/create/{idalunno}-{idclasse}", name="alunni_password_create",
   *    requirements={"idalunno": "\d+", "idclasse": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function passwordCreateAction(Request $request, EntityManagerInterface $em,
                                        UserPasswordEncoderInterface $encoder, LogHandler $dblogger,
                                        PdfManager $pdf, $idalunno, $idclasse) {
    if ($idalunno > 0 && ($alunno = $em->getRepository('AppBundle:Alunno')->find($idalunno))) {
      // recupera genitori (anche più di uno)
      $genitori = $em->getRepository('AppBundle:Genitore')->findBy(['alunno' => $alunno]);
      // crea password
      $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
      $password = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
      foreach ($genitori as $gen) {
        $gen->setPasswordNonCifrata($password);
        $pswd = $encoder->encodePassword($gen, $gen->getPasswordNonCifrata());
        $gen->setPassword($pswd);
      }
      // memorizza su db
      $em->flush();
      // log azione
      $dblogger->write($alunno, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
        'Username esecutore' => $this->getUser()->getUsername(),
        'Ruolo esecutore' => $this->getUser()->getRoles()[0],
        'ID esecutore' => $this->getUser()->getId()
        ));
      // crea documento PDF
      $pdf->configure('Istituto di Istruzione Superiore "NOME"',
        'Credenziali di accesso al Registro Elettronico');
      // contenuto in formato HTML
      $html = $this->renderView('pdf/credenziali_alunni.html.twig', array(
        'alunno' => $alunno,
        'username' => $genitori[0]->getUsername(),
        'password' => $password,
        'sesso' => $alunno->getSesso() == 'M' ? 'o' : 'a',
        ));
      $pdf->createFromHtml($html);
      // invia il documento
      return $pdf->send('credenziali_registro.pdf');
    } elseif ($idclasse > 0 && ($classe = $em->getRepository('AppBundle:Classe')->find($idclasse))) {
      // recupera alunni della classe
      $alunni = $em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->where('a.classe=:classe AND a.abilitato=:abilitato')
      ->setParameters(['classe' => $classe, 'abilitato' => 1])
      ->orderBy('a.cognome,a.nome', 'ASC')
      ->getQuery()
      ->getResult();
      if (empty($alunni)) {
        // nessun alunno
        return $this->redirectToRoute('alunni_password');
      } else {
        // alunni presenti
        $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
        // crea documento PDF
        $pdf->configure('Istituto di Istruzione Superiore "NOME"',
          'Credenziali di accesso al Registro Elettronico');
        foreach ($alunni as $alu) {
          // recupera genitori (anche più di uno)
          $genitori = $em->getRepository('AppBundle:Genitore')->findBy(['alunno' => $alu]);
          // crea password
          $password = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
          foreach ($genitori as $gen) {
            $gen->setPasswordNonCifrata($password);
            $pswd = $encoder->encodePassword($gen, $gen->getPasswordNonCifrata());
            $gen->setPassword($pswd);
          }
          // memorizza su db
          $em->flush();
          // log azione
          $dblogger->write($alu, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
            'Username esecutore' => $this->getUser()->getUsername(),
            'Ruolo esecutore' => $this->getUser()->getRoles()[0],
            'ID esecutore' => $this->getUser()->getId()
            ));
          // contenuto in formato HTML
          $html = $this->renderView('pdf/credenziali_alunni.html.twig', array(
            'alunno' => $alu,
            'username' => $genitori[0]->getUsername(),
            'password' => $password,
            'sesso' => $alu->getSesso() == 'M' ? 'o' : 'a',
            ));
          $pdf->createFromHtml($html);
        }
        // invia il documento
        return $pdf->send('credenziali_registro.pdf');
      }
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

}

