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
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\CambioClasse;


/**
 * CambioClasseType - form per la classe CambioClasse
 *
 * @author Antonello Dessì
 */
class CambioClasseType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'A') {
      // form cambio generico
      $builder
        ->add('alunno', EntityType::class, array('label' => 'label.alunno',
          'class' => 'App\Entity\Alunno',
          'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')'; },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('a')
              ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC'); },
          'placeholder' => 'label.choose_option',
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'class' => 'App\Entity\Classe',
          'choice_label' => function ($obj) {
            return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() : $obj); },
          'group_by' => 'sede.citta',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC'); },
          'placeholder' => 'label.choose_option',
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-row-start'],
          'required' => true))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true))
        ->add('note', TextType::class, array('label' => 'label.note',
          'required' => false));
    } elseif ($options['formMode'] == 'I') {
      // form inserimento alunno
      $builder
        ->add('alunno', EntityType::class, array('label' => 'label.alunno',
          'class' => 'App\Entity\Alunno',
          'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')'; },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('a')
              ->where("a.abilitato=1")
              ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC'); },
          'placeholder' => 'label.choose_option',
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('cancella', CheckboxType::class, array('label' => 'label.cancella_dati',
          'mapped' => false,
          'required' => false));
    } elseif ($options['formMode'] == 'T') {
      // form trasferimento alunno
      $builder
        ->add('alunno', EntityType::class, array('label' => 'label.alunno',
          'class' => 'App\Entity\Alunno',
          'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')'; },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('a')
              ->where("a.abilitato=1")
              ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC'); },
          'placeholder' => 'label.choose_option',
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('cancella', CheckboxType::class, array('label' => 'label.cancella_dati',
          'mapped' => false,
          'required' => false));
    } elseif ($options['formMode'] == 'S') {
      // form cambio sezione
      $builder
        ->add('alunno', EntityType::class, array('label' => 'label.alunno',
          'class' => 'App\Entity\Alunno',
          'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')'; },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('a')
              ->where("a.abilitato=1")
              ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC'); },
          'placeholder' => 'label.choose_option',
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'class' => 'App\Entity\Classe',
          'choice_label' => function ($obj) {
            return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() : $obj); },
          'group_by' => 'sede.citta',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC'); },
          'placeholder' => 'label.choose_option',
          'attr' => ['widget' => 'search'],
          'required' => true))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('cancella', CheckboxType::class, array('label' => 'label.cancella_dati',
          'mapped' => false,
          'required' => false));
    }
    // aggiunge campi finali
    $builder
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['returnUrl']."'"]));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('formMode');
    $resolver->setDefined('returnUrl');
    $resolver->setDefaults(array(
      'formMode' => null,
      'returnUrl' => null,
      'data_class' => CambioClasse::class));
  }

}
