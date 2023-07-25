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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * CampoType - form per la classe DefinizioneRichiesta
 *
 * @author Antonello Dessì
 */
class CampoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('nome_campo', TextType::class, array('label' => 'label.nome_campo',
        'attr' => ['widget' => 'gs-row-start'],
        'row_attr' => ['class' => 'offset-1 col-4'],
        'required' => true))
      ->add('tipo_campo', ChoiceType::class, array('label' => 'label.tipo_campo',
        'choices' => ['label.tipo_string' => 'string', 'label.tipo_text' => 'text',
          'label.tipo_int' => 'int', 'label.tipo_float' => 'float', 'label.tipo_bool' => 'bool',
          'label.tipo_date' => 'date', 'label.tipo_time' => 'time'],
        'attr' => ['widget' => 'gs-row-inline'],
        'row_attr' => ['class' => 'col-4'],
        'required' => true))
      ->add('campo_obbligatorio', ChoiceType::class, array('label' => 'label.campo_obbligatorio',
        'choices' => array('label.si' => true, 'label.no' => false),
        'attr' => ['widget' => 'gs-row-inline'],
        'row_attr' => ['class' => 'col-2'],
        'required' => true))
      ->add('delete', ButtonType::class, array('label' => 'label.minus',
        'attr' => ['widget' => 'gs-row-end',
          'class' => 'btn-danger btn-xs gs-remove-item',
          'title' => 'label.cancella_elemento'],
        'row_attr' => ['class' => 'col-1']
      ));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults([
      'data_class' => null]);
  }

}
