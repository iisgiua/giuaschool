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
 * AvvisoFiltroType - form filtro per la classe Avviso
 *
 * @author Antonello Dessì
 */
class AvvisoFiltroType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    if ($options['form_mode'] == 'gestione') {
      // form filtro gestione
      $builder
        ->add('autore', ChoiceType::class, ['label' => 'label.filtro_autore',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'placeholder' => 'label.tutti_staff',
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder', 'title' => 'label.filtro_autore'],
          'required' => false])
        ->add('tipo', ChoiceType::class, ['label' => 'label.filtro_destinatario',
          'data' => $options['values'][2],
          'choices' => $options['values'][3],
          'placeholder' => 'label.tutti_tipi',
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder', 'title' => 'label.filtro_tipo'],
          'required' => false])
        ->add('mese', ChoiceType::class, ['label' => 'label.filtro_mese',
          'data' => $options['values'][4],
          'choices' => $options['values'][5],
          'placeholder' => 'label.tutti_mesi',
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder', 'title' => 'label.filtro_mese'],
          'required' => false])
        ->add('oggetto', TextType::class, ['label' => 'label.filtro_oggetto',
          'data' => $options['values'][6],
          'attr' => ['placeholder' => 'label.oggetto', 'title' => 'label.filtro_oggetto'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => false]);
    } elseif ($options['form_mode'] == 'archivio') {
      // form filtro archivio
      $builder
        ->add('anno', ChoiceType::class, ['label' => 'label.filtro_anno_scolastico',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder', 'title' => 'label.filtro_anno_scolastico'],
          'required' => true])
        ->add('autore', ChoiceType::class, ['label' => 'label.filtro_autore',
          'data' => $options['values'][2],
          'choices' => $options['values'][3],
          'placeholder' => 'label.tutti_staff',
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder', 'title' => 'label.filtro_autore'],
          'required' => false])
        ->add('tipo', ChoiceType::class, ['label' => 'label.filtro_destinatario',
          'data' => $options['values'][4],
          'choices' => $options['values'][5],
          'placeholder' => 'label.tutti_tipi',
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder', 'title' => 'label.filtro_tipo'],
          'required' => false])
        ->add('mese', ChoiceType::class, ['label' => 'label.filtro_mese',
          'data' => $options['values'][6],
          'choices' => $options['values'][7],
          'placeholder' => 'label.tutti_mesi',
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder', 'title' => 'label.filtro_mese'],
          'required' => false])
        ->add('oggetto', TextType::class, ['label' => 'label.filtro_oggetto',
          'data' => $options['values'][8],
          'attr' => ['placeholder' => 'label.oggetto', 'title' => 'label.filtro_oggetto'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => false]);
    } elseif ($options['form_mode'] == 'bacheca') {
      // form filtro bacheca
      $builder
        ->add('visualizza', ChoiceType::class, ['label' => 'label.avvisi_filtro_visualizza',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true])
        ->add('mese', ChoiceType::class, ['label' => 'label.filtro_mese',
          'data' => $options['values'][2],
          'choices' => $options['values'][3],
          'placeholder' => 'label.tutti_mesi',
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false])
        ->add('oggetto', TextType::class, ['label' => 'label.avvisi_filtro_oggetto',
          'data' => $options['values'][4],
          'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => false]);
    }
    // Aggiunge pulsanti
    $builder
      ->add('submit', SubmitType::class, ['label' => 'label.filtra',
        'attr' => ['class' => 'btn-primary']]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'form_mode' => 'gestione',
      'values' => null,
      'data_class' => null]);
  }

}
