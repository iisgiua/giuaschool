<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * RicercaType - form per filtro di ricerca
 *
 * @author Antonello Dessì
 */
class RicercaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    if ($options['form_mode'] == 'ata') {
      // form ata
      $builder
        ->add('sede', ChoiceType::class, ['label' => 'label.sede',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'choice_value' => (fn($c) => is_object($c) ? $c->getId() : (int) $c),
          'placeholder' => 'label.qualsiasi_sede',
          'choice_translation_domain' => false,
          'required' => false])
        ->add('cognome', TextType::class, ['label' => 'label.cognome',
          'data' => $options['values'][2],
          'required' => false])
        ->add('nome', TextType::class, ['label' => 'label.nome',
          'data' => $options['values'][3],
          'required' => false]);
    } elseif ($options['form_mode'] == 'docenti-alunni') {
      // form docenti/alunni
      $builder
        ->add('classe', ChoiceType::class, ['label' => 'label.classe',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'choice_value' => (fn($c) => is_object($c) ? $c->getId() : (int) $c),
          'placeholder' => 'label.qualsiasi_classe',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false])
        ->add('cognome', TextType::class, ['label' => 'label.cognome',
          'data' => $options['values'][2],
          'required' => false])
        ->add('nome', TextType::class, ['label' => 'label.nome',
          'data' => $options['values'][3],
          'required' => false]);
    } elseif ($options['form_mode'] == 'utenti') {
      // form utenti
      $builder
        ->add('cognome', TextType::class, ['label' => 'label.cognome',
          'data' => $options['values'][0],
          'required' => false])
        ->add('nome', TextType::class, ['label' => 'label.nome',
          'data' => $options['values'][1],
          'required' => false]);
    } elseif ($options['form_mode'] == 'cattedre') {
      // form cattedre
      $builder
        ->add('classe', ChoiceType::class, ['label' => 'label.classe',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'choice_value' => 'id',
          'placeholder' => 'label.qualsiasi_classe',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false])
        ->add('materia', ChoiceType::class, ['label' => 'label.materia',
          'data' => $options['values'][2],
          'choices' => $options['values'][3],
          'choice_value' => 'id',
          'placeholder' => 'label.qualsiasi_materia',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false])
        ->add('docente', ChoiceType::class, ['label' => 'label.docente',
          'data' => $options['values'][4],
          'choices' => $options['values'][5],
          'choice_value' => 'id',
          'placeholder' => 'label.qualsiasi_docente',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false]);
    } elseif ($options['form_mode'] == 'rappresentanti') {
      // form rappresentanti
      $builder
      ->add('tipo', ChoiceType::class, ['label' => 'label.tipo',
        'data' => $options['values'][0],
        'choices' => $options['values'][1],
        'placeholder' => 'label.tutti',
        'required' => false])
      ->add('cognome', TextType::class, ['label' => 'label.cognome',
        'data' => $options['values'][2],
        'required' => false])
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'data' => $options['values'][3],
        'required' => false]);
    }
    // pulsante filtro
    $builder
      ->add('submit', SubmitType::class, ['label' => 'label.filtra']);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'form_mode' => 'ata',
      'values' => [],
      'data_class' => null]);
  }

}
