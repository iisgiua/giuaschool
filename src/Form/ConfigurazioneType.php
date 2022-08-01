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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Form\MessageType;
use App\Entity\Configurazione;


/**
 * ConfigurazioneType - form per i paramtri di configurazione
 *
 * @author Antonello Dessì
 */
class ConfigurazioneType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'banner') {
    // form banner
      $builder
        ->add('banner_login', MessageType::class, array('label' => 'label.banner_login',
          'data' => $options['dati'][0],
          'attr' => ['rows' => '3'],
          'required' => false))
        ->add('banner_home', MessageType::class, array('label' => 'label.banner_home',
          'data' => $options['dati'][1],
          'attr' => ['rows' => '3'],
          'required' => false));
    } elseif ($options['formMode'] == 'manutenzione') {
    // form manutenzione
      $builder
        ->add('manutenzione', CheckboxType::class, array('label' => 'label.manutenzione_attiva',
          'data' => $options['dati'][0],
          'required' => false))
        ->add('data_inizio', DateType::class, array('label' => 'label.data_inizio',
          'data' => $options['dati'][1],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-start'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('ora_inizio', TimeType::class, array('label' => 'label.ora_inizio',
          'data' => $options['dati'][2],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true))
        ->add('data_fine', DateType::class, array('label' => 'label.data_fine',
          'data' => $options['dati'][3],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-start'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('ora_fine', TimeType::class, array('label' => 'label.ora_fine',
          'data' => $options['dati'][4],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true));
    } elseif ($options['formMode'] == 'parametri') {
      // form parametri
      $builder
        ->add('parametri', CollectionType::class, array('label' => false,
          'data' => $options['dati'],
          'entry_type' => ParametroType::class,
          'entry_options' => []));
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
    $resolver->setDefined('dati');
    $resolver->setDefaults(array(
      'formMode' => 'parametri',
      'returnUrl' => null,
      'dati' => null,
      'data_class' => null));
  }

}


/**
 * ParametroType - form per un parametro di configurazione
 *
 * @author Antonello Dessì
 */
class ParametroType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    $builder
      ->add('valore', TextType::class, array(
        'empty_data' => '',
        'required' => false))
      ->add('categoria', HiddenType::class, array(
        'disabled' => true,
        'required' => false))
      ->add('parametro', HiddenType::class, array(
        'disabled' => true,
        'required' => false))
      ->add('descrizione', HiddenType::class, array(
        'disabled' => true,
        'required' => false));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(array(
      'data_class' => Configurazione::class));
    }

}
