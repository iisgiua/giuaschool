<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Cattedra;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * CattedraType - form per la classe Cattedra
 *
 * @author Antonello Dessì
 */
class CattedraType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'choices' => $options['values'][0],
        'choice_value' => 'id',
        'placeholder' => 'label.choose_option',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'search'],
        'required' => true])
      ->add('materia', ChoiceType::class, ['label' => 'label.materia',
        'choices' => $options['values'][1],
        'choice_value' => 'id',
        'placeholder' => 'label.choose_option',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'search'],
        'required' => true])
      ->add('alunno', ChoiceType::class, ['label' => 'label.alunno_H',
        'choices' => $options['values'][2],
        'choice_value' => 'id',
        'placeholder' => 'label.choose_option',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'search'],
        'required' => false])
      ->add('docente', ChoiceType::class, ['label' => 'label.docente',
        'choices' => $options['values'][3],
        'choice_value' => 'id',
        'placeholder' => 'label.choose_option',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'search'],
        'required' => true])
      ->add('tipo', ChoiceType::class, ['label' => 'label.tipo',
        'choices' => ['label.tipo_N' => 'N', 'label.tipo_I' => 'I', 'label.tipo_P' => 'P',
          'label.tipo_A' => 'A'],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('supplenza', CheckboxType::class, ['label' => 'label.supplenza',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('return_url');
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'return_url' => null,
      'values' => [],
      'data_class' => Cattedra::class]);
  }

}
