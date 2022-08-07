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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Form\MessageType;
use App\Entity\Colloquio;


/**
 * ColloquioType - form per la classe Colloquio
 *
 * @author Antonello Dessì
 */
class ColloquioType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    if ($options['formMode'] == 'sede') {
      // colloqui con sede indicata
      $builder
        ->add('sede', EntityType::class, array('label' => 'label.sede',
          'data' => $options['dati'][0],
          'class' => 'App\Entity\Sede',
          'choice_label' => 'citta',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')->orderBy('s.ordinamento', 'ASC'); },
          'placeholder' => 'label.choose_option',
          'mapped' => false,
          'disabled' => ($options['dati'][0] !== null),
          'required' => true))
        ->add('docente', EntityType::class, array('label' => 'label.docente',
          'class' => 'App\Entity\Docente',
          'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=1 AND d NOT INSTANCE OF App\Entity\Preside')
              ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
          'placeholder' => 'label.choose_option',
          'disabled' => ($options['dati'][1] !== null),
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('giorno', ChoiceType::class, array('label' => 'label.giorno',
          'choices' => ['label.lunedi' => 1, 'label.martedi' => 2, 'label.mercoledi' => 3, 'label.giovedi' => 4,
            'label.venerdi' => 5, 'label.sabato' => 6 ],
          'placeholder' => 'label.choose_option',
          'attr' => ['widget' => 'gs-row-start'],
          'required' => true))
        ->add('ora', IntegerType::class, array('label' => 'label.ora',
          'attr' => ['min' => 1, 'widget' => 'gs-row-end'],
          'required' => true))
        ->add('frequenza', ChoiceType::class, array('label' => 'label.frequenza',
          'choices' => ['label.ogni_settimana' => 'S' , 'label.prima_settimana' => '1',
            'label.seconda_settimana' => '2', 'label.terza_settimana' => '3', 'label.ultima_settimana' => '4'],
          'placeholder' => 'label.choose_option',
          'required' => true))
        ->add('note', TextType::class, array('label' => 'label.note',
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['returnUrl']."'"]));
    } elseif ($options['formMode'] == 'noSede') {
      // colloqui senza sede (a distanza)
      $builder
        //-- ->add('note', MessageType::class, array('label' => 'label.colloqui_note',
          //-- 'attr' => ['rows' => 3],
          //-- 'required' => false))
        ->add('codice', TextType::class, array('label' => 'label.colloqui_codice',
          'data' => $options['dati'][0],
          'required' => true,
          'mapped' => false))
        ->add('frequenza', ChoiceType::class, array('label' => 'label.colloqui_frequenza',
          'choices'  => ['label.frequenza_colloquio_S' => 'S', 'label.frequenza_colloquio_1' => '1',
            'label.frequenza_colloquio_2' => '2', 'label.frequenza_colloquio_3' => '3',
            'label.frequenza_colloquio_4' => '4'],
          'required' => true))
        ->add('giorno', ChoiceType::class, array('label' => 'label.giorno',
          'choices'  => ['label.lunedi' => 1, 'label.martedi' => 2, 'label.mercoledi' => 3, 'label.giovedi' => 4,
            'label.venerdi' => 5, 'label.sabato' => 6],
          'required' => true))
        ->add('ora', ChoiceType::class, array('label' => 'label.ora',
          'choices'  => $options['dati'][1],
          'choice_translation_domain' => false,
          'required' => true))
        ->add('extra', CollectionType::class, array('label' => 'label.colloqui_ore_extra',
          'data' => $options['dati'][2],
          'entry_options' => ['label' => false],
          'allow_add' => true,
          'allow_delete' => true,
          'prototype' => true,
          'by_reference' => false,
          'attr' => ['class' => 'hide'],
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['returnUrl']."'"]));
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
      'formMode' => 'sede',
      'returnUrl' => null,
      'dati' => null,
      'data_class' => Colloquio::class));
  }

}
