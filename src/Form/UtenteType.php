<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;


/**
 * UtenteType - form per la classe Utente
 *
 * @author Antonello Dessì
 */
class UtenteType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'password') {
      // form password
      $builder
        ->add('username', TextType::class, array('label' => 'label.username',
          'required' => true))
        ->add('password', RepeatedType::class, array(
          'type' => PasswordType::class,
          'invalid_message' => 'password.nomatch',
          'first_options' => array('label' => 'label.password'),
          'second_options' => array('label' => 'label.password2'),
          'required' => true));
    } elseif ($options['formMode'] == 'alias') {
      // form alias
      $builder
        ->add('username', TextType::class, array('label' => 'label.username',
          'required' => true));
    }
    // aggiunge pulsanti al form
    if ($options['returnUrl']) {
      $builder
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['returnUrl']."'"]));
    } else {
      $builder
        ->add('submit', SubmitType::class, array('label' => 'label.submit'));
    }
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('formMode');
    $resolver->setDefined('returnUrl');
    $resolver->setDefined('data');
    $resolver->setDefaults(array(
      'formMode' => 'password',
      'returnUrl' => null,
      'data' => null,
      'data_class' => null));
  }

}
