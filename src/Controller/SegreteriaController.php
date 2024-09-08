<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Form\AlunnoGenitoreType;
use App\Util\PdfManager;
use App\Util\SegreteriaUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * SegreteriaController - funzioni per la segreteria
 *
 * @author Antonello Dessì
 */
class SegreteriaController extends BaseController {

  /**
   * Gestisce la visualizzazione delle assenze
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/segreteria/assenze/{pagina}', name: 'segreteria_assenze', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_ATA')]
  public function assenze(Request $request, int $pagina): Response {
    // recupera criteri dalla sessione
    $search = [];
    $search['classe'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_assenze/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository(\App\Entity\Classe::class)->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_assenze/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_assenze/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_assenze/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_assenze/pagina', $pagina);
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // form di ricerca
    $limite = 20;
    // tutte le classi
    $opzioniClassi = $this->em->getRepository(\App\Entity\Classe::class)->opzioni(null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('segreteria_assenze', FormType::class)
      ->setAction($this->generateUrl('segreteria_assenze'))
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('cognome', TextType::class, ['label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim((string) $form->get('cognome')->getData());
      $search['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_assenze/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_assenze/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_assenze/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_assenze/pagina', $pagina);
    }
    // lista alunni
    $lista = $this->em->getRepository(\App\Entity\Alunno::class)->findAllEnabled($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_ata/assenze.html.twig', [
      'pagina_titolo' => 'page.segreteria_assenze',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite)]);
  }

  /**
   * Visualizza il riepilogo delle assenze
   *
   * @param SegreteriaUtil $segr Funzioni di utilità per la segreteria
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/segreteria/assenze/mostra/{alunno}', name: 'segreteria_assenze_mostra', requirements: ['alunno' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_ATA')]
  public function assenzeMostra(SegreteriaUtil $segr, int $alunno): Response {
    // controlla alunno
    $alunno = $this->em->getRepository(\App\Entity\Alunno::class)->findOneBy(['id' => $alunno, 'abilitato' => 1]);
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
    return $this->render('ruolo_ata/assenze_mostra.html.twig', [
      'pagina_titolo' => 'page.segreteria_assenze',
      'alunno' => $alunno,
      'dati' => $dati]);
  }

  /**
   * Stampa il riepilogo delle assenze
   *
   * @param SegreteriaUtil $segr Funzioni di utilità per la segreteria
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/segreteria/assenze/stampa/{alunno}', name: 'segreteria_assenze_stampa', requirements: ['alunno' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_ATA')]
  public function assenzeStampa(SegreteriaUtil $segr, PdfManager $pdf, int $alunno) {
    // controlla alunno
    $alunno = $this->em->getRepository(\App\Entity\Alunno::class)->findOneBy(['id' => $alunno, 'abilitato' => 1]);
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
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      'Prospetto mensile delle assenze');
    // contenuto in formato HTML
    $html = $this->renderView('pdf/segreteria_assenze.html.twig', [
      'alunno' => $alunno,
      'sesso' => $alunno->getSesso() == 'M' ? 'o' : 'a',
      'dati' => $dati]);
    $pdf->createFromHtml($html);
    // invia il documento
    $nomefile = 'prospetto-assenze.pdf';
    return $pdf->send($nomefile);
  }

  /**
   * Gestisce la visualizzazione degli scrutini
   *
   * @param Request $request Pagina richiesta
   * @param SegreteriaUtil $segr Funzioni di utilità per la segreteria
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/segreteria/scrutini/{pagina}', name: 'segreteria_scrutini', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_ATA')]
  public function scrutini(Request $request, SegreteriaUtil $segr, int $pagina): Response {
    // recupera criteri dalla sessione
    $search = [];
    $search['classe'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_scrutini/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository(\App\Entity\Classe::class)->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_scrutini/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_scrutini/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_scrutini/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_scrutini/pagina', $pagina);
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // form di ricerca
    $limite = 20;
    // tutte le classi
    $opzioniClassi = $this->em->getRepository(\App\Entity\Classe::class)->opzioni(null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('segreteria_scrutini', FormType::class)
      ->setAction($this->generateUrl('segreteria_scrutini'))
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('cognome', TextType::class, ['label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim((string) $form->get('cognome')->getData());
      $search['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_scrutini/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_scrutini/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_scrutini/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_scrutini/pagina', $pagina);
    }
    // lista alunni
    $dati['lista'] = $this->em->getRepository(\App\Entity\Alunno::class)->findAllEnabled($search, $pagina, $limite);
    // legge dati pagelle
    $dati['pagelle'] = $segr->pagelleAlunni($dati['lista']);
    // mostra la pagina di risposta
    return $this->render('ruolo_ata/scrutini.html.twig', [
      'pagina_titolo' => 'page.segreteria_scrutini',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'dati' => $dati,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite)]);
  }

  /**
   * Visualizza i documenti dello scrutinio per l'alunno indicato
   *
   * @param SegreteriaUtil $segr Funzioni di utilità per la segreteria
   * @param int $alunno Identificativo dell'alunno
   * @param string $periodo Periodo dello scrutinio
   * @param int $scrutinio Identificativo dello scrutinio
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/segreteria/scrutini/mostra/{alunno}/{periodo}/{scrutinio}', name: 'segreteria_scrutini_mostra', requirements: ['alunno' => '\d+', 'periodo' => 'A|P|S|F|G|R|X', 'scrutinio' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_ATA')]
  public function scrutiniMostra(SegreteriaUtil $segr, int $alunno, string $periodo,
                                 int $scrutinio): Response {
    // controlla alunno
    $alunno = $this->em->getRepository(\App\Entity\Alunno::class)->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla scrutinio
    if ($periodo == 'A') {
      // dati storico
      $scrutinio = $this->em->getRepository(\App\Entity\StoricoEsito::class)->findOneBy(['id' => $scrutinio,
        'alunno' => $alunno]);
    } else {
      // dati scrutinio
      $scrutinio = $this->em->getRepository(\App\Entity\Scrutinio::class)->findOneBy(['id' => $scrutinio,
        'periodo' => $periodo, 'stato' => 'C']);
    }
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
    if ($periodo == 'A') {
      // precedente A.S.
      $dati = $segr->scrutinioPrecedenteAlunno($alunno, $scrutinio);
    } else {
      // periodo in corso d'anno
      $dati = $segr->scrutinioAlunno($alunno, $scrutinio);
    }
    // visualizza pagina
    return $this->render('ruolo_ata/scrutini_mostra.html.twig', [
      'pagina_titolo' => 'page.segreteria_scrutini_mostra',
      'alunno' => $alunno,
      'scrutinio' => $scrutinio,
      'periodo' => $periodo,
      'dati' => $dati]);
  }

  /**
   * Gestisce le modifiche agli utenti genitori
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/segreteria/genitori/{pagina}', name: 'segreteria_genitori', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_ATA')]
  public function genitori(Request $request, int $pagina): Response {
    // recupera criteri dalla sessione
    $search = [];
    $search['classe'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_genitori/classe');
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_genitori/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_genitori/nome', '');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository(\App\Entity\Classe::class)->find($search['classe']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/segreteria_genitori/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_genitori/pagina', $pagina);
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // form di ricerca
    $opzioniClassi = $this->em->getRepository(\App\Entity\Classe::class)->opzioni(null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('segreteria_genitori', FormType::class)
      ->setAction($this->generateUrl('segreteria_genitori'))
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('cognome', TextType::class, ['label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim((string) $form->get('cognome')->getData());
      $search['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_genitori/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_genitori/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_genitori/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/segreteria_genitori/pagina', $pagina);
    }
    // lista alunni
    $search['abilitato'] = 1;
    $lista = $this->em->getRepository(\App\Entity\Alunno::class)->cerca($search, $pagina);
    $lista['genitori'] = $this->em->getRepository(\App\Entity\Genitore::class)->datiGenitoriPaginator($lista['lista']);
    // mostra la pagina di risposta
    return $this->render('ruolo_ata/genitori.html.twig', [
      'pagina_titolo' => 'page.segreteria_genitori',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina]);
  }

  /**
   * Visualizza i documenti dello scrutinio per l'alunno indicato
   *
   * @param Request $request Pagina richiesta
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/segreteria/genitori/edit/{alunno}', name: 'segreteria_genitori_edit', requirements: ['alunno' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_ATA')]
  public function genitoriEdit(Request $request, int $alunno): Response {
    // controlla alunno
    $alunno = $this->em->getRepository(\App\Entity\Alunno::class)->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge genitori nell'ordine corretto
    $username = substr((string) $alunno->getUsername(), 0, -2).'f'.substr((string) $alunno->getUsername(), -1);
    if ($alunno->getGenitori()[0]->getUsername() == $username) {
      $genitore1 = $alunno->getGenitori()[0];
      $genitore2 = $alunno->getGenitori()[1] ?? null;
    } else {
      $genitore1 = $alunno->getGenitori()[1];
      $genitore2 = $alunno->getGenitori()[0];
    }
    // form
    $form = $this->createForm(AlunnoGenitoreType::class, null, [
      'return_url' => $this->generateUrl('segreteria_genitori'), 'form_mode' => 'segreteria',
      'values' => [$genitore1, $genitore2]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
      // redirezione
      return $this->redirectToRoute('segreteria_genitori');
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_ata/genitori_edit.html.twig', [
      'pagina_titolo' => 'page.segreteria_genitori',
      'form' => $form->createView(),
      'form_title' => 'title.segreteria_genitori',
      'alunno' => $alunno]);
  }

}
