<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * FiltroType - form per filtro di ricerca
 *
 * @author Antonello Dessì
 */
class FiltroType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['form_mode'] == 'richieste') {
      // form gestione richieste
      $builder
        ->add('tipo', ChoiceType::class, array('label' => 'label.richiesta_tipo',
          'data' => $options['values'][0],
          'choices' => ['label.richiesta_tipo_E' => 'E', 'label.richiesta_tipo_D' => 'D',
            'label.richiesta_tipo_altre' => '*', 'label.richiesta_tipo_tutte' => ''],
          'attr' => ['title' => 'label.richiesta_tipo'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => true))
        ->add('stato', ChoiceType::class, array('label' => 'label.richiesta_stato',
          'data' => $options['values'][1],
          'choices' => ['label.richiesta_stato_I' => 'I', 'label.richiesta_stato_G' => 'G',
            'label.richiesta_stato_R' => 'R', 'label.richiesta_stato_A' => 'A',
            'label.richiesta_stato_tutte' => ''],
          'attr' => ['title' => 'label.richiesta_stato'],
          'label_attr' => ['class' => 'sr-only'],
        'required' => true))
        ->add('sede', ChoiceType::class, array('label' => 'label.richiesta_sede',
          'data' => $options['values'][2],
          'choices' => $options['values'][7],
          'choice_translation_domain' => false,
          'attr' => ['title' => 'label.richiesta_sede'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => true))
        ->add('classe', ChoiceType::class, array('label' => 'label.classe',
          'data' => $options['values'][3],
          'choices' => $options['values'][8],
          'placeholder' => 'label.qualsiasi_classe',
          'choice_translation_domain' => false,
          'attr' => ['title' => 'label.classe'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => false))
        ->add('residenza', TextType::class, array('label' => 'label.residenza',
          'data' => $options['values'][4],
          'attr' => ['placeholder' => 'label.residenza', 'title' => 'label.residenza',
            'style' => 'width:10em'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => false))
        ->add('cognome', TextType::class, array('label' => 'label.cognome',
          'data' => $options['values'][5],
          'attr' => ['placeholder' => 'label.cognome', 'title' => 'label.cognome',
            'style' => 'width:10em'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => false))
        ->add('nome', TextType::class, array('label' => 'label.nome',
          'data' => $options['values'][6],
          'attr' => ['placeholder' => 'label.nome', 'title' => 'label.nome',
            'style' => 'width:10em'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => false));
    } elseif ($options['form_mode'] == 'colloqui') {
      // form cerca colloqui
      $builder
        ->add('docente', ChoiceType::class, ['label' => 'label.docente',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'placeholder' => 'label.scegli_docente',
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder'];
            },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false]);
    } elseif ($options['form_mode'] == 'presenze') {
      // form presenze
      $builder
        ->add('alunno', ChoiceType::class, ['label' => 'label.alunno',
          'data' => $options['values'][0],
          'choices' => $options['values'][3],
          'placeholder' => 'label.tutti_alunni',
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder'];
            },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false])
        ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
          'data' => $options['values'][1],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => false))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'data' => $options['values'][2],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => false));
    }
    // pulsante filtro
    $builder
      ->add('submit', SubmitType::class, array('label' => 'label.filtra'));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'form_mode' => 'richieste',
      'values' => [],
      'data_class' => null));
  }

}
