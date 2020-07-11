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
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\RichiestaColloquio;


/**
 * ColloquiController - gestione dei colloqui
 */
class ColloquiController extends AbstractController {

  /**
   * Gestione delle richieste di colloquio
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/colloqui", name="colloqui",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function colloquiAction(EntityManagerInterface $em, SessionInterface $session) {
    // inizializza variabili
    $errore = null;
    $dati = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // controllo fine colloqui
    $fine = \DateTime::createFromFormat('Y-m-d H:i:s', $session->get('/CONFIG/SCUOLA/anno_fine').' 00:00:00');
    $fine->modify('-30 days');    // controllo fine
    $oggi = new \DateTime('today');
    if ($oggi > $fine) {
      // visualizza errore
      $errore = 'exception.colloqui_sospesi';
    } else {
      // legge richieste
      $dati['richieste'] = $em->getRepository('App:RichiestaColloquio')->colloquiDocente($this->getUser());
      $dati['ore'] = $em->getRepository('App:Colloquio')->ore($this->getUser());
    }
    // visualizza pagina
    return $this->render('colloqui/colloqui.html.twig', array(
      'pagina_titolo' => 'page.docenti_colloqui',
      'errore' => $errore,
      'dati' => $dati,
      'settimana' => $settimana,
      'mesi' => $mesi,
    ));
  }

  /**
   * Risponde ad una richiesta di colloquio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RichiestaColloquio $richiesta Richiesta di colloquio da modificare
   * @param string $azione Tipo di modifica da effettuare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/colloqui/edit/{richiesta}/{azione}", name="colloqui_edit",
   *    requirements={"richiesta": "\d+", "azione": "C|N|X"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function colloquiEditAction(Request $request, EntityManagerInterface $em, RichiestaColloquio $richiesta, $azione) {
    // inizializza variabili
    $label = array();
    // controlla richiesta
    $richiesta = $em->getRepository('App:RichiestaColloquio')->find($richiesta);
    if (empty($richiesta)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $colloquio = $richiesta->getColloquio();
    if ($colloquio->getDocente() != $this->getUser()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // info
    $label['docente'] = $colloquio->getDocente()->getCognome().' '.$colloquio->getDocente()->getNome();
    $label['alunno'] = $richiesta->getAlunno()->getCognome().' '.$richiesta->getAlunno()->getNome();
    $label['classe'] = $richiesta->getAlunno()->getClasse()->getAnno().'ª '.$richiesta->getAlunno()->getClasse()->getSezione();
    $label['data'] = $richiesta->getData()->format('d/m/Y');
    // azione
    if ($azione == 'C') {
      // conferma colloquio
      $msg_required = false;
      $stato_disabled = true;
      $msg = '';
      $stato = 'C';
    } elseif ($azione == 'N') {
      // rifiuta colloquio
      $msg_required = true;
      $stato_disabled = true;
      $msg = '';
      $stato = 'N';
    } else {
      // modifica risposta
      $msg_required = true;
      $stato_disabled = false;
      $msg = $richiesta->getMessaggio();
      $stato = $richiesta->getStato();
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('colloqui_edit', FormType::class)
      ->add('stato', ChoiceType::class, array('label' => 'label.stato_colloquio',
        'data' => $stato,
        'choices'  => ['label.stato_colloquio_C' => 'C', 'label.stato_colloquio_N' => 'N'],
        'required' => true,
        'disabled' => $stato_disabled))
      ->add('messaggio', TextType::class, array(
        'data' => $msg,
        'label' => 'label.messaggio_colloquio',
        'trim' => true,
        'required' => $msg_required))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('colloqui')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
        $richiesta
          ->setStato($form->get('stato')->getData())
          ->setMessaggio($form->get('messaggio')->getData());
      // ok: memorizza dati
      $em->flush();
      // redirezione
      return $this->redirectToRoute('colloqui');
    }
    // mostra la pagina di risposta
    return $this->render('colloqui/colloqui_edit.html.twig', array(
      'pagina_titolo' => 'page.colloqui_edit',
      'form' => $form->createView(),
      'form_title' => 'title.risposta_colloqui',
      'label' => $label,
    ));
  }

}
