<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * FiltroType - form per filtro di ricerca
 *
 * @author Antonello Dessì
 */
class FiltroType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'richieste') {
      // form gestione richieste
      $builder
        ->add('tipo', ChoiceType::class, array('label' => false,
          'data' => $options['values'][1],
          'choices' => ['modulo' => 'U'],
          'placeholder' => 'label.qualsiasi_tipo_richiesta',
          'required' => false))
        ->add('stato', ChoiceType::class, array('label' => false,
          'data' => $options['values'][2],
          'choices' => ['label.richieste_I' => 'I', 'label.richieste_G' => 'G'],
          'placeholder' => 'label.qualsiasi_stato_richiesta',
          'required' => false))
        ->add('sede', EntityType::class, array('label' => false,
          'data' => $options['values'][3],
          'class' => 'App\Entity\Sede',
          'choice_label' => 'citta',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')->orderBy('s.ordinamento', 'ASC'); },
          'placeholder' => 'label.qualsiasi_sede',
          'required' => false));
    }
    // pulsante filtro
    $builder
      ->add('submit', SubmitType::class, array('label' => 'label.filtra'));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('formMode');
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'formMode' => 'richieste',
      'values' => [],
      'data_class' => null));
  }

}
