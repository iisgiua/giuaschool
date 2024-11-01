<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Sede;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * SedeType - form per la classe Sede
 *
 * @author Antonello Dessì
 */
class SedeType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, ['label' => 'label.nome_sede',
        'required' => true])
      ->add('nomeBreve', TextType::class, ['label' => 'label.nome_breve_sede',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('citta', TextType::class, ['label' => 'label.citta',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('indirizzo1', TextType::class, ['label' => 'label.indirizzo',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('indirizzo2', TextType::class, ['label' => 'label.indirizzo_cap',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('telefono', TelType::class, ['label' => 'label.telefono',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('ordinamento', IntegerType::class, ['label' => 'label.ordinamento',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
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
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefined('return_url');
    $resolver->setDefaults([
      'return_url' => null,
      'data_class' => Sede::class]);
  }

}
