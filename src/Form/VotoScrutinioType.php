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


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Entity\VotoScrutinio;


/**
 * VotoScrutinioType - form per la classe VotoScrutinio
 */
class VotoScrutinioType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if (isset($options['attr']['subType'])) {
      // form parziale
      if ($options['attr']['subType'] == 'condotta') {
        // voto di condotta
        $builder
          ->add('alunno', HiddenType::class, array('property_path' => 'alunno.id'))
          ->add('unico', HiddenType::class)
          ->add('motivazione', TextareaType::class, array('label' => false,
            'property_path' => 'dati[motivazione]',
            'trim' => true,
            'required' => false))
          ->add('unanimita', ChoiceType::class, array('label' => false,
            'property_path' => 'dati[unanimita]',
            'choices' => ['label.votazione_unanimita' => true, 'label.votazione_maggioranza' => false],
            'placeholder' => null,
            'expanded' => true,
            'multiple' => false,
            'label_attr' => ['class' => 'radio-inline gs-mr-4'],
            'required' => false))
          //-- ->add('contrari', ChoiceType::class, array('label' => false,
            //-- 'property_path' => 'dati[contrari]',
            //-- 'choices' => ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10],
            //-- 'expanded' => false,
            //-- 'multiple' => false,
            //-- 'choice_translation_domain' => false,
            //-- 'required' => false));
          ->add('contrari', TextType::class, array('label' => false,
            'property_path' => 'dati[contrari]',
            'trim' => true,
            'required' => false))
          ->add('contrari_motivazione', TextareaType::class, array('label' => false,
            'property_path' => 'dati[contrari_motivazione]',
            'trim' => true,
            'required' => false));
      } elseif ($options['attr']['subType'] == 'esito') {
        // esito
        $builder
          ->add('alunno', HiddenType::class, array('property_path' => 'alunno.id'))
          ->add('unico', HiddenType::class);
      } elseif ($options['attr']['subType'] == 'debiti') {
        // debiti
        $builder
        //-- ->add('recupero', ChoiceType::class, array('label' => false,
          //-- 'choices' => ['label.recupero_A' => 'A', 'label.recupero_S' => 'S', 'label.recupero_C' => 'C',
            //-- 'label.recupero_R' => 'R', 'label.recupero_N' => 'N'],
          //-- 'placeholder' => 'label.scegli_recupero',
          //-- 'expanded' => false,
          //-- 'multiple' => false,
          //-- 'choice_attr' => function($val, $key, $index) {
              //-- return ['class' => 'gs-no-placeholder'];
            //-- },
          //-- 'attr' => ['class' => 'gs-placeholder'],
          //-- 'required' => false))
        ->add('debito', TextareaType::class, array('label' => false,
          'trim' => true,
          'attr' => array('rows' => '3'),
          'required' => false))
        ->add('strategie', TextareaType::class, array('label' => false,
          'trim' => true,
          'attr' => array('rows' => '3'),
          'required' => false,
          'property_path' => 'dati[strategie]'));
      } elseif ($options['attr']['subType'] == 'carenze') {
        // carenze
        $builder
        ->add('debito', TextareaType::class, array('label' => false,
          'trim' => true,
          'required' => false));
      }
    } else {
      // form completo
      $builder
        ->add('alunno', HiddenType::class, array('property_path' => 'alunno.id'))
        ->add('unico', HiddenType::class)
        ->add('recupero', ChoiceType::class, array('label' => false,
          'choices' => ['label.recupero_A' => 'A', 'label.recupero_S' => 'S', 'label.recupero_C' => 'C',
            'label.recupero_R' => 'R', 'label.recupero_N' => 'N'],
          'placeholder' => 'label.scegli_recupero',
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder'];
            },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false))
        ->add('debito', TextareaType::class, array('label' => false,
          'trim' => true,
          'required' => false))
        ->add('motivazione', TextareaType::class, array('label' => false,
          'property_path' => 'dati[motivazione]',
          'trim' => true,
          'required' => false))
        ->add('unanimita', ChoiceType::class, array('label' => false,
          'property_path' => 'dati[unanimita]',
          'choices' => ['label.votazione_unanimita' => true, 'label.votazione_maggioranza' => false],
          'placeholder' => null,
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline gs-mr-4'],
          'required' => false))
        ->add('contrari', ChoiceType::class, array('label' => false,
          'property_path' => 'dati[contrari]',
          'choices' => ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10],
          'expanded' => false,
          'multiple' => false,
          'choice_translation_domain' => false,
          'required' => false));
    }
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(array('data_class' => VotoScrutinio::class));
  }

}
