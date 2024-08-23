<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\ScansioneOraria;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ScansioneOrariaType - form per la classe ScansioneOraria
 *
 * @author Antonello Dessì
 */
class ScansioneOrariaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('visibile', HiddenType::class, ['label' => false,
        'required' => true,
        'mapped' => false])
      ->add('ora', TextType::class, ['label' => 'label.ora',
        'attr' => ['widget' => 'gs-row-start', 'class' => 'border-0 pl-1 pr-1 text-center'],
        'row_attr' => ['class' => 'col-1'],
        'disabled' => true,
        'required' => true])
      ->add('inizio', TimeType::class, ['label' => 'label.ora_inizio',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-inline'],
        'row_attr' => ['class' => 'mt-2'],
        'required' => true])
      ->add('fine', TimeType::class, ['label' => 'label.ora_fine',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-inline'],
        'row_attr' => ['class' => 'mt-2'],
        'required' => true])
      ->add('durata', ChoiceType::class, ['label' => 'label.durata',
        'choices' => ['1' => 1.0, '0,5' => 0.5, '1,5' => 1.5],
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-inline'],
        'row_attr' => ['class' => 'col-2'],
        'required' => true])
      ->add('delete', ButtonType::class, ['label' => 'label.minus',
        'attr' => ['widget' => 'gs-row-end',
          'class' => 'btn-danger btn-xs gs-remove-item',
          'title' => 'label.cancella_elemento'],
        'row_attr' => ['class' => 'col-1']]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults([
      'data_class' => ScansioneOraria::class]);
  }

}
