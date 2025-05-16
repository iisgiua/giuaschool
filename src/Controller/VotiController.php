<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Lezione;
use App\Entity\Materia;
use App\Entity\Valutazione;
use App\Form\MessageType;
use App\Form\VotoClasseType;
use App\Util\GenitoriUtil;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\RegistroUtil;
use App\Util\StaffUtil;
use DateTime;
use IntlDateFormatter;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * VotiController - gestione dei voti
 *
 * @author Antonello Dessì
 */
class VotiController extends BaseController {

  /**
   * Quadro dei voti
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra (nullo se sostituzione)
   * @param int $classe Identificativo della classe
   * @param int $periodo Periodo relativo allo scrutinio
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/voti/quadro/{cattedra}/{classe}/{periodo}', name: 'lezioni_voti_quadro', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'periodo' => '1|2|3|0'], defaults: ['cattedra' => 0, 'classe' => 0, 'periodo' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function voti(Request $request, RegistroUtil $reg, int $cattedra, int $classe, int $periodo): Response {
    // inizializza variabili
    $dati = [];
    $dati['alunni'] = [];
    $info = null;
    $azione_edit = false;
    $lista_periodi = null;
    // parametri cattedra/classe/periodo
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    }
    // controllo cattedra/sostituzione
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      if ($cattedra->getMateria()->getTipo() == 'S') {
        // cattedra di sostegno: redirezione
        return $this->redirectToRoute('lezioni_voti_quadro_sostegno', ['cattedra' => $cattedra->getId()]);
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
      $info['religioneTipo'] = ($cattedra->getMateria()->getTipo() == 'R' and $cattedra->getTipo() == 'A') ? 'A' :
        ($cattedra->getMateria()->getTipo() == 'R' ? 'S' : '');
      $info['alunno'] = $cattedra->getAlunno();
      // memorizza parametri in sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra->getId());
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe->getId());
    } elseif ($classe > 0) {
      // sostituzione
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $this->em->getRepository(Materia::class)->findOneByTipo('U');
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
      // informazioni necessarie
      $cattedra = null;
      $info['materia'] = $materia->getNomeBreve();
      $info['religione'] = false;
      $info['religioneTipo'] = '';
      $info['alunno'] = null;
    }
    if ($cattedra) {
      // periodo
      $lista_periodi = $reg->infoPeriodi();
      // seleziona periodo se non indicato
      if ($periodo == 0) {
        // seleziona periodo in base alla data
        if ($this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione')) {
          // recupera data da sessione
          $data = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
        } else {
          // imposta data odierna
          $data = new DateTime();
        }
        $periodo = $reg->periodo($data);
        if ($periodo) {
          $periodo = $periodo['periodo'];
        }
      }
      if ($periodo) {
        // dati periodo
        $inizio = DateTime::createFromFormat('Y-m-d', $lista_periodi[$periodo]['inizio']);
        $fine = DateTime::createFromFormat('Y-m-d', $lista_periodi[$periodo]['fine']);
        // controlla permessi
        if ($reg->azioneVoti($inizio, $this->getUser(), $classe, $cattedra->getMateria(), null)) {
          // edit permesso
          $azione_edit = true;
        }
        // legge voti
        $dati = $reg->quadroVoti($inizio, $fine, $this->getUser(), $cattedra);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/voti_quadro.html.twig', [
      'pagina_titolo' => 'page.lezioni_voti',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
      'edit' => $azione_edit,
      'lista_periodi' => $lista_periodi,
      'periodo' => $periodo]);
  }

  /**
   * Gestione dei voti per le prove di classe
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra
   * @param string $tipo Tipo della valutazione (S,O,P)
   * @param string $data Data del giorno (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/voti/classe/{cattedra}/{tipo}/{data}', name: 'lezioni_voti_classe', requirements: ['cattedra' => '\d+', 'tipo' => 'S|O|P', 'data' => '\d\d\d\d-\d\d-\d\d\.\d+'], defaults: ['data' => '0000-00-00.0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function votiClasse(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                             LogHandler $dblogger, int $cattedra, string $tipo,
                             string $data): Response {
    // inizializza
    $label = [];
    $visibile = true;
    $argomento = '';
    $elenco = null;
    $elenco_precedente = null;
    $assenti = [];
    if ($request->isMethod('GET')) {
      // inizializza sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/lezioni_voti_classe/conferma', 0);
    }
    // controllo cattedra
    $cattedra = $this->em->getRepository(Cattedra::class)->find($cattedra);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera classe
    $classe = $cattedra->getClasse();
    // controlla tipo religione
    $religione = ($cattedra->getMateria()->getTipo() == 'R' && $cattedra->getTipo() == 'A') ? 'A' :
      ($cattedra->getMateria()->getTipo() == 'R' ? 'S' : '');
    // controlla data
    if ($data == '0000-00-00.0') {
      // data non specificata
      $dataObject = new DateTime('today');
    } else {
      // data esistente
      $dataObject = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));
    }
    // elenco di alunni
    $elenco = $reg->elencoVoti($data, $this->getUser(), $classe, $cattedra->getMateria(),
      $tipo, $religione, $argomento, $visibile);
    $elenco_precedente = unserialize(serialize($elenco)); // clona oggetti
    // dati in formato stringa
    $label['materia'] = $cattedra->getMateria()->getNomeBreve();
    $label['classe'] = ''.$classe;
    $label['tipo'] = 'label.voti_'.$tipo;
    $label['festivi'] = $reg->listaFestivi();
    $label['inizio'] = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio'))->format('d/m/Y');
    $label['fine'] = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine'))->format('d/m/Y');
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('voti_classe', FormType::class)
      ->add('data', DateType::class, ['label' => 'label.data',
        'data' => $dataObject,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'mapped' => false,
        'required' => true])
      ->add('visibile', ChoiceType::class, ['label' => 'label.visibile_genitori',
        'data' => $visibile,
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('argomento', MessageType::class, ['label' => 'label.voto_argomento',
        'data' => $argomento,
        'trim' => true,
        'required' => false])
      ->add('lista', CollectionType::class, ['label' => false,
        'data' => $elenco,
        'entry_type' => VotoClasseType::class,
        'entry_options' => ['label' => false]])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
      '   onclick' => "location.href='".$this->generateUrl('lezioni_voti_quadro')."'"]])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->get('data')->addError(new FormError($trans->trans('exception.data_festiva')));
      }
      // controlla lezione
      $lezione = $this->em->getRepository(Lezione::class)->lezioneVoto($form->get('data')->getData(),
        $this->getUser(), $classe, $cattedra->getMateria());
      if (!$lezione) {
        // lezione non esiste
        $form->get('data')->addError(new FormError($trans->trans('exception.lezione_non_esiste',
          ['materia' => $cattedra->getMateria()->getNomeBreve()])));
      }
      // controlla permessi
      if (!$reg->azioneVoti($form->get('data')->getData(), $this->getUser(), $classe, $cattedra->getMateria(), null)) {
        // errore: azione non permessa
        $form->addError(new FormError($trans->trans('exception.non_permesso_in_data')));
      }
      // controllo alunni
      $lista_alunni = $reg->alunniInData($form->get('data')->getData(), $classe);
      foreach ($form->get('lista')->getData() as $valutazione) {
        // controlla alunno
        if (!in_array($valutazione->getId(), $lista_alunni) &&
            ($valutazione->getVoto() > 0 || !empty($valutazione->getGiudizio()))) {
          // errore: alunno non presente in data
          $form->addError(new FormError($trans->trans('exception.alunno_no_classe_in_data',
            ['alunno' => $valutazione->getAlunno()])));
        }
      }
      if ($form->isValid()) {
        $log['create'] = [];
        $log['edit'] = [];
        $log['delete'] = [];
        // controlla presenza alunni con voto
        $alunniVoto = [];
        foreach ($form->get('lista')->getData() as $valutazione) {
          // controlla voto
          if ($valutazione->getVoto() > 0 || !empty($valutazione->getGiudizio())) {
            $alunniVoto[] = $valutazione->getId();
          }
        }
        $conferma = 1;
        $assenti = $this->em->getRepository(Lezione::class)->alunniAssenti($lezione, $alunniVoto);
        if (!empty($assenti) && $this->reqstack->getSession()->get('/APP/ROUTE/lezioni_voti_classe/conferma', 0) != $conferma) {
          // alunni assenti: richiede conferma
          $this->reqstack->getSession()->set('/APP/ROUTE/lezioni_voti_classe/conferma', $conferma);
        } else {
          // alunni presenti
          $ordine = (int) substr($data, 11);
          // controllo verifiche su valutazionu con stessa materia/alunno/tipo/data
          $altroDocente = $this->em->getRepository(Valutazione::class)
            ->altroDocente($this->getUser(), $cattedra->getMateria(), $classe, $tipo, $data);
          if (substr($data, 0, 10) != $form->get('data')->getData()->format('Y-m-d') || $altroDocente) {
            $ordine = $this->em->getRepository(Valutazione::class)
              ->numeroOrdineClasse($cattedra->getMateria(), $classe, $tipo, $form->get('data')->getData());
          }
          foreach ($form->get('lista')->getData() as $key=>$valutazione) {
            // correzione voto
            if ($valutazione->getVoto() > 0 && $valutazione->getVoto() < 1) {
              $valutazione->setVoto(1);
            } elseif ($valutazione->getVoto() > 10) {
              $valutazione->setVoto(10);
            }
            // legge alunno
            $alunno = $this->em->getRepository(Alunno::class)->find($valutazione->getId());
            // legge vecchio voto
            $voto = ($elenco_precedente[$key]->getVotoId() ?
              $this->em->getRepository(Valutazione::class)->find($elenco_precedente[$key]->getVotoId()) : null);
            if (!$voto && ($valutazione->getVoto() > 0 || !empty($valutazione->getGiudizio()))) {
              // valutazione aggiunta
              $voto = (new Valutazione())
                ->setTipo($tipo)
                ->setVisibile($form->get('visibile')->getData())
                ->setMedia($valutazione->getMedia())
                ->setArgomento($form->get('argomento')->getData())
                ->setDocente($this->getUser())
                ->setLezione($lezione)
                ->setMateria($cattedra->getMateria())
                ->setAlunno($alunno)
                ->setVoto($valutazione->getVoto())
                ->setGiudizio($valutazione->getGiudizio())
                ->setOrdine($ordine);
              $this->em->persist($voto);
              $log['create'][] = $voto;
            } elseif ($voto && $valutazione->getVoto() == 0 && empty($valutazione->getGiudizio())) {
              // valutazione cancellata
              $log['delete'][] = [$voto->getId(), $voto];
              $this->em->remove($voto);
            } elseif ($voto && ($elenco_precedente[$key]->getVoto() != $valutazione->getVoto() ||
                      $elenco_precedente[$key]->getGiudizio() != $valutazione->getGiudizio() ||
                      $argomento != $form->get('argomento')->getData() || $visibile != $form->get('visibile')->getData() ||
                      $voto->getLezione()->getId() != $lezione->getId() || $elenco_precedente[$key]->getMedia() != $valutazione->getMedia())) {
              // valutazione modificata
              $log['edit'][] = [$voto->getId(), $voto->getVisibile(), $voto->getArgomento(),
                $voto->getLezione()->getId(), $voto->getVoto(), $voto->getGiudizio(), $voto->getMedia()];
              $voto
                ->setVisibile($form->get('visibile')->getData())
                ->setMedia($valutazione->getMedia())
                ->setLezione($lezione)
                ->setArgomento($form->get('argomento')->getData())
                ->setVoto($valutazione->getVoto())
                ->setGiudizio($valutazione->getGiudizio())
                ->setOrdine($ordine);
            } elseif ($voto) {
              // valutazione non modificata
              $voto->setOrdine($ordine);
            }
          }
          // ok: memorizza dati
          $this->em->flush();
          // log azione
          $dblogger->logAzione('VOTI', 'Voti della classe', [
            'Tipo' => $tipo,
            'Voti creati' => implode(', ', array_map(fn($e) => $e->getId(), $log['create'])),
            'Voti modificati' => implode(', ', array_map(fn($e) => '[Id: '.$e[0].', Visibile: '.$e[1].', Media: '.$e[6].', Argomento: "'.$e[2].'"'.
              ', Lezione: '.$e[3].
              ', Voto: '.$e[4].', Giudizio: "'.$e[5].'"'.']',
              $log['edit'])),
            'Voti cancellati' => implode(', ', array_map(fn($e) => '[Id: '.$e[0].', Tipo: '.$e[1]->getTipo().', Visibile: '.$e[1]->getVisibile().
              ', Media: '.$e[1]->getMedia().
              ', Argomento: "'.$e[1]->getArgomento().'", Docente: '.$e[1]->getDocente()->getId().
              ', Alunno: '.$e[1]->getAlunno()->getId().', Lezione: '.$e[1]->getLezione()->getId().
              ', Voto: '.$e[1]->getVoto().', Giudizio: "'.$e[1]->getGiudizio().'"'.']',
              $log['delete']))]);
          // redirezione
          return $this->redirectToRoute('lezioni_voti_quadro');
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/voti_classe_edit.html.twig', [
      'pagina_titolo' => 'page.lezioni_voti',
      'form' => $form->createView(),
      'form_title' => 'title.voti_classe',
      'label' => $label,
      'assenti' => $assenti]);
  }

  /**
   * Gestione dei voti per l'alunno
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra
   * @param int $alunno Identificativo dell'alunno
   * @param string $tipo Tipo della valutazione (S,O,P)
   * @param int $id Identificativo del voto (0=nuovo)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/voti/alunno/{cattedra}/{alunno}/{tipo}/{id}', name: 'lezioni_voti_alunno', requirements: ['cattedra' => '\d+', 'alunno' => '\d+', 'tipo' => 'S|O|P', 'id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function votiAlunno(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                             LogHandler $dblogger, int $cattedra, int $alunno, string $tipo,
                             int $id): Response {
    // inizializza
    $label = [];
    if ($request->isMethod('GET')) {
      // inizializza sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/lezioni_voti_alunno/conferma', 0);
    }
    // controllo cattedra
    $cattedra = $this->em->getRepository(Cattedra::class)->find($cattedra);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera classe
    $classe = $cattedra->getClasse();
    // controllo alunno
    $alunno = $this->em->getRepository(Alunno::class)->find($alunno);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo voto
    if ($id) {
      // legge voto
      $valutazione = $this->em->getRepository(Valutazione::class)->findOneBy(['id' => $id, 'alunno' => $alunno,
        'docente' => $this->getUser(), 'tipo' => $tipo]);
      if ($valutazione) {
        $valutazione_precedente = [$valutazione->getId(), $valutazione->getVisibile(), $valutazione->getArgomento(),
          $valutazione->getVoto(), $valutazione->getGiudizio(), $valutazione->getLezione()->getId(),
          $valutazione->getMedia(), $valutazione->getMateria()];
        $data = $valutazione->getLezione()->getData();
      }
    }
    if (!$id || !$valutazione) {
      // aggiungi voto
      $valutazione = (new Valutazione())
        ->setTipo($tipo)
        ->setDocente($this->getUser())
        ->setAlunno($alunno)
        ->setMateria($cattedra->getMateria())
        ->setVisibile(true)
        ->setMedia(true);
      $this->em->persist($valutazione);
      $valutazione_precedente = null;
      $data = null;
    }
    // dati in formato stringa
    $label['materia'] = $cattedra->getMateria()->getNomeBreve();
    $label['classe'] = ''.$classe;
    $label['tipo'] = 'label.voti_'.$tipo;
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome().' ('.$alunno->getDataNascita()->format('d/m/Y').')';
    $label['bes'] = $alunno->getBes();
    $label['festivi'] = $reg->listaFestivi();
    $label['inizio'] = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio'))->format('d/m/Y');
    $label['fine'] = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine'))->format('d/m/Y');
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('voti_alunno', FormType::class, $valutazione)
      ->add('data', DateType::class, ['label' => 'label.data',
        'data' => $data ?? new DateTime('today'),
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'mapped' => false,
        'required' => true])
      ->add('visibile', ChoiceType::class, ['label' => 'label.visibile_genitori',
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('media', ChoiceType::class, ['label' => 'label.voto_in_media',
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('argomento', MessageType::class, ['label' => 'label.voto_argomento',
        'trim' => true,
        'required' => false])
      ->add('voto', HiddenType::class)
      ->add('giudizio', MessageType::class, ['label' => 'label.voto_giudizio',
        'trim' => true,
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']]);
    if ($valutazione_precedente) {
      $form = $form
        ->add('delete', SubmitType::class, ['label' => 'label.delete',
          'attr' => ['widget' => 'gs-button-inline', 'class' => 'btn-danger']]);
    }
    $form = $form
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$this->generateUrl('lezioni_voti_quadro')."'"]])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // correzione voto
      if ($valutazione->getVoto() > 0 && $valutazione->getVoto() < 1) {
        $valutazione->setVoto(1);
      } elseif ($valutazione->getVoto() > 10) {
        $valutazione->setVoto(10);
      }
      // controlli
      if ($valutazione_precedente && $form->get('delete')->isClicked()) {
        // cancella voto
        $this->em->remove($valutazione);
      } else {
        // controllo data
        $errore = $reg->controlloData($form->get('data')->getData(), null);
        if ($errore) {
          // errore: festivo
          $form->get('data')->addError(new FormError($trans->trans('exception.data_festiva')));
        }
        // controlla lezione
        $lezione = $this->em->getRepository(Lezione::class)->lezioneVoto($form->get('data')->getData(),
          $this->getUser(), $classe, $cattedra->getMateria());
        if (!$lezione) {
          // lezione non esiste
          $form->get('data')->addError(new FormError($trans->trans('exception.lezione_non_esiste',
            ['materia' => $cattedra->getMateria()->getNomeBreve()])));
        } else {
          // inserisce lezione
          $valutazione->setLezione($lezione);
        }
        // controlla permessi
        if (!$reg->azioneVoti($form->get('data')->getData(), $this->getUser(), $classe, $cattedra->getMateria(), $alunno)) {
          // errore: azione non permessa
          $form->addError(new FormError($trans->trans('exception.non_permesso_in_data')));
        }
        // controlla voto
        if (empty($valutazione->getVoto()) && empty($valutazione->getGiudizio())) {
          // errore di validazione
          $form->addError(new FormError($trans->trans('exception.voto_vuoto')));
        }
      }
      if ($form->isValid()) {
        // controlla presenza alunno
        $conferma = 1;
        $assente = $this->em->getRepository(Lezione::class)->alunnoAssente($valutazione->getLezione(),
          $valutazione->getAlunno());
        if (!($valutazione_precedente && $form->get('delete')->isClicked()) && $assente &&
            $this->reqstack->getSession()->get('/APP/ROUTE/lezioni_voti_alunno/conferma', 0) != $conferma) {
          // alunno risulta assente: richiede conferma
          $this->reqstack->getSession()->set('/APP/ROUTE/lezioni_voti_alunno/conferma', $conferma);
        } else {
          // alunno risulta presente
          if (!$valutazione->getVisibile()) {
            // media non utilizzata se voto non visibile
            $valutazione->setMedia(false);
          }
          // controllo verifiche su valutazionu con stessa materia/alunno/tipo/data
          if ($data != $valutazione->getLezione()->getData()) {
            $ordine = $this->em->getRepository(Valutazione::class)
              ->numeroOrdine($cattedra->getMateria(), $alunno, $tipo, $valutazione->getLezione()->getData());
            $valutazione->setOrdine($ordine);
          }
          // ok: memorizza dati
          $this->em->flush();
          // log azione
          if ($valutazione_precedente && $form->get('delete')->isClicked()) {
            // cancellazione
            $dblogger->logAzione('VOTI', 'Cancella voto', [
              'Id' => $valutazione_precedente[0],
              'Tipo' => $tipo,
              'Visibile' => $valutazione_precedente[1],
              'Media' => $valutazione_precedente[6],
              'Argomento' => $valutazione_precedente[2],
              'Voto' => $valutazione_precedente[3],
              'Giudizio' => $valutazione_precedente[4],
              'Docente' => $valutazione->getDocente()->getId(),
              'Alunno' => $valutazione->getAlunno()->getId(),
              'Lezione' => $valutazione_precedente[5],
              'Materia' => $valutazione_precedente[6]]);
          } elseif ($valutazione_precedente && ($valutazione_precedente[3] != $valutazione->getVoto() ||
                    $valutazione_precedente[4] != $valutazione->getGiudizio() ||
                    $valutazione_precedente[2] != $valutazione->getArgomento() ||
                    $valutazione_precedente[1] != $valutazione->getVisibile() ||
                    $valutazione_precedente[6] != $valutazione->getMedia())) {
            // modifica
            $dblogger->logAzione('VOTI', 'Modifica voto', [
              'Id' => $valutazione_precedente[0],
              'Visibile' => $valutazione_precedente[1],
              'Media' => $valutazione_precedente[6],
              'Argomento' => $valutazione_precedente[2],
              'Voto' => $valutazione_precedente[3],
              'Giudizio' => $valutazione_precedente[4],
              'Lezione' => $valutazione_precedente[5]]);
          } elseif (!$valutazione_precedente) {
            // creazione
            $dblogger->logAzione('VOTI', 'Crea voto', [
              'Id' => $valutazione->getId()]);
          }
          // redirezione
          return $this->redirectToRoute('lezioni_voti_quadro');
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/voti_alunno_edit.html.twig', [
      'pagina_titolo' => 'page.lezioni_voti',
      'form' => $form->createView(),
      'form_title' => 'title.voti_alunno',
      'label' => $label]);
  }

  /**
   * Dettagli dei voti degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra (nullo se sostituzione)
   * @param int $classe Identificativo della classe
   * @param int $alunno Identificativo dell'alunno (nullo se non ancora scelto)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/voti/dettagli/{cattedra}/{classe}/{alunno}', name: 'lezioni_voti_dettagli', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'alunno' => '\d+'], defaults: ['cattedra' => 0, 'classe' => 0, 'alunno' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function votiDettagli(Request $request, TranslatorInterface $trans,
                               RegistroUtil $reg, int $cattedra, int $classe,
                               int $alunno): Response {
    // inizializza variabili
    $info = null;
    $dati = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro alunno
    if ($alunno > 0) {
      $alunno = $this->em->getRepository(Alunno::class)->find($alunno);
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    // controllo cattedra/sostituzione
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // sostituzione
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    if ($cattedra) {
      // lista alunni
      $listaAlunni = $reg->alunniInData(new DateTime(), $classe);
      $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.bes,a.note,a.religione')
        ->where('a.id IN (:lista)')
        ->setParameter('lista', $listaAlunni)
        ->orderBY('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->getQuery()
        ->getArrayResult();
      if ($alunno && in_array($alunno->getId(), $listaAlunni)) {
        // alunno indicato e presente in classe
        $info['alunno_scelto'] = $alunno->getCognome().' '.$alunno->getNome().' ('.
          $alunno->getDataNascita()->format('d/m/Y').')';
        $info['bes'] = $alunno->getBes();
        $info['note'] = $alunno->getNote();
      } else {
        // alunno non specificato o non presente in classe
        $info['alunno_scelto'] = $trans->trans('label.scegli_alunno');
        $alunno = null;
      }
      if ($alunno) {
        // recupera dati
        $dati = $reg->dettagliVoti($this->getUser(), $cattedra, $alunno);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/voti_dettagli.html.twig', [
      'pagina_titolo' => 'page.lezioni_voti_dettagli',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'alunni' => $alunni,
      'idalunno' => ($alunno ? $alunno->getId() : 0),
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Dettagli dei voti di un alunno con sostegno
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param int $cattedra Identificativo della cattedra
   * @param int $materia Identificativo della materia
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/voti/sostegno/{cattedra}/{materia}', name: 'lezioni_voti_sostegno', requirements: ['cattedra' => '\d+', 'materia' => '\d+'], defaults: ['cattedra' => 0, 'materia' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function votiSostegno(Request $request, TranslatorInterface $trans,
                               GenitoriUtil $gen, int $cattedra,
                               int $materia): Response {
    // inizializza variabili
    $materie = null;
    $info = null;
    $dati = null;
    // parametro cattedra
    if ($cattedra == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
    }
    // controllo cattedra
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $alunno = $cattedra->getAlunno();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    } else {
      // cattedra non specificata
      $classe = null;
      $alunno = null;
    }
    // parametro materia
    if ($materia > 0) {
      $materia = $this->em->getRepository(Materia::class)->find($materia);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    if ($cattedra) {
      // lista materie
      $materie = $gen->materie($classe, false);
      if ($materia && array_search($materia->getId(), array_column($materie, 'id')) !== false) {
        // materia indicata e presente in cattedre di classe
        $info['materia_scelta'] = $materia->getNome();
      } else {
        // materia non specificata o non presente in cattedre di classe
        $info['materia_scelta'] = $trans->trans('label.scegli_materia');
        $materia = null;
      }
      if ($materia) {
      // recupera dati
        $dati = $gen->voti($classe, $materia, $alunno);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/voti_sostegno.html.twig', [
      'pagina_titolo' => 'page.lezioni_voti_dettagli',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'alunno' => $alunno,
      'materie' => $materie,
      'idmateria' => ($materia ? $materia->getId() : 0),
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Stampa del quadro dei voti
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $cattedra Identificativo della cattedra (nullo se sostituzione)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/voti/stampa/{cattedra}/{classe}/{data}', name: 'lezioni_voti_stampa', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d'], defaults: ['cattedra' => 0, 'classe' => 0, 'data' => '0000-00-00'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function votiStampa(RegistroUtil $reg, PdfManager $pdf, int $cattedra, int $classe,
                             string $data): Response {
    // inizializza variabili
    $dati = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    }
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // controllo cattedra
    $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($cattedra->getTipo() == 'S' || $cattedra->getMateria()->getTipo() == 'S') {
      // cattedra di sostegno: errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni necessarie
    $classe = $cattedra->getClasse();
    $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R' && $cattedra->getTipo() == 'A') ? 'A' :
      ($cattedra->getMateria()->getTipo() == 'R' ? 'S' : '');
    // recupera dati
    $info['periodo'] = $reg->periodo($data_obj);
    $dati = $reg->quadroVoti($info['periodo']['inizio'], $info['periodo']['fine'], $this->getUser(), $cattedra);
    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      'Voti della classe '.$classe.' - '.$info['materia']);
    $html = $this->renderView('pdf/voti_quadro.html.twig', [
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati]);
    $pdf->createFromHtml($html);
    // invia il documento
    $nomefile = 'voti-'.$classe->getAnno().$classe->getSezione().'-'.
      strtoupper(str_replace(' ', '-', str_replace(['/', '.', '\'', ','], '', $info['materia']))).'.pdf';
    return $pdf->send($nomefile);
  }

  /**
   * Esporta voti in formato CSV
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra (nullo se sostituzione)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/voti/esporta/{cattedra}/{classe}/{data}', name: 'lezioni_voti_esporta', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d'], defaults: ['cattedra' => 0, 'classe' => 0, 'data' => '0000-00-00'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function votiEsporta(RegistroUtil $reg, int $cattedra, int $classe, string $data): Response {
    // inizializza variabili
    $dati = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    }
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // controllo cattedra
    $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($cattedra->getTipo() == 'S' || $cattedra->getMateria()->getTipo() == 'S') {
      // cattedra di sostegno: errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni necessarie
    $classe = $cattedra->getClasse();
    $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R' && $cattedra->getTipo() == 'A') ? 'A' :
      ($cattedra->getMateria()->getTipo() == 'R' ? 'S' : '');
    // recupera dati
    $info['periodo'] = $reg->periodo($data_obj);
    $dati = $reg->quadroVoti($info['periodo']['inizio'], $info['periodo']['fine'], $this->getUser(), $cattedra);
    // crea documento CSV
    $csv = $this->renderView('lezioni/voti_quadro.csv.twig', [
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati]);
    // invia il documento
    $nomefile = 'voti-'.$classe->getAnno().$classe->getSezione().'-'.
      strtoupper(str_replace(' ', '-', str_replace(['/', '.', '\'', ','], '', $info['materia']))).'.csv';
    $response = new Response($csv);
    $disposition = HeaderUtils::makeDisposition(
        HeaderUtils::DISPOSITION_ATTACHMENT,
        $nomefile);
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Content-Type', 'text/csv');
    return $response;
  }

  /**
   * Cancellazione di un voto
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo del voto
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/voti/cancella/{id}', name: 'lezioni_voti_cancella', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function votiCancella(RegistroUtil $reg, LogHandler $dblogger,
                               int $id): Response {
    // controllo voto
    $valutazione = $this->em->getRepository(Valutazione::class)->findOneBy(['id' => $id,
      'docente' => $this->getUser()]);
    if (!$valutazione) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla permessi
    if (!$reg->azioneVoti($valutazione->getLezione()->getData(), $this->getUser(),
        $valutazione->getAlunno()->getClasse(), $valutazione->getMateria(), $valutazione->getAlunno())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // copia per log
    $vecchiaValutazione = clone $valutazione;
    // cancella voto
    $this->em->remove($valutazione);
    // memorizzazione e log
    $dblogger->logAzione('VOTI', 'Cancella voto', [
      'Id' => $vecchiaValutazione->getId(),
      'Tipo' => $vecchiaValutazione->getTipo(),
      'Visibile' => $vecchiaValutazione->getVisibile(),
      'Media' => $vecchiaValutazione->getMedia(),
      'Argomento' => $vecchiaValutazione->getArgomento(),
      'Voto' => $vecchiaValutazione->getVoto(),
      'Giudizio' => $vecchiaValutazione->getGiudizio(),
      'Docente' => $vecchiaValutazione->getDocente()->getId(),
      'Alunno' => $vecchiaValutazione->getAlunno()->getId(),
      'Lezione' => $vecchiaValutazione->getLezione()->getId(),
      'Materia' => $vecchiaValutazione->getMateria()->getId()]);
    // redirezione
    return $this->redirectToRoute('lezioni_voti_quadro');
  }

  /**
   * Medie dei voti della classe per il docente di sostegno
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param StaffUtil $staff Funzioni di utilità per il personale
   * @param int $classe Identificativo della classe
   * @param int $periodo Periodo relativo allo scrutinio
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/lezioni/voti/quadro/sostegno/{cattedra}/{periodo}', name: 'lezioni_voti_quadro_sostegno', requirements: ['cattedra' => '\d+', 'periodo' => '1|2|3|0'], defaults: ['cattedra' => 0, 'periodo' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function votiQuadroSostegno(RegistroUtil $reg, StaffUtil $staff, int $cattedra, int $periodo): Response {
    // inizializza variabili
    $dati = null;
    $info = null;
    // parametro cattedra
    if ($cattedra == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
    }
    $cattedra = $this->em->getRepository(Cattedra::class)->find($cattedra);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $classe = $cattedra->getClasse();
    if (!$this->em->getRepository(Cattedra::class)->docenteClasse($this->getUser(), $classe, true)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // periodo
    $listaPeriodi = $reg->infoPeriodi();
    // seleziona periodo se non indicato
    if ($periodo == 0) {
      // seleziona periodo in base alla data
      $datiPeriodo = $reg->periodo(new DateTime());
      $periodo = $datiPeriodo['periodo'];
    } else {
      $datiPeriodo = $listaPeriodi[$periodo];
    }
    // info da visualizzare
    $info['periodo'] = $periodo;
    $info['listaPeriodi'] = $listaPeriodi;
    $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    $info['alunno'] = $cattedra->getAlunno();
    // legge voti
    $dati = $staff->voti($classe, $datiPeriodo);
    // visualizza pagina
    return $this->render('lezioni/voti_quadro_sostegno.html.twig', [
      'pagina_titolo' => 'page.lezioni_voti_sostegno',
      'cattedra' => $cattedra,
      'dati' => $dati,
      'info' => $info]);
  }

}
