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
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * PaginaVotiType - form per la configurazione della pagina voti
 *
 * @author Antonello Dessì
 */
class PaginaVotiType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    // aggiunge campi al form
    $builder
      ->add('compatta', ChoiceType::class, ['label' => 'label.pagina_voti_compatta',
        'data' => $options['values'][0],
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('medie_tipi', ChoiceType::class, ['label' => 'label.pagina_voti_medie_tipi',
        'data' => $options['values'][1],
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('scritto', ChoiceType::class, ['label' => 'label.pagina_voti_scritto',
        'data' => $options['values'][2],
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('orale', ChoiceType::class, ['label' => 'label.pagina_voti_orale',
        'data' => $options['values'][3],
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('pratico', ChoiceType::class, ['label' => 'label.pagina_voti_pratico',
        'data' => $options['values'][4],
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary gs-mr-3']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$options['return_url']."'"]]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefined('return_url');
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'return_url' => null,
      'data_class' => null]);
  }

}
