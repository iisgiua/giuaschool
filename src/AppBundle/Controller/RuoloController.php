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
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;


/**
 * RuoloController - gestione dell'assegnamento dei ruoli
 */
class RuoloController extends Controller {

  /**
   * Gestione dell'assegnamento dei ruoli
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ruolo/", name="ruolo")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function ruoloAction() {
    return $this->render('ruolo/index.html.twig', array(
      'pagina_titolo' => 'page.ruolo',
    ));
  }

  /**
   * Gestione dell'assegnamento del ruolo di staff
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ruolo/staff/", name="ruolo_staff")
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function staffAction(Request $request, EntityManagerInterface $em) {
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('ruolo_staff', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.docente',
        'class' => 'AppBundle:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')';
          },
        'placeholder' => 'label.choose_option',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d INSTANCE OF AppBundle:Docente AND d.abilitato=1')
              ->orderBy('d.cognome,d.nome', 'ASC');
          },
        'required' => true))
      ->add('sede', EntityType::class, array('label' => 'label.sede',
        'class' => 'AppBundle:Sede',
        'choice_label' => 'citta',
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $docente = $form->get('docente')->getData();
      if ($docente && $docente->getAbilitato()) {
        // ruolo di staff
        $sede = ($form->get('sede')->getData() ? $form->get('sede')->getData()->getId() : null);
        $sql = "UPDATE gs_utente SET modificato=NOW(),ruolo='STA',sede_id=:sede WHERE id=:id";
        $params = array('id' => $docente->getId(), 'sede' => $sede);
        $em->getConnection()->prepare($sql)->execute($params);
        // svuota cache
        $em->clear();
      }
    }
    // lista staff aggiornata
    $staff = $em->getRepository('AppBundle:Staff')->findBy(array(), array('cognome' => 'ASC', 'nome' => 'ASC'));
    // mostra la pagina di risposta
    return $this->render('ruolo/staff.html.twig', array(
      'pagina_titolo' => 'page.staff',
      'staff' => $staff,
      'form' => $form->createView(),
      'form_title' => 'title.staff',
      'form_help' => 'message.required_fields',
      'form_success' => null,
    ));
  }

  /**
   * Gestione della cancellazione del ruolo di staff
   *
   * @param int $id ID dell'utente
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ruolo/staff/delete/{id}", name="ruolo_staff_delete", requirements={"id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function staffDeleteAction($id, EntityManagerInterface $em) {
    $user = $em->getRepository('AppBundle:Staff')->find($id);
    if ($user) {
      // toglie ruolo di staff
      $sql = "UPDATE gs_utente SET modificato=NOW(),ruolo='DOC',sede_id=:sede WHERE id=:id";
      $params = array('id' => $user->getId(), 'sede' => null);
      $em->getConnection()->prepare($sql)->execute($params);
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // redirezione
    return $this->redirectToRoute('ruolo_staff');
  }

  /**
   * Gestione dell'assegnamento del ruolo di coordinatore
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ruolo/coordinatore/", name="ruolo_coordinatore")
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function coordinatoreAction(Request $request, EntityManagerInterface $em) {
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('ruolo_coordinatore', FormType::class)
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
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $classe = $form->get('classe')->getData();
      $docente = $form->get('docente')->getData();
      if ($classe && $docente && $docente->getAbilitato()) {
        // coordinatore
        $classe->setCoordinatore($docente);
        $em->flush();
      }
    }
    // lista aggiornata
    $lista = $em->getRepository('AppBundle:Classe')->createQueryBuilder('c')
      ->select('c.id,c.anno,c.sezione,co.cognome,co.nome,co.username,s.citta')
      ->join('c.coordinatore', 'co')
      ->join('c.sede', 's')
      ->orderBy('c.sede,c.anno,c.sezione', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // mostra la pagina di risposta
    return $this->render('ruolo/coordinatore.html.twig', array(
      'pagina_titolo' => 'page.coordinatore',
      'lista' => $lista,
      'form' => $form->createView(),
      'form_title' => 'title.coordinatore',
      'form_help' => 'message.required_fields',
      'form_success' => null,
    ));
  }

  /**
   * Gestione della cancellazione del ruolo di coordinatore
   *
   * @param int $id ID dell'utente
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ruolo/coordinatore/delete/{id}", name="ruolo_coordinatore_delete", requirements={"id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function coordinatoreDeleteAction($id, EntityManagerInterface $em) {
    $classe = $em->getRepository('AppBundle:Classe')->find($id);
    if ($classe) {
      // toglie ruolo di coordinatore
      $classe->setCoordinatore(null);
      $em->flush();
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // redirezione
    return $this->redirectToRoute('ruolo_coordinatore');
  }

  /**
   * Gestione dell'assegnamento del ruolo di segretario
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ruolo/segretario/", name="ruolo_segretario")
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function segretarioAction(Request $request, EntityManagerInterface $em) {
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('ruolo_segretario', FormType::class)
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
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $classe = $form->get('classe')->getData();
      $docente = $form->get('docente')->getData();
      if ($classe && $docente && $docente->getAbilitato()) {
        // coordinatore
        $classe->setSegretario($docente);
        $em->flush();
      }
    }
    // lista aggiornata
    $lista = $em->getRepository('AppBundle:Classe')->createQueryBuilder('c')
      ->select('c.id,c.anno,c.sezione,se.cognome,se.nome,se.username,s.citta')
      ->join('c.segretario', 'se')
      ->join('c.sede', 's')
      ->orderBy('c.sede,c.anno,c.sezione', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // mostra la pagina di risposta
    return $this->render('ruolo/segretario.html.twig', array(
      'pagina_titolo' => 'page.segretario',
      'lista' => $lista,
      'form' => $form->createView(),
      'form_title' => 'title.segretario',
      'form_help' => 'message.required_fields',
      'form_success' => null,
    ));
  }

  /**
   * Gestione della cancellazione del ruolo di segretario
   *
   * @param int $id ID dell'utente
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ruolo/segretario/delete/{id}", name="ruolo_segretario_delete", requirements={"id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function segretarioDeleteAction($id, EntityManagerInterface $em) {
    $classe = $em->getRepository('AppBundle:Classe')->find($id);
    if ($classe) {
      // toglie ruolo di segretario
      $classe->setSegretario(null);
      $em->flush();
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // redirezione
    return $this->redirectToRoute('ruolo_segretario');
  }

}

