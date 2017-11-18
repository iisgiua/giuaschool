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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use AppBundle\Entity\Genitore;
use AppBundle\Util\LogHandler;


/**
 * UtentiController - gestione utenti generici
 */
class UtentiController extends Controller {

  /**
   * Modifica del profilo dell'utente connesso
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $action Nome del form utilizzato
   *
   * @return Response Pagina di risposta
   *
   * @Route("/utenti/profilo/", name="utenti_profilo", defaults={"action": "email"})
   * @Route("/utenti/profilo/{action}", name="utenti_profilo-param",
   *    requirements={"action": "email|password"})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_UTENTE')")
   */
  public function profiloAction(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder,
                                 LogHandler $dblogger, $action) {
    // form email
    $success1 = null;
    $form1 = $this->container->get('form.factory')->createNamedBuilder('utenti_email', FormType::class)
      ->setAction($this->generateUrl('utenti_profilo-param', ['action' => 'email']))
      ->add('email', TextType::class, array('label' => 'label.email',
        'data' => substr($this->getUser()->getEmail(), -6) == '.local' ? '' : $this->getUser()->getEmail(),
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('home')."'"]))
      ->getForm();
    $form1->handleRequest($request);
    if ($form1->isSubmitted() && $form1->isValid()) {
      // validazione
      $vecchia_email = $this->getUser()->getEmail();
      $this->getUser()->setEmail($form1->get('email')->getData());
      $errors = $this->get('validator')->validate($this->getUser());
      if (count($errors) > 0) {
        $form1->addError(new FormError($errors[0]->getMessage()));
      } else {
        // memorizza modifica
        $em->flush();
        $success1 = 'message.update_ok';
        // log azione
        $dblogger->write($this->getUser(), $request->getClientIp(), 'SICUREZZA', 'Cambio Email', __METHOD__, array(
          'Precedente email' => $vecchia_email
          ));
      }
    }
    // form password
    $success2 = null;
    $form2 = $this->container->get('form.factory')->createNamedBuilder('utenti_password', FormType::class)
      ->setAction($this->generateUrl('utenti_profilo-param', ['action' => 'password']))
      ->add('current_password', PasswordType::class, array('label' => 'label.current_password',
        'required' => true))
      ->add('password', RepeatedType::class, array(
        'type' => PasswordType::class,
        'invalid_message' => 'password.nomatch',
        'first_options' => array('label' => 'label.new_password'),
        'second_options' => array('label' => 'label.new_password2'),
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('home')."'"]))
      ->getForm();
    $form2->handleRequest($request);
    if ($this->getUser() instanceof Genitore && $form2->isSubmitted() && $form2->isValid()) {
      // controllo password
      if (!$encoder->isPasswordValid($this->getUser(), $form2->get('current_password')->getData())) {
        // vecchia password errata
        $form2->get('current_password')->addError(
          new FormError($this->get('translator')->trans('password.wrong', [], 'validators')));
      } else {
        // validazione nuova password
        $this->getUser()->setPasswordNonCifrata($form2->get('password')->getData());
        $errors = $this->get('validator')->validate($this->getUser());
        if (count($errors) > 0) {
          $form2->get('password')['first']->addError(new FormError($errors[0]->getMessage()));
        } else {
          // codifica password
          $password = $encoder->encodePassword($this->getUser(), $this->getUser()->getPasswordNonCifrata());
          $this->getUser()->setPassword($password);
          // memorizza password
          $em->flush();
          $success2 = 'message.update_ok';
          // log azione
          $dblogger->write($this->getUser(), $request->getClientIp(), 'SICUREZZA', 'Cambio Password', __METHOD__, array(
            ));
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('utenti/profilo.html.twig', array(
      'pagina_titolo' => 'page.modifica_profilo',
      'form1' => $form1->createView(),
      'form1_title' => 'title.modifica_email',
      'form1_help' => null,
      'form1_success' => $success1,
      'form2' => $form2->createView(),
      'form2_title' => 'title.modifica_password',
      'form2_help' => null,
      'form2_success' => $success2,
    ));
  }

}

