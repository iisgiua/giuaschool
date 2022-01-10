<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Avviso;


/**
 * AvvisoType - form per la classe Avviso
 */
class AvvisoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'generico') {
      // form generico
      $builder
        ->add('data', DateType::class, array('label' => 'label.data_evento',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('oggetto', TextType::class, array('label' => 'label.oggetto',
          'required' => true))
        ->add('testo', MessageType::class, array('label' => 'label.testo',
          'attr' => array('rows' => '4'),
          'required' => true))
        ->add('creaAnnotazione', ChoiceType::class, array('label' => 'label.crea_annotazione',
          'data' => $options['dati'][0],
          'choices' => ['label.si' => true, 'label.no' => false],
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline'],
          'mapped' => false,
          'required' => true))
        ->add('sedi', EntityType::class, array('label' => 'label.sede',
          'class' => 'App:Sede',
          'choice_label' => 'citta',
          'query_builder' => function (EntityRepository $er) use ($options) {
              if ($options['dati'][1]) {
                return $er->createQueryBuilder('s')
                  ->where('s.id=:sede')
                  ->setParameter(':sede', $options['dati'][1])
                  ->orderBy('s.ordinamento', 'ASC');
              } else {
                return $er->createQueryBuilder('s')
                  ->orderBy('s.ordinamento', 'ASC');
              }
            },
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => true))
        ->add('destinatariAta', ChoiceType::class, array('label' => 'label.destinatari_ATA',
          'choices' => ['label.dsga' => 'D', 'label.ata' => 'A'],
          'placeholder' => false,
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => false))
        ->add('destinatari', ChoiceType::class, array('label' => 'label.destinatari',
          'choices' => ['label.coordinatori' => 'C', 'label.docenti' => 'D', 'label.genitori' => 'G',
            'label.alunni' => 'A'],
          'placeholder' => false,
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => false))
        ->add('filtroTipo', ChoiceType::class, array('label' => 'label.filtro_tipo',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
            'label.filtro_materia' => 'M', 'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder', 'style' => 'width:auto;display:inline'],
          'required' => false))
        ->add('filtro', HiddenType::class, array('label' => false,
          'required' => false))
        ->add('classi', EntityType::class, array('label' => 'label.scegli_classi',
          'class' => 'App:Classe',
          'choice_label' => function ($obj) {
              return $obj->getAnno().'ª '.$obj->getSezione();
            },
          'query_builder' => function (EntityRepository $er) use($options) {
              if ($options['dati'][1]) {
                return $er->createQueryBuilder('c')
                  ->where('c.sede=:sede')
                  ->setParameter(':sede', $options['dati'][1])
                  ->orderBy('c.sede,c.sezione,c.anno', 'ASC');
              } else {
                return $er->createQueryBuilder('c')
                  ->orderBy('c.sede,c.sezione,c.anno', 'ASC');
              }
            },
          'group_by' => function ($obj) {
              return $obj->getSede()->getCitta().'-'.$obj->getSezione();
            },
          'expanded' => true,
          'multiple' => true,
          'required' => false,
          'mapped' => false))
        ->add('materie', EntityType::class, array('label' => 'label.scegli_materie',
          'class' => 'App:Materia',
          'choice_label' => function ($obj) {
              return $obj->getNome();
            },
          'query_builder' => function (EntityRepository $er) {
              return $er->createQueryBuilder('m')
                ->where("m.tipo IN ('N','R','S')")
                ->orderBy('m.nome', 'ASC');
            },
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'checkbox-split-vertical gs-pt-0'],
          'required' => false,
          'mapped' => false))
        ->add('lista_classi', EntityType::class, array('label' => 'label.scegli_classi',
          'class' => 'App:Classe',
          'choice_label' => function ($obj) {
              return $obj->getAnno().'ª '.$obj->getSezione();
            },
          'query_builder' => function (EntityRepository $er) use ($options) {
              if ($options['dati'][1]) {
                return $er->createQueryBuilder('c')
                  ->where('c.sede=:sede')
                  ->setParameter(':sede', $options['dati'][1])
                  ->orderBy('c.anno,c.sezione', 'ASC');
              } else {
                return $er->createQueryBuilder('c')
                  ->orderBy('c.anno,c.sezione', 'ASC');
              }
            },
          'group_by' => function ($obj) {
              return $obj->getSede()->getCitta();
            },
          'placeholder' => 'label.classe',
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder'];
            },
          'attr' => ['style' => 'width:auto;display:inline-block', 'class' => 'gs-placeholder'],
          'required' => false,
          'mapped' => false));
    } elseif ($options['formMode'] == 'orario') {
      // form orario
      $builder
        ->add('data', DateType::class, array('label' => 'label.data_evento',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('ora', TimeType::class, array(
          'label' => ($options['dati'][0] == 'E' ? 'label.ora_entrata' : 'label.ora_uscita'),
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => true))
        ->add('testo', MessageType::class, array('label' => 'label.testo',
          'attr' => array('rows' => '4'),
          'required' => true))
        ->add('sedi', EntityType::class, array('label' => 'label.sede',
          'class' => 'App:Sede',
          'choice_label' => 'citta',
          'query_builder' => function (EntityRepository $er) use ($options) {
              if ($options['dati'][1]) {
                return $er->createQueryBuilder('s')
                  ->where('s.id=:sede')
                  ->setParameter(':sede', $options['dati'][1])
                  ->orderBy('s.ordinamento', 'ASC');
              } else {
                return $er->createQueryBuilder('s')
                  ->orderBy('s.ordinamento', 'ASC');
              }
            },
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => true))
        ->add('filtro', HiddenType::class, array('label' => false,
          'required' => false))
        ->add('classi', EntityType::class, array('label' => 'label.scegli_classi',
          'class' => 'App:Classe',
          'choice_label' => function ($obj) {
              return $obj->getAnno().'ª '.$obj->getSezione();
            },
          'query_builder' => function (EntityRepository $er) use($options) {
              if ($options['dati'][1]) {
                return $er->createQueryBuilder('c')
                  ->where('c.sede=:sede')
                  ->setParameter(':sede', $options['dati'][1])
                  ->orderBy('c.sede,c.sezione,c.anno', 'ASC');
              } else {
                return $er->createQueryBuilder('c')
                  ->orderBy('c.sede,c.sezione,c.anno', 'ASC');
              }
            },
          'group_by' => function ($obj) {
              return $obj->getSede()->getCitta().'-'.$obj->getSezione();
            },
          'label_attr' => ['class' => 'gs-checkbox-inline col-sm-2 gs-pt-1'],
          'expanded' => true,
          'multiple' => true,
          'required' => true,
          'mapped' => false));
    } elseif ($options['formMode'] == 'attivita') {
      // form attività
      $builder
        ->add('data', DateType::class, array('label' => 'label.data_evento',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('ora', TimeType::class, array('label' => 'label.ora_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => true))
        ->add('oraFine', TimeType::class, array('label' => 'label.ora_fine',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => true))
        ->add('testo', MessageType::class, array('label' => 'label.testo',
          'attr' => array('rows' => '4'),
          'required' => true))
        ->add('sedi', EntityType::class, array('label' => 'label.sede',
          'class' => 'App:Sede',
          'choice_label' => 'citta',
          'query_builder' => function (EntityRepository $er) use ($options) {
              if ($options['dati'][0]) {
                return $er->createQueryBuilder('s')
                  ->where('s.id=:sede')
                  ->setParameter(':sede', $options['dati'][0])
                  ->orderBy('s.ordinamento', 'ASC');
              } else {
                return $er->createQueryBuilder('s')
                  ->orderBy('s.ordinamento', 'ASC');
              }
            },
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => true))
        ->add('filtro', HiddenType::class, array('label' => false,
          'required' => false))
        ->add('classi', EntityType::class, array('label' => 'label.scegli_classi',
          'class' => 'App:Classe',
          'choice_label' => function ($obj) {
              return $obj->getAnno().'ª '.$obj->getSezione();
            },
          'query_builder' => function (EntityRepository $er) use($options) {
              if ($options['dati'][0]) {
                return $er->createQueryBuilder('c')
                  ->where('c.sede=:sede')
                  ->setParameter(':sede', $options['dati'][0])
                  ->orderBy('c.sede,c.sezione,c.anno', 'ASC');
              } else {
                return $er->createQueryBuilder('c')
                  ->orderBy('c.sede,c.sezione,c.anno', 'ASC');
              }
            },
          'group_by' => function ($obj) {
              return $obj->getSede()->getCitta().'-'.$obj->getSezione();
            },
          'label_attr' => ['class' => 'gs-checkbox-inline col-sm-2 gs-pt-1'],
          'expanded' => true,
          'multiple' => true,
          'required' => true,
          'mapped' => false));
    } elseif ($options['formMode'] == 'individuale') {
      // form messaggio indivisuale
      $builder
        ->add('testo', MessageType::class, array('label' => 'label.testo',
          'attr' => array('rows' => '4'),
          'required' => true))
        ->add('sedi', EntityType::class, array('label' => 'label.sede',
          'class' => 'App:Sede',
          'choice_label' => 'citta',
          'query_builder' => function (EntityRepository $er) use ($options) {
              if ($options['dati'][0]) {
                return $er->createQueryBuilder('s')
                  ->where('s.id=:sede')
                  ->setParameter(':sede', $options['dati'][0])
                  ->orderBy('s.ordinamento', 'ASC');
              } else {
                return $er->createQueryBuilder('s')
                  ->orderBy('s.ordinamento', 'ASC');
              }
            },
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => true))
        ->add('filtro', HiddenType::class, array('label' => false,
          'required' => false))
        ->add('lista_classi', EntityType::class, array('label' => 'label.scegli_classi',
          'class' => 'App:Classe',
          'choice_label' => function ($obj) {
              return $obj->getAnno().'ª '.$obj->getSezione();
            },
          'query_builder' => function (EntityRepository $er) use ($options) {
              if ($options['dati'][0]) {
                return $er->createQueryBuilder('c')
                  ->where('c.sede=:sede')
                  ->setParameter(':sede', $options['dati'][0])
                  ->orderBy('c.anno,c.sezione', 'ASC');
              } else {
                return $er->createQueryBuilder('c')
                  ->orderBy('c.anno,c.sezione', 'ASC');
              }
            },
          'group_by' => function ($obj) {
              return $obj->getSede()->getCitta();
            },
          'placeholder' => 'label.classe',
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder'];
            },
          'attr' => ['style' => 'width:auto;display:inline-block', 'class' => 'gs-placeholder'],
          'required' => false,
          'mapped' => false));
    } elseif ($options['formMode'] == 'coordinatore') {
      // form coordinatore
      $builder
        ->add('testo', MessageType::class, array('label' => 'label.testo',
          'attr' => array('rows' => '4'),
          'required' => true))
        ->add('creaAnnotazione', ChoiceType::class, array('label' => 'label.crea_annotazione',
          'data' => $options['dati'][0],
          'choices' => ['label.si' => true, 'label.no' => false],
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline'],
          'mapped' => false,
          'required' => true))
        ->add('destinatari', ChoiceType::class, array('label' => 'label.destinatari',
          'choices' => ['label.docenti_classe' => 'D', 'label.genitori_classe' => 'G',
            'label.alunni_classe' => 'A'],
          'placeholder' => false,
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => false))
        ->add('filtroTipo', ChoiceType::class, array('label' => 'label.filtro_tipo',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T',
            'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder', 'style' => 'width:auto;display:inline'],
          'required' => false))
        ->add('filtro', HiddenType::class, array('label' => false,
          'required' => false));
    } elseif ($options['formMode'] == 'verifica' || $options['formMode'] == 'compito') {
      // form verifica/compito
      $builder
        ->add('data', DateType::class, array(
          'label' => $options['formMode'] == 'verifica' ? 'label.data_verifica' : 'label.data_compito',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('cattedra', ChoiceType::class, array(
          'label' => $options['formMode'] == 'verifica' ? 'label.cattedra_verifica' : 'label.cattedra_compito',
          'choices' => $options['dati'][0],
          'expanded' => false,
          'multiple' => false,
          'placeholder' => 'label.scegli_cattedra',
          'choice_translation_domain' => false,
          'required' => true))
        ->add('materia_sostegno', HiddenType::class, array('label' => false,
          'data' => $options['dati'][1],
          'mapped' => false,
          'required' => false))
        ->add('testo', MessageType::class, array(
          'label' => $options['formMode'] == 'verifica' ? 'label.descrizione_verifica' : 'label.descrizione_compito',
          'attr' => array('rows' => '4'),
          'required' => true))
        ->add('filtroTipo', ChoiceType::class, array('label' => 'label.filtro_tipo',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder', 'style' => 'width:auto;display:inline'],
          'required' => false))
        ->add('filtro', HiddenType::class, array('label' => false,
          'required' => false));
    }
    // aggiunge pulsanti
    $builder
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['class' => 'btn-primary btn gs-mr-3']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['onclick' => "location.href='".$options['returnUrl']."'"]));
    // aggiunge data transform
    $builder->get('filtro')->addModelTransformer(new CallbackTransformer(
      function ($filtro) {
        return implode(',', $filtro);
      },
      function ($filtro) {
        return explode(',', $filtro);
      }));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('formMode');
    $resolver->setDefined('returnUrl');
    $resolver->setDefined('dati');
    $resolver->setDefaults(array(
      'formMode' => 'generico',
      'returnUrl' => null,
      'dati' => null,
      'data_class' => Avviso::class));
  }

}
