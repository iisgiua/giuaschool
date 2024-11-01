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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, ['label' => 'label.nome_modulo',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('sede', ChoiceType::class, ['label' => 'label.sede',
        'choices' => $options['values'][0],
        'choice_value' => 'id',
        'placeholder' => 'label.choose_option',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false])
      ->add('richiedenti', ChoiceType::class, ['label' => 'label.richiedenti_modulo',
        'choices' => ['label.ruolo_funzione_TN' => 'TN', 'label.ruolo_funzione_DN' => 'DN',
          'label.ruolo_funzione_GN' => 'GN', 'label.ruolo_funzione_AM' => 'AM',
          'label.ruolo_funzione_AN' => 'AN'],
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'checkbox-inline'],
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('destinatari', ChoiceType::class, ['label' => 'label.destinatari_modulo',
        'choices' => ['label.ruolo_funzione_PN' => 'PN', 'label.ruolo_funzione_SN' => 'SN',
          'label.ruolo_funzione_DN' => 'DN'],
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'checkbox-inline'],
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('campi', CollectionType::class, ['label' => 'label.campi_modulo',
        'data' => $options['values'][1],
        'entry_type' => CampoType::class,
        'entry_options' => ['label' => false, 'row_attr' => ['class' => 'mb-0']],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => true,
        'by_reference' => false,
        'row_attr' => ['class' => 'gs-lista-campi'],
        'label_attr' => ['class' => 'position-relative text-uppercase text-primary font-weight-bold pb-3'],
        'required' => false])
      ->add('modulo', ChoiceType::class, ['label' => 'label.template_modulo',
        'choices' => $options['values'][2],
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('allegati', IntegerType::class, ['label' => 'label.allegati_modulo',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('unica', ChoiceType::class, ['label' => 'label.richiesta_unica_modulo',
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'attr' => ['widget' => 'gs-row-start'],
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('gestione', ChoiceType::class, ['label' => 'label.gestione_modulo',
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'attr' => ['widget' => 'gs-row-end'],
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true])
      ->add('tipo', TextType::class, ['label' => 'label.tipo_richiesta',
	      'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
	      'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]]);
    // aggiunge data transform
    $builder->get('richiedenti')->addModelTransformer(new CallbackTransformer(
      fn($richiedenti) => explode(',', (string) $richiedenti),
      fn($richiedenti) => implode(',', $richiedenti)));
    $builder->get('destinatari')->addModelTransformer(new CallbackTransformer(
      fn($destinatari) => explode(',', (string) $destinatari),
      fn($destinatari) => implode(',', $destinatari)));
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
      'values' => [],
      'data_class' => DefinizioneRichiesta::class]);
  }

}
