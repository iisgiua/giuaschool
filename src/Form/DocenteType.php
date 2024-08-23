<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Docente;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * DocenteType - form per la classe Docente
 *
 * @author Antonello Dessì
 */
class DocenteType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, ['label' => 'label.nome',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('cognome', TextType::class, ['label' => 'label.cognome',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('sesso', ChoiceType::class, ['label' => 'label.sesso',
        'choices' => ['label.maschile' => 'M', 'label.femminile' => 'F'],
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('codiceFiscale', TextType::class, ['label' => 'label.codice_fiscale',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false])
      ->add('spid', ChoiceType::class, ['label' => 'label.spid',
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('username', TextType::class, ['label' => 'label.username',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('email', TextType::class, ['label' => 'label.email',
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
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('return_url');
    $resolver->setDefaults([
      'return_url' => null,
      'data_class' => Docente::class]);
  }

}
