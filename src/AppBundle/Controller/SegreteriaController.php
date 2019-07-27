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


namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Util\SegreteriaUtil;
use AppBundle\Util\PdfManager;


/**
 * SegreteriaController - funzioni per la segreteria
 */
class SegreteriaController extends Controller {

  /**
   * Gestisce la visualizzazione delle assenze
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/segreteria/assenze/{pagina}", name="segreteria_assenze",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @Security("has_role('ROLE_ATA')")
   */
  public function assenzeAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/segreteria_assenze/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/segreteria_assenze/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/segreteria_assenze/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/segreteria_assenze/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/segreteria_assenze/pagina', $pagina);
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // form di ricerca
    $limite = 20;
    // tutte le classi
    $classi = $em->getRepository('AppBundle:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    $form = $this->container->get('form.factory')->createNamedBuilder('segreteria_assenze', FormType::class)
      ->setAction($this->generateUrl('segreteria_assenze'))
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
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
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
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/segreteria_assenze/nome', $search['nome']);
      $session->set('/APP/ROUTE/segreteria_assenze/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/segreteria_assenze/classe', $search['classe']);
      $session->set('/APP/ROUTE/segreteria_assenze/pagina', $pagina);
    }
    // lista alunni
    if ($session->has('/APP/ROUTE/segreteria_assenze/nome')) {
      $lista = $em->getRepository('AppBundle:Alunno')->findAllEnabled($search, $pagina, $limite);
    } else {
      $lista = $em->getRepository('AppBundle:Alunno')->listaVuota($pagina, $limite);
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_ata/assenze.html.twig', array(
      'pagina_titolo' => 'page.segreteria_assenze',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
    ));
  }

  /**
   * Visualizza il riepilogo delle assenze
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SegreteriaUtil $segr Funzioni di utilità per la segreteria
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/segreteria/assenze/mostra/{alunno}", name="segreteria_assenze_mostra",
   *    requirements={"alunno": "\d+"},
   *    methods={"GET"})
   *
   * @Security("has_role('ROLE_ATA')")
   */
  public function assenzeMostraAction(EntityManagerInterface $em, SegreteriaUtil $segr, $alunno) {
    // controlla alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // recupera dati
    $dati = $segr->riepilogoAssenze($alunno);
    // visualizza pagina
    return $this->render('ruolo_ata/assenze_mostra.html.twig', array(
      'pagina_titolo' => 'page.segreteria_assenze',
      'alunno' => $alunno,
      'dati' => $dati,
    ));
  }

  /**
   * Stampa il riepilogo delle assenze
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SegreteriaUtil $segr Funzioni di utilità per la segreteria
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/segreteria/assenze/stampa/{alunno}", name="segreteria_assenze_stampa",
   *    requirements={"alunno": "\d+"},
   *    methods={"GET"})
   *
   * @Security("has_role('ROLE_ATA')")
   */
  public function assenzeStampaAction(EntityManagerInterface $em, SegreteriaUtil $segr, PdfManager $pdf, $alunno) {
    // controlla alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // recupera dati
    $dati = $segr->riepilogoAssenze($alunno);
    // crea documento PDF
    $pdf->configure('Istituto di Istruzione Superiore ""',
      'Prospetto mensile delle assenze');
    // contenuto in formato HTML
    $html = $this->renderView('pdf/segreteria_assenze.html.twig', array(
      'alunno' => $alunno,
      'sesso' => $alunno->getSesso() == 'M' ? 'o' : 'a',
      'dati' => $dati,
      ));
    $pdf->createFromHtml($html);
    // invia il documento
    $nomefile = 'prospetto-assenze.pdf';
    return $pdf->send($nomefile);
  }

  /**
   * Gestisce la visualizzazione degli scrutini
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SegreteriaUtil $segr Funzioni di utilità per la segreteria
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/segreteria/scrutini/{pagina}", name="segreteria_scrutini",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @Security("has_role('ROLE_ATA')")
   */
  public function scrutiniAction(Request $request, EntityManagerInterface $em, SegreteriaUtil $segr,
                                  SessionInterface $session, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/segreteria_scrutini/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/segreteria_scrutini/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/segreteria_scrutini/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/segreteria_scrutini/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/segreteria_scrutini/pagina', $pagina);
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // form di ricerca
    $limite = 20;
    // tutte le classi
    $classi = $em->getRepository('AppBundle:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    $form = $this->container->get('form.factory')->createNamedBuilder('segreteria_scrutini', FormType::class)
      ->setAction($this->generateUrl('segreteria_scrutini'))
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
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
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
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/segreteria_scrutini/nome', $search['nome']);
      $session->set('/APP/ROUTE/segreteria_scrutini/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/segreteria_scrutini/classe', $search['classe']);
      $session->set('/APP/ROUTE/segreteria_scrutini/pagina', $pagina);
    }
    // lista alunni
    if ($session->has('/APP/ROUTE/segreteria_scrutini/nome')) {
      $dati['lista'] = $em->getRepository('AppBundle:Alunno')->findAllEnabled($search, $pagina, $limite);
    } else {
      $dati['lista'] = $em->getRepository('AppBundle:Alunno')->listaVuota($pagina, $limite);
    }
    // legge dati pagelle
    $dati['pagelle'] = $segr->pagelleAlunni($dati['lista']);
    // mostra la pagina di risposta
    return $this->render('ruolo_ata/scrutini.html.twig', array(
      'pagina_titolo' => 'page.segreteria_scrutini',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'dati' => $dati,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
    ));
  }

  /**
   * Visualizza i documenti dello scrutinio per l'alunno indicato
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SegreteriaUtil $segr Funzioni di utilità per la segreteria
   * @param int $alunno Identificativo dell'alunno
   * @param string $periodo Periodo dello scrutinio
   * @param int $scrutinio Identificativo dello scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/segreteria/scrutini/mostra/{alunno}/{periodo}/{scrutinio}", name="segreteria_scrutini_mostra",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|I|1|2", "scrutinio": "\d+", },
   *    methods={"GET"})
   *
   * @Security("has_role('ROLE_ATA')")
   */
  public function scrutiniMostraAction(EntityManagerInterface $em, SegreteriaUtil $segr,
                                        $alunno, $periodo, $scrutinio) {
    // controlla alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla scrutinio
    $adesso = (new \DateTime())->format('Y-m-d H:i:0');
    $scrutinio = $em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.id=:scrutinio AND s.periodo=:periodo AND s.stato=:stato AND s.visibile<=:adesso')
      ->setParameters(['scrutinio' => $scrutinio, 'periodo' => $periodo, 'stato' => 'C', 'adesso' => $adesso])
      ->getQuery()
      ->setMaxResults(1)
      ->getOneOrNullResult();
    if (!$scrutinio) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // recupera dati
    $dati = $segr->scrutinioAlunno($alunno, $scrutinio);
    // visualizza pagina
    return $this->render('ruolo_ata/scrutini_mostra.html.twig', array(
      'pagina_titolo' => 'page.segreteria_scrutini_mostra',
      'alunno' => $alunno,
      'scrutinio' => $scrutinio,
      'dati' => $dati,
    ));
  }

}

