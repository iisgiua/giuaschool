<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Circolare;


/**
 * CircolareType - form per la classe Circolare
 */
class CircolareType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('data', DateType::class, array('label' => 'label.data',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('numero', IntegerType::class, array('label' => 'label.numero',
        'required' => true))
      ->add('oggetto', TextType::class, array('label' => 'label.oggetto',
        'trim' => true,
        'required' => true))
      ->add('sedi', EntityType::class, array('label' => 'label.sede',
        'class' => 'App:Sede',
        'choice_label' => function ($obj) {
            return $obj->getCitta();
          },
        'query_builder' => function (EntityRepository $er) use ($options){
            if ($options['setSede']) {
              return $er->createQueryBuilder('s')
                ->where('s.id=:sede')
                ->setParameter(':sede', $options['setSede'])
                ->orderBy('s.ordinamento', 'ASC');
            } else {
              return $er->createQueryBuilder('s')
                ->orderBy('s.ordinamento', 'ASC');
            }
          },
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5  gs-pr-5'],
        'required' => true))
      ->add('dsga', CheckboxType::class, array('label' => 'label.dsga',
        'label_attr' => ['class' => 'gs-checkbox-inline'],
        'required' => false))
      ->add('ata', CheckboxType::class, array('label' => 'label.ata',
        'label_attr' => ['class' => 'gs-checkbox-inline'],
        'required' => false))
      ->add('coordinatori', ChoiceType::class, array('label' => 'label.coordinatori',
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C'],
        'placeholder' => false,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto;display:inline'],
        'required' => false))
      ->add('filtroCoordinatori', HiddenType::class, array('label' => false,
        'required' => false))
      ->add('docenti', ChoiceType::class, array('label' => 'label.docenti',
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
          'label.filtro_materia' => 'M', 'label.filtro_utenti' => 'U'],
        'placeholder' => false,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto;display:inline'],
        'required' => false))
      ->add('filtroDocenti', HiddenType::class, array('label' => false,
        'required' => false))
      ->add('genitori', ChoiceType::class, array('label' => 'label.genitori',
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
          'label.filtro_utenti' => 'U'],
        'placeholder' => false,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto;display:inline'],
        'required' => false))
      ->add('filtroGenitori', HiddenType::class, array('label' => false,
        'required' => false))
      ->add('alunni', ChoiceType::class, array('label' => 'label.alunni',
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
          'label.filtro_utenti' => 'U'],
        'placeholder' => false,
        'expanded' => false,
        'multiple' => false,
        'attr' => ['style' => 'width:auto;display:inline'],
        'required' => false))
      ->add('filtroAlunni', HiddenType::class, array('label' => false,
        'required' => false))
      ->add('altri', TextType::class, array('label' => 'label.altri',
        'trim' => true,
        'attr' => ['placeholder' => 'label.altri_info'],
        'required' => false))
      ->add('firma', CheckboxType::class, array('label' => 'label.firma_circolare',
        'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
        'required' => false))
      ->add('notifica', CheckboxType::class, array('label' => 'label.notifica_circolare',
        'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
        'required' => false))
      ->add('classi', EntityType::class, array('label' => 'label.scegli_classi',
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'query_builder' => function (EntityRepository $er) use($options) {
            if ($options['setSede']) {
              return $er->createQueryBuilder('c')
                ->where('c.sede=:sede')
                ->setParameter(':sede', $options['setSede'])
                ->orderBy('c.sezione,c.anno', 'ASC');
            } else {
              return $er->createQueryBuilder('c')
                ->orderBy('c.sezione,c.anno', 'ASC');
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
            if ($options['setSede']) {
              return $er->createQueryBuilder('c')
                ->where('c.sede=:sede')
                ->setParameter(':sede', $options['setSede'])
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
        'mapped' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['class' => 'btn-primary btn gs-mr-3']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['onclick' => "location.href='".$options['returnUrl']."'"]));
    // aggiunge data transform
    $builder->get('filtroCoordinatori')->addModelTransformer(new CallbackTransformer(
      function ($filtro) {
        return implode(',', $filtro);
      },
      function ($filtro) {
        return explode(',', $filtro);
      }
      ));
    $builder->get('filtroDocenti')->addModelTransformer(new CallbackTransformer(
      function ($filtro) {
        return implode(',', $filtro);
      },
      function ($filtro) {
        return explode(',', $filtro);
      }));
    $builder->get('filtroGenitori')->addModelTransformer(new CallbackTransformer(
      function ($filtro) {
        return implode(',', $filtro);
      },
      function ($filtro) {
        return explode(',', $filtro);
      }));
    $builder->get('filtroAlunni')->addModelTransformer(new CallbackTransformer(
      function ($filtro) {
        return implode(',', $filtro);
      },
      function ($filtro) {
        return explode(',', $filtro);
      }));
    $builder->get('altri')->addModelTransformer(new CallbackTransformer(
      function ($filtro) {
        return implode(',', $filtro);
      },
      function ($filtro) {
        $d = explode(',', $filtro);
        return (count($d) == 1 && empty($d[0])) ? array() : $d;
      }));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('returnUrl');
    $resolver->setDefaults(array(
      'returnUrl' => null,
      'setSede' => null,
      'data_class' => Circolare::class));
  }

}
