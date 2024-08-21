<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * DocumentoType - form per i documenti
 *
 * @author Antonello Dessì
 */
class DocumentoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['form_mode'] == 'docenti') {
      // form filtro documenti docenti
      $builder
        ->add('filtro', ChoiceType::class, ['label' => 'label.filtro_documenti',
          'data' => $options['values'][0],
          'choices' => ['label.documenti_presenti' => 'D', 'label.documenti_mancanti' => 'M',
            'label.documenti_tutti' => 'T'],
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true])
        ->add('tipo', ChoiceType::class, ['label' => 'label.tipo_documenti',
          'data' => $options['values'][1],
          'choices' => ['label.piani' => 'L', 'label.programmi' => 'P', 'label.relazioni' => 'R',
            'label.maggio' => 'M'],
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true])
        ->add('classe', ChoiceType::class, ['label' => 'label.classe',
          'data' => $options['values'][2],
          'choices' => $options['values'][3],
          'placeholder' => 'label.tutte_classi',
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_value' => 'id',
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false])
        ->add('submit', SubmitType::class, ['label' => 'label.filtra',
          'attr' => ['class' => 'btn-primary']]);
      return;
    }
    if ($options['form_mode'] == 'alunni') {
      // form filtro documenti alunni
      $builder
        ->add('tipo', ChoiceType::class, ['label' => 'label.tipo_documenti',
          'data' => $options['values'][0],
          'choices' => ['label.documenti_bes_B' => 'B', 'label.documenti_bes_H' => 'H',
            'label.documenti_bes_D' => 'D'],
          'placeholder' => 'label.tutti_tipi_documento',
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false])
        ->add('classe', ChoiceType::class, ['label' => 'label.classe',
          'data' => $options['values'][1],
          'choices' => $options['values'][2],
          'placeholder' => 'label.tutte_classi',
          'choice_translation_domain' => false,
          'choice_value' => 'id',
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false])
        ->add('submit', SubmitType::class, ['label' => 'label.filtra',
          'attr' => ['class' => 'btn-primary']]);
      return;
    }
    if ($options['form_mode'] == 'bacheca') {
      // form filtro documenti bacheca
      $builder
        ->add('tipo', ChoiceType::class, ['label' => 'label.tipo_documenti',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'placeholder' => 'label.documenti_tutti',
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false])
        ->add('titolo', TextType::class, ['label' => 'label.titolo_documento',
          'data' => $options['values'][2],
          'attr' => ['placeholder' =>
          'label.titolo_documento',
          'class' => 'gs-placeholder',
          'style' => 'width:30em'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => false])
        ->add('submit', SubmitType::class, ['label' => 'label.filtra',
          'attr' => ['class' => 'btn-primary']]);
      return;
    }
    if (in_array($options['form_mode'], ['B', 'H', 'D'])) {
      // form documenti BES
      if (!empty($options['values'][0])) {
        // scelta alunno
        $builder
          ->add('classe', ChoiceType::class, ['label' => 'label.classe',
            'choices' => $options['values'][0],
            'placeholder' => 'label.scegli_classe',
            'choice_translation_domain' => false,
            'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
            'choice_value' => 'id',
            'attr' => ['class' => 'gs-placeholder'],
            'required' => false])
          ->add('alunno', HiddenType::class, ['label' => false,
            'required' => false]);
      }
      $builder
        ->add('tipo', ChoiceType::class, ['label' => 'label.tipo_documenti',
          'choices' => $options['values'][1],
          'placeholder' => 'label.scegli_tipo_documento',
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false])
        ->add('submit', SubmitType::class, ['label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']])
        ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$options['return_url']."'"]]);
      return;
    }
    // form vuoto per solo allegato
    $builder
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$options['return_url']."'"]]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('return_url');
    $resolver->setDefined('form_mode');
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'return_url' => null,
      'form_mode' => null,
      'values' => [],
      'allow_extra_fields' => true,
      'data_class' => null]);
  }

}
