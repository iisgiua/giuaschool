<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Configurazione;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use App\Util\ArchiviazioneUtil;
use App\Util\ConfigLoader;


/**
 * ProcedureController - procedure di utilità
 */
class ProcedureController extends AbstractController {

  /**
   * Procedure di utilità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/", name="procedure",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function procedureAction() {
    return $this->render('procedure/index.html.twig', array(
      'pagina_titolo' => 'page.procedure',
    ));
  }

  /**
   * Cambia la password di un utente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ValidatorInterface $validator Gestore della validazione dei dati
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/password/", name="procedure_password",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function passwordAction(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder,
                                  TranslatorInterface $trans, ValidatorInterface $validator, LogHandler $dblogger) {
    // form
    $success = null;
    $form = $this->container->get('form.factory')->createNamedBuilder('procedure_password', FormType::class)
      ->add('username', TextType::class, array('label' => 'label.username', 'required' => true))
      ->add('password', RepeatedType::class, array(
        'type' => PasswordType::class,
        'invalid_message' => 'password.nomatch',
        'first_options' => array('label' => 'label.password'),
        'second_options' => array('label' => 'label.password2'),
        'required' => true
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $em->getRepository('App:Utente')->findOneByUsername($username);
      if (!$user || !$user->getAbilitato()) {
        // errore, utente non esiste o non abilitato
        $form->addError(new FormError($trans->trans('exception.invalid_user')));
      } else {
        // validazione password
        $user->setPasswordNonCifrata($form->get('password')->getData());
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
          $form->addError(new FormError($errors[0]->getMessage()));
        } else {
          // codifica password
          $password = $encoder->encodePassword($user, $user->getPasswordNonCifrata());
          $user->setPassword($password);
          // memorizza password
          $em->flush();
          $success = 'message.update_ok';
          // log azione
          $dblogger->write($user, $request->getClientIp(), 'SICUREZZA', 'Cambio Password da Amministrazione', __METHOD__, array(
            'Username esecutore' => $this->getUser()->getUsername(),
            'Ruolo esecutore' => $this->getUser()->getRoles()[0],
            'ID esecutore' => $this->getUser()->getId()
            ));
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('procedure/password.html.twig', array(
      'pagina_titolo' => 'page.password',
      'form' => $form->createView(),
      'form_title' => 'title.password',
      'form_help' => 'message.required_fields',
      'form_success' => $success,
      ));
  }

  /**
   * Impersona un altro utente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/alias/", name="procedure_alias",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function aliasAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                              TranslatorInterface $trans, ConfigLoader $config, LogHandler $dblogger) {
    // form per l'input dell'alias
    $form = $this->container->get('form.factory')->createNamedBuilder('procedure_alias', FormType::class)
      ->add('username', TextType::class, array('label' => 'label.username', 'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $em->getRepository('App:Utente')->findOneByUsername($username);
      if (!$user || !$user->getAbilitato()) {
        // errore, utente non esiste o non abilitato
        $form->addError(new FormError($trans->trans('exception.invalid_user')));
      } else {
        // memorizza dati in sessione
        $session->set('/APP/UTENTE/tipo_accesso_reale', $session->get('/APP/UTENTE/tipo_accesso'));
        $session->set('/APP/UTENTE/ultimo_accesso_reale', $session->get('/APP/UTENTE/ultimo_accesso'));
        $session->set('/APP/UTENTE/username_reale', $this->getUser()->getUsername());
        $session->set('/APP/UTENTE/ruolo_reale', $this->getUser()->getRoles()[0]);
        $session->set('/APP/UTENTE/id_reale', $this->getUser()->getId());
        $session->set('/APP/UTENTE/ultimo_accesso',
          ($user->getUltimoAccesso() ? $user->getUltimoAccesso()->format('d/m/Y H:i:s') : null));
        $session->set('/APP/UTENTE/tipo_accesso', 'alias');
        // cancella altri dati di sessione
        $session->remove('/APP/ROUTE');
        $session->remove('/APP/DOCENTE');
        // log azione
        $dblogger->write($user, $request->getClientIp(), 'ACCESSO', 'Alias', __METHOD__, array(
          'Username' => $user->getUsername(),
          'Ruolo' => $user->getRoles()[0],
          'Username reale' => $this->getUser()->getUsername(),
          'Ruolo reale' => $this->getUser()->getRoles()[0],
          'ID reale' => $this->getUser()->getId()
          ));
        // impersona l'alias e fa il redirect alla home
        return $this->redirectToRoute('login_home', array('reload' => 'yes', '_alias' => $username));
      }
    }
    // mostra la pagina di risposta
    return $this->render('procedure/alias.html.twig', array(
      'pagina_titolo' => 'page.alias',
      'form' => $form->createView(),
      'form_title' => 'title.alias',
      'form_help' => 'message.required_fields',
      'form_success' => null,
      ));
  }

  /**
   * Disconnette l'alias in uso e ritorna all'utente iniziale
   *
   * @param Request $request Pagina richiesta
   * @param SessionInterface $session Gestore delle sessioni
   * @param ConfigLoader $config Gestore della configurazione su database
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/alias/exit", name="procedure_alias_exit",
   *    methods={"GET"})
   */
  public function aliasExitAction(Request $request, SessionInterface $session, ConfigLoader $config,
                                  LogHandler $dblogger) {
    // log azione
    $dblogger->write($this->getUser(), $request->getClientIp(), 'ACCESSO', 'Alias Exit', __METHOD__, array(
      'Username' => $this->getUser()->getUsername(),
      'Ruolo' => $this->getUser()->getRoles()[0],
      'Username reale' => $session->get('/APP/UTENTE/username_reale'),
      'Ruolo reale' => $session->get('/APP/UTENTE/ruolo_reale'),
      'ID reale' => $session->get('/APP/UTENTE/id_reale')
      ));
    // ricarica dati in sessione
    $session->set('/APP/UTENTE/ultimo_accesso', $session->get('/APP/UTENTE/ultimo_accesso_reale'));
    $session->set('/APP/UTENTE/tipo_accesso', $session->get('/APP/UTENTE/tipo_accesso_reale'));
    $session->remove('/APP/UTENTE/tipo_accesso_reale');
    $session->remove('/APP/UTENTE/ultimo_accesso_reale');
    $session->remove('/APP/UTENTE/username_reale');
    $session->remove('/APP/UTENTE/ruolo_reale');
    $session->remove('/APP/UTENTE/id_reale');
    $session->remove('/APP/ROUTE');
    $session->remove('/APP/DOCENTE');
    // disconnette l'alias in uso e redirect alla home
    return $this->redirectToRoute('login_home', array('reload' => 'yes', '_alias' => '_exit'));
  }

  /**
   * Ricalcola le ore di assenza per un dato periodo
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/ricalcola", name="procedure_ricalcola",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function ricalcolaAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg) {
    // form
    $success = null;
    $form = $this->container->get('form.factory')->createNamedBuilder('procedure_ricalcola', FormType::class)
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
    ->add('submit', SubmitType::class, array('label' => 'label.submit'))
    ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // cancella assenze in intervallo di date (tutte le classi e alunni)
      $em->getConnection()
        ->prepare('DELETE FROM gs_assenza_lezione WHERE lezione_id IN (SELECT id FROM gs_lezione WHERE data BETWEEN :data1 AND :data2)')
        ->execute(['data1' => $form->get('inizio')->getData()->format('Y-m-d'),
          'data2' => $form->get('fine')->getData()->format('Y-m-d')]);
      // legge lezioni
      $lezioni = $em->getRepository('App:Lezione')->createQueryBuilder('l')
        ->where('l.data BETWEEN :data1 AND :data2')
        ->setParameters(['data1' => $form->get('inizio')->getData()->format('Y-m-d'),
          'data2' => $form->get('fine')->getData()->format('Y-m-d')])
        ->getQuery()
        ->getResult();
      // ricalcola ore di ogni lezione
      foreach ($lezioni as $l) {
        $reg->ricalcolaOreLezione($l->getData(), $l);
      }
      // ok
      $success = 'message.update_ok';
    }
    return $this->render('procedure/ricalcola.html.twig', array(
      'pagina_titolo' => 'page.ricalcola',
      'form' => $form->createView(),
      'form_title' => 'title.ricalcola',
      'form_help' => 'message.required_fields',
      'form_success' => $success,
      ));
  }

  /**
   * Gestione della modalità manutenzione del registro
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/manutenzione/", name="procedure_manutenzione",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function manutenzioneAction(Request $request, EntityManagerInterface $em) {
    $dati = null;
    // legge manutenzione
    $param_inizio = $em->getRepository('App:Configurazione')->findOneByParametro('manutenzione_inizio');
    $param_fine = $em->getRepository('App:Configurazione')->findOneByParametro('manutenzione_fine');
    if (empty($param_inizio->getValore())) {
      // non è impostata una manutenzione
      $manutenzione = false;
      $inizio = new \DateTime();
      $inizio->modify('+'.(10 - $inizio->format('i') % 10).' minutes');
    } else {
      // è già impostata una manutenzione
      $manutenzione = true;
      $inizio = \DateTime::createFromFormat('Y-m-d H:i', $param_inizio->getValore());
    }
    if (empty($param_fine->getValore())) {
      // non è impostata una fine per la manutenzione
      $fine = (clone $inizio)->modify('+30 minutes');
    } else {
      // è già impostata una fine per la manutenzione
      $fine = \DateTime::createFromFormat('Y-m-d H:i', $param_fine->getValore());
    }
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('procedure_manutenzione', FormType::class)
      ->add('data_inizio', DateType::class, array('label' => 'label.data_inizio',
        'data' => $inizio,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('ora_inizio', TimeType::class, array('label' => 'label.ora_inizio',
        'data' => $inizio,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true))
      ->add('data_fine', DateType::class, array('label' => 'label.data_fine',
        'data' => $fine,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('ora_fine', TimeType::class, array('label' => 'label.ora_fine',
        'data' => $fine,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']));
    if ($manutenzione) {
      $form = $form
        ->add('delete', SubmitType::class, array('label' => 'label.delete',
          'attr' => ['widget' => 'gs-button-inline', 'class' => 'btn-danger']));
    }
    $form = $form
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('procedure')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      if ($manutenzione && $form->get('delete')->isClicked()) {
        // cancella manutenzione
        $param_inizio->setValore('');
        $param_fine->setValore('');
      } else {
        // imposta manutenzione
        $param_inizio->setValore($form->get('data_inizio')->getData()->format('Y-m-d').' '.
          $form->get('ora_inizio')->getData()->format('H:i'));
        $param_fine->setValore($form->get('data_fine')->getData()->format('Y-m-d').' '.
          $form->get('ora_fine')->getData()->format('H:i'));
        if ($param_inizio->getValore() > $param_fine->getValore()) {
          // inverte l'ordine
          $temp = $param_inizio->getValore();
          $param_inizio->setValore($param_fine->getValore());
          $param_fine->setValore($temp);
        }
      }
      // ok: memorizza dati
      $em->flush();
      // redirezione
      return $this->redirectToRoute('procedure');
    }
    // mostra la pagina di risposta
    return $this->render('procedure/manutenzione.html.twig', array(
      'pagina_titolo' => 'page.manutenzione',
      'form' => $form->createView(),
      'form_title' => 'title.manutenzione',
      'form_help' => null,
      'form_success' => null,
      ));
  }

  /**
   * Gestione dell'archiviazione dei registri in PDF
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ArchiviazioneUtil $arch Funzioni di utilità per l'archiviazione
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/archiviazione/", name="procedure_archiviazione",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function archiviazioneAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans, ArchiviazioneUtil $arch) {
    $lista_docente = $em->getRepository('App:Docente')->createQueryBuilder('d')
      ->join('App:Cattedra', 'c', 'WITH', 'c.docente=d.id')
      ->join('c.materia', 'm')
      ->where('m.tipo IN (:tipi)')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['tipi' => ['N', 'R']])
      ->getQuery()
      ->getResult();
    $lista_sostegno = $em->getRepository('App:Docente')->createQueryBuilder('d')
      ->join('App:Cattedra', 'c', 'WITH', 'c.docente=d.id')
      ->join('c.materia', 'm')
      ->where('m.tipo=:tipo')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['tipo' => 'S'])
      ->getQuery()
      ->getResult();
    $lista_classe = $em->getRepository('App:Classe')->createQueryBuilder('c')
      ->orderBy('c.anno,c.sezione', 'ASC')
      ->getQuery()
      ->getResult();
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('procedure_archiviazione', FormType::class)
      ->add('docente', ChoiceType::class, array('label' => 'label.registro_docente',
        'choices' => array_merge(['label.tutti_docenti' => -1], $lista_docente),
        'choice_label' => function ($obj, $val) use ($trans) {
            return (is_object($obj) ? $obj->getCognome().' '.$obj->getNome() :
              $trans->trans('label.tutti_docenti'));
          },
        'choice_value' => function ($obj) {
            return (is_object($obj) ? $obj->getId() : $obj);
          },
        'placeholder' => 'label.nessuno',
        'choice_translation_domain' => false,
        'choice_attr' => function($obj) {
            return (is_object($obj) ? ['class' => 'gs-no-placeholder'] : []);
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('sostegno', ChoiceType::class, array('label' => 'label.registro_sostegno',
        'choices' => array_merge(['label.tutti_docenti' => -1], $lista_sostegno),
        'choice_label' => function ($obj, $val) use ($trans) {
            return (is_object($obj) ? $obj->getCognome().' '.$obj->getNome() :
              $trans->trans('label.tutti_docenti'));
          },
        'choice_value' => function ($obj) {
            return (is_object($obj) ? $obj->getId() : $obj);
          },
        'placeholder' => 'label.nessuno',
        'choice_translation_domain' => false,
        'choice_attr' => function($obj) {
            return (is_object($obj) ? ['class' => 'gs-no-placeholder'] : []);
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.registro_classe',
        'choices' => array_merge(['label.tutte_classi' => -1], $lista_classe),
        'choice_label' => function ($obj, $val) use ($trans) {
            return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() :
              $trans->trans('label.tutte_classi'));
          },
        'choice_value' => function ($obj) {
            return (is_object($obj) ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return (is_object($obj) ? $obj->getSede()->getCitta() : null);
          },
        'placeholder' => 'label.nessuno',
        'choice_translation_domain' => false,
        'choice_attr' => function($obj) {
            return (is_object($obj) ? ['class' => 'gs-no-placeholder'] : []);
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('scrutinio', ChoiceType::class, array('label' => 'label.documenti_scrutinio',
        'choices' => array_merge(['label.tutte_classi' => -1], $lista_classe),
        'choice_label' => function ($obj, $val) use ($trans) {
            return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() :
              $trans->trans('label.tutte_classi'));
          },
        'choice_value' => function ($obj) {
            return (is_object($obj) ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return (is_object($obj) ? $obj->getSede()->getCitta() : null);
          },
        'placeholder' => 'label.nessuno',
        'choice_translation_domain' => false,
        'choice_attr' => function($obj) {
            return (is_object($obj) ? ['class' => 'gs-no-placeholder'] : []);
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('procedure')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $docente = $form->get('docente')->getData();
      $sostegno = $form->get('sostegno')->getData();
      $classe = $form->get('classe')->getData();
      $scrutinio = $form->get('scrutinio')->getData();
      // assicura che script non sia interrotto
      ini_set('max_execution_time', 0);
      // registro docenti
      if (is_object($docente)) {
        // crea registro
        $arch->registroDocente($docente);
      } elseif ($docente === -1) {
        // crea tutti i registri
        $arch->tuttiRegistriDocente($lista_docente);
      }
      // registro sostegno
      if (is_object($sostegno)) {
        // crea registro
        $arch->registroSostegno($sostegno);
      } elseif ($sostegno === -1) {
        // crea tutti i registri
        $arch->tuttiRegistriSostegno($lista_sostegno);
      }
      // registro classe
      if (is_object($classe)) {
        // crea registro
        $arch->registroClasse($classe);
      } elseif ($classe === -1) {
        // crea tutti i registri
        $arch->tuttiRegistriClasse($lista_classe);
      }
      // documenti scrutinio
      if (is_object($scrutinio)) {
        // crea documenti per la classe
        $arch->scrutinioClasse($scrutinio);
      } elseif ($scrutinio === -1) {
        // crea documenti per tutte le classi
        $arch->tuttiScrutiniClasse($lista_classe);
      }
    }
    // mostra la pagina di risposta
    return $this->render('procedure/archiviazione.html.twig', array(
      'pagina_titolo' => 'page.archiviazione',
      'form' => $form->createView(),
      'form_title' => 'title.archiviazione',
      'form_help' => null,
      'form_success' => null,
      ));
  }

}
