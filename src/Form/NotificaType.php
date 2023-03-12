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
 * NotificaType - form per la gestione delle notifiche
 *
 * @author Antonello Dessì
 */
class NotificaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // form notifiche
    $builder
      ->add('tipo', ChoiceType::class, array('label' => 'label._ok',
        'data' => $options['values'][0],
        'choices' => ['label.tipo_notifica_email' => 'email',
          'label.tipo_notifica_telegram' => 'telegram'],
        'expanded' => true,
        'multiple' => false,
        'required' => true))
      ->add('abilitato', ChoiceType::class, array('label' => false,
        'data' => $options['values'][1],
        'choices' => ['label.abilitato_notifica_circolare' => 'circolare',
          'label.abilitato_notifica_avviso' => 'avviso'],
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['class' => 'btn-primary gs-mr-3 gs-strong']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['onclick' => "location.href='".$options['returnUrl']."'",
          'class' => 'btn-default gs-strong']));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('returnUrl');
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'returnUrl' => null,
      'values' => null,
      'data_class' => null));
  }

}
