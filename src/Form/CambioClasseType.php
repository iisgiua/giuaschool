<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\CambioClasse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * CambioClasseType - form per la classe CambioClasse
 *
 * @author Antonello Dessì
 */
class CambioClasseType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['form_mode'] == 'A') {
      // form cambio generico
      $builder
        ->add('alunno', ChoiceType::class, array('label' => 'label.alunno',
          'choices' => $options['values'][0],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('classe', ChoiceType::class, array('label' => 'label.classe',
          'choices' => $options['values'][1],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-row-start'],
          'required' => true))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true))
        ->add('note', TextType::class, array('label' => 'label.note',
          'required' => false));
    } elseif ($options['form_mode'] == 'I') {
      // form inserimento alunno
      $builder
        ->add('alunno', ChoiceType::class, array('label' => 'label.alunno',
          'choices' => $options['values'][0],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('cancella', CheckboxType::class, array('label' => 'label.cancella_dati',
          'mapped' => false,
          'required' => false));
    } elseif ($options['form_mode'] == 'T') {
      // form trasferimento alunno
      $builder
        ->add('alunno', ChoiceType::class, array('label' => 'label.alunno',
          'choices' => $options['values'][0],
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('cancella', CheckboxType::class, array('label' => 'label.cancella_dati',
          'mapped' => false,
          'required' => false));
    } elseif ($options['form_mode'] == 'S') {
      // form cambio sezione
      $builder
        ->add('alunno', ChoiceType::class, array('label' => 'label.alunno',
          'choices' => $options['values'][0],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('classe', ChoiceType::class, array('label' => 'label.classe',
          'choices' => $options['values'][1],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('cancella', CheckboxType::class, array('label' => 'label.cancella_dati',
          'mapped' => false,
          'required' => false));
    }
    // aggiunge campi finali
    $builder
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('return_url');
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'form_mode' => null,
      'return_url' => null,
      'values' => [],
      'data_class' => CambioClasse::class));
  }

}
