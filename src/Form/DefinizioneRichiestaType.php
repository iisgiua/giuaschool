<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\DefinizioneRichiesta;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * DefinizioneRichiestaType - form per la classe DefinizioneRichiesta
 *
 * @author Antonello Dessì
 */
class DefinizioneRichiestaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, array('label' => 'label.nome_modulo',
        'required' => true))
      ->add('richiedenti', ChoiceType::class, array('label' => 'label.richiedenti_modulo',
        'choices' => array('label.ruolo_funzione_GN' => 'GN', 'label.ruolo_funzione_AM' => 'AM',
          'label.ruolo_funzione_AN' => 'AN'),
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'checkbox-inline'],
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('destinatari', ChoiceType::class, array('label' => 'label.destinatari_modulo',
        'choices' => array('label.ruolo_funzione_PN' => 'PN', 'label.ruolo_funzione_SN' => 'SN',
          'label.ruolo_funzione_DN' => 'DN'),
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'checkbox-inline'],
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('campi', CollectionType::class, array('label' => 'label.campi_modulo',
        'data' => $options['dati'][0],
        'entry_type' => CampoType::class,
        'entry_options' => ['label' => false, 'row_attr' => ['class' => 'mb-0']],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => true,
        'by_reference' => false,
        'row_attr' => ['class' => 'gs-lista-campi'],
        'label_attr' => ['class' => 'position-relative text-uppercase text-primary font-weight-bold pb-3'],
        'required' => false))
      ->add('modulo', ChoiceType::class, array('label' => 'label.template_modulo',
        'choices' => $options['dati'][1],
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('allegati', IntegerType::class, array('label' => 'label.allegati_modulo',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('tipo', TextType::class, array('label' => 'label.tipo_richiesta',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('unica', ChoiceType::class, array('label' => 'label.richiesta_unica_modulo',
        'choices' => array('label.si' => true, 'label.no' => false),
        'expanded' => true,
        'attr' => ['widget' => 'gs-row-end'],
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]));
    // aggiunge data transform
    $builder->get('richiedenti')->addModelTransformer(new CallbackTransformer(
      function ($richiedenti) {
        return explode(',', $richiedenti); },
      function ($richiedenti) {
        return implode(',', $richiedenti); }));
    $builder->get('destinatari')->addModelTransformer(new CallbackTransformer(
      function ($destinatari) {
        return explode(',', $destinatari); },
      function ($destinatari) {
        return implode(',', $destinatari); }));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('return_url');
    $resolver->setDefined('dati');
    $resolver->setDefaults(array(
      'return_url' => null,
      'dati' => null,
      'data_class' => DefinizioneRichiesta::class));
  }

}
